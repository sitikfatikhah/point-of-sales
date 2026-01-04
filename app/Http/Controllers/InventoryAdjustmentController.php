<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * InventoryAdjustmentController
 *
 * Controller ini HANYA untuk mengelola adjustment manual dengan nomor jurnal.
 * Data purchase dan sale ditampilkan melalui StockMovement.
 */
class InventoryAdjustmentController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Display a listing of inventory adjustments.
     * Hanya menampilkan adjustment dengan nomor jurnal.
     */
    public function index(Request $request)
    {
        // Query hanya untuk adjustment dengan nomor jurnal
        $query = InventoryAdjustment::with(['product', 'user'])
            ->withJournal() // Hanya yang punya journal number
            ->orderBy('created_at', 'desc');

        // Filter by product
        if ($request->filled('product_id')) {
            $query->forProduct($request->product_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        // Filter by date range
        if ($request->filled('from') && $request->filled('to')) {
            $query->dateRange($request->from, $request->to);
        }

        // Filter by journal number
        if ($request->filled('journal_number')) {
            $query->where('journal_number', 'like', "%{$request->journal_number}%");
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    });
            });
        }

        $adjustments = $query->paginate(15)->withQueryString();

        // Get summary data
        $summary = $this->inventoryService->getInventorySummary();

        // Get products for filter
        $products = Product::select('id', 'title', 'barcode')->orderBy('title')->get();

        return Inertia::render('InventoryAdjustment/Index', [
            'adjustments' => $adjustments,
            'products' => $products,
            'summary' => $summary,
            'types' => [
                InventoryAdjustment::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                InventoryAdjustment::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                InventoryAdjustment::TYPE_RETURN => 'Return Barang',
                InventoryAdjustment::TYPE_DAMAGE => 'Barang Rusak',
                InventoryAdjustment::TYPE_CORRECTION => 'Koreksi Stok',
            ],
            'filters' => $request->only(['search', 'product_id', 'type', 'from', 'to', 'journal_number']),
        ]);
    }

    /**
     * Show the form for creating a new adjustment.
     */
    public function create()
    {
        $products = Product::with('inventory')
            ->select('id', 'title', 'barcode', 'stock')
            ->orderBy('title')
            ->get()
            ->map(function ($product) {
                // Tambahkan current_stock dari StockMovement
                $product->current_stock = StockMovement::getCurrentStock($product->id);
                return $product;
            });

        return Inertia::render('InventoryAdjustment/Create', [
            'products' => $products,
            'types' => [
                InventoryAdjustment::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                InventoryAdjustment::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                InventoryAdjustment::TYPE_RETURN => 'Return Barang',
                InventoryAdjustment::TYPE_DAMAGE => 'Barang Rusak',
                InventoryAdjustment::TYPE_CORRECTION => 'Koreksi Stok',
            ],
        ]);
    }

    /**
     * Store a newly created adjustment.
     * Membuat adjustment dengan nomor jurnal otomatis.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:' . implode(',', InventoryAdjustment::getTypes()),
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $product = Product::findOrFail($request->product_id);

        if ($request->type === InventoryAdjustment::TYPE_CORRECTION) {
            // For correction, quantity is the new stock level
            $result = $this->inventoryService->stockCorrection(
                $product,
                $request->quantity,
                $request->reason,
                auth()->id()
            );
        } else {
            $result = $this->inventoryService->createAdjustment(
                $product,
                $request->quantity,
                $request->type,
                $request->reason,
                $request->notes,
                auth()->id()
            );
        }

        $journalNumber = $result['adjustment']->journal_number ?? 'N/A';

        return redirect()->route('inventory-adjustments.index')
            ->with('success', "Adjustment berhasil dibuat dengan nomor jurnal: {$journalNumber}");
    }

    /**
     * Display the specified adjustment with full details.
     */
    public function show(InventoryAdjustment $inventoryAdjustment)
    {
        $inventoryAdjustment->load([
            'product' => function ($query) {
                $query->with(['category', 'inventory']);
            },
            'user',
            'stockMovement',
        ]);

        // Get other adjustments for the same product (recent 10)
        $relatedAdjustments = InventoryAdjustment::forProduct($inventoryAdjustment->product_id)
            ->withJournal()
            ->where('id', '!=', $inventoryAdjustment->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return Inertia::render('InventoryAdjustment/Show', [
            'adjustment' => $inventoryAdjustment,
            'relatedAdjustments' => $relatedAdjustments,
            'types' => [
                InventoryAdjustment::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                InventoryAdjustment::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                InventoryAdjustment::TYPE_RETURN => 'Return Barang',
                InventoryAdjustment::TYPE_DAMAGE => 'Barang Rusak',
                InventoryAdjustment::TYPE_CORRECTION => 'Koreksi Stok',
            ],
        ]);
    }

    /**
     * Display product inventory detail with all stock movements.
     * Menampilkan semua pergerakan stok (purchase, sale, adjustment) dari StockMovement.
     */
    public function productHistory(Product $product, Request $request)
    {
        $product->load(['category', 'inventory']);

        // Query dari StockMovement untuk semua pergerakan
        $query = StockMovement::forProduct($product->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        // Filter by date range
        if ($request->filled('from') && $request->filled('to')) {
            $query->dateRange($request->from, $request->to);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }

        $movements = $query->paginate(20)->withQueryString();

        // Get summary for this product
        $summary = [
            'total_in' => StockMovement::forProduct($product->id)
                ->incoming()
                ->sum('quantity'),
            'total_out' => abs(StockMovement::forProduct($product->id)
                ->outgoing()
                ->sum('quantity')),
            'current_stock' => StockMovement::getCurrentStock($product->id),
            'average_buy_price' => StockMovement::getAverageBuyPrice($product->id),
            'inventory_stock' => optional($product->inventory)->quantity ?? 0,
        ];

        return Inertia::render('InventoryAdjustment/ProductHistory', [
            'product' => $product,
            'movements' => $movements,
            // Legacy support
            'adjustments' => $movements,
            'summary' => $summary,
            'types' => [
                StockMovement::TYPE_PURCHASE => 'Pembelian',
                StockMovement::TYPE_SALE => 'Penjualan',
                StockMovement::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
                StockMovement::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
                StockMovement::TYPE_RETURN => 'Return',
                StockMovement::TYPE_DAMAGE => 'Barang Rusak',
                StockMovement::TYPE_CORRECTION => 'Koreksi',
            ],
            'filters' => $request->only(['from', 'to', 'type']),
        ]);
    }

    /**
     * Sync inventory from stock movements.
     */
    public function syncInventory()
    {
        $synced = $this->inventoryService->syncInventoryFromMovements();

        return redirect()->back()
            ->with('success', "Berhasil sinkronisasi {$synced} produk dengan inventory.");
    }
}
