<?php

namespace App\Http\Controllers\Apps;

use App\Models\Cart;
use App\Exceptions\PaymentGatewayException;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use App\Models\Transaction;
use App\Models\PaymentSetting;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class TransactionController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index()
    {
        //get cart
        $carts = Cart::with('product')
            ->where('cashier_id', auth()->id())
            ->latest()
            ->get();

        //get all customers
        $customers = Customer::latest()->get();

        $paymentSetting = PaymentSetting::first();

        $carts_total = 0;
        foreach ($carts as $cart) {
            $carts_total += $cart->price * $cart->quantity;
        }

        $defaultGateway = $paymentSetting?->default_gateway ?? 'cash';
        if ($defaultGateway !== 'cash' && (!$paymentSetting || !$paymentSetting->isGatewayReady($defaultGateway))) {
            $defaultGateway = 'cash';
        }

        return Inertia::render('Dashboard/Transactions/Index', [
            'carts' => $carts,
            'carts_total' => $carts_total,
            'customers' => $customers,
            'paymentGateways' => $paymentSetting?->enabledGateways() ?? [],
            'defaultPaymentGateway' => $defaultGateway,
        ]);
    }

    public function searchProduct(Request $request)
    {
        $product = Product::where('barcode', $request->barcode)->first();

        return response()->json([
            'success' => (bool)$product,
            'data' => $product
        ]);
    }

    /**
     * Search products by partial barcode or title for autocomplete suggestions
     * Only shows products with stock > 0
     */
    public function suggestProducts(Request $request)
    {
        $query = $request->input('query', '');

        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'data' => []
            ]);
        }

        // Filter hanya produk dengan stok > 0
        $products = Product::where('stock', '>', 0)
            ->where(function ($q) use ($query) {
                $q->where('barcode', 'like', "%{$query}%")
                  ->orWhere('title', 'like', "%{$query}%");
            })
            ->select('id', 'barcode', 'title', 'sell_price', 'stock')
            ->limit(10)
            ->get()
            ->map(function ($product) {
                // Tambahkan current_stock dari StockMovement
                $product->current_stock = StockMovement::getCurrentStock($product->id);
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function addToCart(Request $request)
    {
        // Temukan product berdasarkan barcode
        $product = Product::where('barcode', $request->barcode)->first();

        if (!$product) {
            return redirect()->back()->with('error', 'Produk tidak ditemukan.');
        }

        // Validasi stok menggunakan StockMovement ledger
        $currentStock = StockMovement::getCurrentStock($product->id);

        if ($currentStock <= 0) {
            return redirect()->back()->with('error', "Stok produk '{$product->title}' habis. Tidak dapat menambahkan ke keranjang.");
        }

        // Hitung total quantity di cart + yang diminta
        $existingCart = Cart::where('product_id', $product->id)
                            ->where('cashier_id', auth()->id())
                            ->first();
        $cartQuantity = $existingCart ? $existingCart->quantity : 0;
        $totalRequested = $cartQuantity + $request->quantity;

        if ($currentStock < $totalRequested) {
            return redirect()->back()->with('error', "Stok produk '{$product->title}' tidak mencukupi. Stok tersedia: {$currentStock}, di keranjang: {$cartQuantity}, diminta: {$request->quantity}");
        }

        // Tambahkan ke cart
        if ($existingCart) {
            $existingCart->increment('quantity', $request->quantity);
            $existingCart->price = $product->sell_price * $existingCart->quantity;
            $existingCart->save();
        } else {
            Cart::create([
                'cashier_id' => auth()->id(),
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $product->sell_price * $request->quantity,
                'discount' => 0,
            ]);
        }

        return redirect()->route('transactions.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function destroyCart($cart_id)
    {
        $cart = Cart::with('product')->find($cart_id);

        if ($cart) {
            $cart->delete();
            return back();
        }

        return back()->withErrors(['message' => 'Cart not found']);
    }

    public function store(Request $request, PaymentGatewayManager $paymentGatewayManager)
    {
        $paymentGateway = $request->input('payment_gateway');
        if ($paymentGateway) {
            $paymentGateway = strtolower($paymentGateway);
        }

        $paymentSetting = $paymentGateway ? PaymentSetting::first() : null;

        if ($paymentGateway && (!$paymentSetting || !$paymentSetting->isGatewayReady($paymentGateway))) {
            return redirect()->route('transactions.index')->with('error', 'Gateway pembayaran belum dikonfigurasi.');
        }

        // Validasi stok sebelum transaksi
        $carts = Cart::with('product')->where('cashier_id', auth()->id())->get();

        if ($carts->isEmpty()) {
            return redirect()->route('transactions.index')->with('error', 'Keranjang belanja kosong.');
        }

        try {
            $this->inventoryService->validateStockOrFail($carts->toArray());
        } catch (Exception $e) {
            return redirect()->route('transactions.index')->with('error', $e->getMessage());
        }

        $invoice = 'TRX-' . Str::upper(Str::random(10));
        $isCashPayment = empty($paymentGateway);
        $cashAmount = $isCashPayment ? $request->cash : $request->grand_total;
        $changeAmount = $isCashPayment ? $request->change : 0;

        $transaction = DB::transaction(function () use ($request, $invoice, $cashAmount, $changeAmount, $paymentGateway, $isCashPayment, $carts) {

            $transaction = Transaction::create([
                'cashier_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'invoice' => $invoice,
                'cash' => $cashAmount,
                'change' => $changeAmount,
                'discount' => $request->discount ?? 0,
                'grand_total' => $request->grand_total,
                'payment_method' => $paymentGateway ?: 'cash',
                'payment_status' => $isCashPayment ? 'paid' : 'pending',
            ]);

            foreach ($carts as $cart) {
                // Simpan detail transaksi
                $transaction->details()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $cart->product_id,
                    'barcode' => $cart->product->barcode ?? $cart->product->id,
                    'quantity' => $cart->quantity,
                    'price' => $cart->price,
                    'discount' => $cart->discount ?? 0,
                ]);

                // Hitung profit menggunakan average buy price dari StockMovement
                $averageBuyPrice = StockMovement::getAverageBuyPrice($cart->product_id);
                $total_buy_price = $averageBuyPrice * $cart->quantity;
                $total_sell_price = $cart->product->sell_price * $cart->quantity;
                $profits = $total_sell_price - $total_buy_price;

                $transaction->profits()->create([
                    'total' => $profits,
                ]);
            }

            // Update stock using InventoryService (after all details are created)
            $transaction->load('details.product');
            $this->inventoryService->processTransaction($transaction);

            // Hapus semua cart
            Cart::where('cashier_id', auth()->id())->delete();

            return $transaction->fresh(['customer']);
        });

        if ($paymentGateway) {
            try {
                $paymentResponse = $paymentGatewayManager->createPayment($transaction, $paymentGateway, $paymentSetting);

                $transaction->update([
                    'payment_reference' => $paymentResponse['reference'] ?? null,
                    'payment_url' => $paymentResponse['payment_url'] ?? null,
                ]);
            } catch (PaymentGatewayException $exception) {
                return redirect()
                    ->route('transactions.print', $transaction->invoice)
                    ->with('error', $exception->getMessage());
            }
        }

        return to_route('transactions.print', $transaction->invoice);
    }

    public function print($invoice)
    {
        $transaction = Transaction::with('details.product', 'cashier', 'customer')
            ->where('invoice', $invoice)
            ->firstOrFail();

        return Inertia::render('Dashboard/Transactions/Print', [
            'transaction' => $transaction
        ]);
    }

    public function history(Request $request)
    {
        $filters = [
            'invoice' => $request->input('invoice'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        $query = Transaction::query()
            ->with(['cashier:id,name', 'customer:id,name'])
            ->withSum('details as total_items', 'quantity')
            ->withSum('profits as total_profit', 'total')
            ->orderByDesc('created_at');

        if (!$request->user()->isSuperAdmin()) {
            $query->where('cashier_id', $request->user()->id);
        }

        $query
            ->when($filters['invoice'], fn($builder, $invoice) => $builder->where('invoice', 'like', "%{$invoice}%"))
            ->when($filters['start_date'], fn($builder, $date) => $builder->whereDate('created_at', '>=', $date))
            ->when($filters['end_date'], fn($builder, $date) => $builder->whereDate('created_at', '<=', $date));

        $transactions = $query->paginate(10)->withQueryString();

        return Inertia::render('Dashboard/Transactions/History', [
            'transactions' => $transactions,
            'filters' => $filters,
        ]);
    }

    public function show($invoice)
    {
        $transaction = Transaction::with([
            'details.product.category',
            'details.product.inventory',
            'cashier:id,name,email',
            'customer',
            'profits'
        ])
            ->withSum('details as total_items', 'quantity')
            ->withSum('profits as total_profit', 'total')
            ->where('invoice', $invoice)
            ->firstOrFail();

        // Get stock movements related to this transaction
        $stockMovements = StockMovement::with('product:id,title,barcode', 'user:id,name')
            ->where('reference_type', 'transaction')
            ->where('reference_id', $transaction->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Dashboard/Transactions/Show', [
            'transaction' => $transaction,
            'stockMovements' => $stockMovements,
            // Legacy support - map to old structure
            'inventoryAdjustments' => $stockMovements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product' => $movement->product,
                    'user' => $movement->user,
                    'type' => $movement->movement_type,
                    'quantity_before' => $movement->quantity_before,
                    'quantity_change' => $movement->quantity,
                    'quantity_after' => $movement->quantity_after,
                    'created_at' => $movement->created_at,
                ];
            }),
        ]);
    }
}
