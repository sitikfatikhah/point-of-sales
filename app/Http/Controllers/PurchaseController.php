<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\InventoryAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'supplier',
            'status',
            'date_from',
            'date_to',
        ]);

        $purchases = Purchase::query()
            ->with('items.product')
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_price_sum', 'total_price')
            ->when($filters['search'] ?? null, fn ($q, $s) =>
                $q->where('reference', 'like', "%{$s}%")
                  ->orWhere('supplier_name', 'like', "%{$s}%")
            )
            ->when($filters['supplier'] ?? null, fn ($q, $s) =>
                $q->where('supplier_name', 'like', "%{$s}%")
            )
            ->when($filters['status'] ?? null, fn ($q, $s) =>
                $q->where('status', $s)
            )
            ->when($filters['date_from'] ?? null, fn ($q, $d) =>
                $q->whereDate('purchase_date', '>=', $d)
            )
            ->when($filters['date_to'] ?? null, fn ($q, $d) =>
                $q->whereDate('purchase_date', '<=', $d)
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard/Purchase/Index', [
            'purchases' => $purchases,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Dashboard/Purchase/Create', [
            'products' => Product::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);

        DB::transaction(function () use ($data) {
            $purchase = Purchase::create($data['purchase']);

            $this->storeItems($purchase, $data['items']);

            $purchase->load('items.product');
            $this->inventoryService->processPurchase($purchase);
        });

        return to_route('purchase.index')
            ->with('success', 'Purchase created successfully.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['items.product.category', 'user']);

        return Inertia::render('Dashboard/Purchase/Show', [
            'purchase' => $purchase,
            'inventoryAdjustments' => InventoryAdjustment::with(['product', 'user'])
                ->where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->get(),
            'totals' => [
                'subtotal' => $purchase->items->sum('total_price'),
                'total_quantity' => $purchase->items->sum('quantity'),
                'total_items' => $purchase->items->count(),
                'total_tax' => $purchase->items->sum(
                    fn ($i) => $i->total_price * ($i->tax_percent / 100)
                ),
                'total_discount' => $purchase->items->sum(function ($i) {
                    $base = $i->quantity * $i->purchase_price;
                    return $base * ($i->discount_percent / 100);
                }),
            ],
        ]);
    }

    public function edit(Purchase $purchase)
    {
        return Inertia::render('Dashboard/Purchase/Edit', [
            'purchase' => $purchase->load('items.product'),
            'products' => Product::all(),
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $data = $this->validateRequest($request);

        DB::transaction(function () use ($purchase, $data) {
            $purchase->update($data['purchase']);

            $this->inventoryService->reversePurchase($purchase);
            $this->deleteInventoryJournal($purchase);

            $purchase->items()->delete();
            $this->storeItems($purchase, $data['items']);

            $purchase->load('items.product');
            $this->inventoryService->processPurchase($purchase);
        });

        return to_route('purchase.index')
            ->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase)
    {
        DB::transaction(function () use ($purchase) {
            $purchase->load('items.product');

            $this->inventoryService->reversePurchase($purchase);
            $this->deleteInventoryJournal($purchase);

            if ($purchase->invoice) {
                Storage::delete('public/purchases/' . $purchase->invoice);
            }

            $purchase->delete();
        });

        return to_route('purchase.index')
            ->with('success', 'Purchase deleted successfully.');
    }

    /* ============================================================
     |  PRIVATE HELPERS
     ============================================================ */

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'purchase_date' => 'required|date',
            'status' => 'nullable|string',
            'notes' => 'nullable|string',
            'tax_included' => 'boolean',
            'reference' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.barcode' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
        ]);

        return [
            'purchase' => collect($validated)->except('items')->toArray(),
            'items' => $validated['items'],
        ];
    }

    private function storeItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $item) {
            $product = Product::where('barcode', $item['barcode'])->first();

            $purchase->items()->create([
                'product_id' => $product?->id,
                'barcode' => $item['barcode'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'purchase_price' => $item['purchase_price'],
                'total_price' => $item['total_price'],
                'tax_percent' => $item['tax_percent'] ?? 0,
                'discount_percent' => $item['discount_percent'] ?? 0,
                'warehouse' => $item['warehouse'] ?? null,
                'batch' => $item['batch'] ?? null,
                'expired' => $item['expired'] ?? null,
                'currency' => $item['currency'] ?? 'IDR',
            ]);
        }
    }

    private function deleteInventoryJournal(Purchase $purchase): void
    {
        InventoryAdjustment::where([
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ])->delete();
    }
}
