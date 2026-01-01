<?php

namespace App\Http\Controllers\Apps;

use App\Models\Cart;
use App\Exceptions\PaymentGatewayException;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use App\Models\Transaction;
use App\Models\PaymentSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionController extends Controller
{
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

    public function addToCart(Request $request)
    {
        // Temukan product berdasarkan barcode
        $product = Product::where('barcode', $request->barcode)->first();

        if (!$product) {
            return redirect()->back()->with('error', 'Product not found.');
        }

        if ($product->stock < $request->quantity) {
            return redirect()->back()->with('error', 'Out of Stock Product!.');
        }

        // Cari cart berdasarkan product_id
        $cart = Cart::where('product_id', $product->id)
                    ->where('cashier_id', auth()->id())
                    ->first();

        if ($cart) {
            $cart->increment('quantity', $request->quantity);
            $cart->price = $product->sell_price * $cart->quantity;
            $cart->save();
        } else {
            Cart::create([
                'cashier_id' => auth()->id(),
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $product->sell_price * $request->quantity,
                'discount' => 0, // default
            ]);
        }

        return redirect()->route('transactions.index')->with('success', 'Product Added Successfully!.');
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

        $invoice = 'TRX-' . Str::upper(Str::random(10));
        $isCashPayment = empty($paymentGateway);
        $cashAmount = $isCashPayment ? $request->cash : $request->grand_total;
        $changeAmount = $isCashPayment ? $request->change : 0;

        $transaction = DB::transaction(function () use ($request, $invoice, $cashAmount, $changeAmount, $paymentGateway, $isCashPayment) {

            $transaction = Transaction::create([
                'cashier_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'invoice' => $invoice,
                'cash' => $cashAmount,
                'change' => $changeAmount,
                'grand_total' => $request->grand_total,
                'payment_method' => $paymentGateway ?: 'cash',
                'payment_status' => $isCashPayment ? 'paid' : 'pending',
            ]);

            $carts = Cart::with('product')->where('cashier_id', auth()->id())->get();

            foreach ($carts as $cart) {
                // Simpan detail transaksi
                $transaction->details()->create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $cart->product_id, // harus ada
                    'quantity' => $cart->quantity,
                    'price' => $cart->price,
                    'discount' => $cart->discount ?? 0,
            ]);

                // Hitung profit
                $total_buy_price = $cart->product->buy_price * $cart->quantity;
                $total_sell_price = $cart->product->sell_price * $cart->quantity;
                $profits = $total_sell_price - $total_buy_price;

                $transaction->profits()->create([
                    'total' => $profits,
                ]);

                // Update stock
                $cart->product->decrement('stock', $cart->quantity);
            }

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
}
