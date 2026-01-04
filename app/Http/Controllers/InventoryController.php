<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\StockMovement;
use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        // Filters
        $filters = [
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'stock_status' => $request->input('stock_status'), // low, out, available
        ];

        // Product query with inventory and movement data
        $productsQuery = Product::query()
            ->with(['category', 'inventory'])
            // Menghitung total pembelian dari stock movements
            ->withSum('purchaseItems as purchase_quantity', 'quantity')
            ->withSum('purchaseItems as purchase_total', 'total_price')
            // Menghitung total penjualan per produk
            ->withSum('transactionDetails as sale_quantity', 'quantity')
            // Menghitung total stock movements
            ->withCount(['stockMovements as movement_count'])
            // Urutkan berdasarkan title produk
            ->orderBy('title');

        // Apply filters
        if ($filters['search']) {
            $search = $filters['search'];
            $productsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($filters['category_id']) {
            $productsQuery->where('category_id', $filters['category_id']);
        }

        if ($filters['stock_status']) {
            switch ($filters['stock_status']) {
                case 'low':
                    $productsQuery->where('stock', '>', 0)->where('stock', '<=', 10);
                    break;
                case 'out':
                    $productsQuery->where('stock', '<=', 0);
                    break;
                case 'available':
                    $productsQuery->where('stock', '>', 10);
                    break;
            }
        }

        $products = $productsQuery->paginate(15)->withQueryString();

        // Add calculated fields (average_buy_price, current_stock) to each product
        $products->getCollection()->transform(function ($product) {
            $product->average_buy_price = StockMovement::getAverageBuyPrice($product->id);
            $product->current_stock = StockMovement::getCurrentStock($product->id);
            return $product;
        });

        // Calculate summary using StockMovement
        $totalStockValue = 0;
        $totalSellValue = 0;
        Product::chunk(100, function ($prods) use (&$totalStockValue, &$totalSellValue) {
            foreach ($prods as $prod) {
                $stock = StockMovement::getCurrentStock($prod->id);
                $avgBuyPrice = StockMovement::getAverageBuyPrice($prod->id);
                $totalStockValue += $stock * $avgBuyPrice;
                $totalSellValue += $stock * $prod->sell_price;
            }
        });

        $summary = [
            'total_products' => Product::count(),
            'total_stock' => Product::sum('stock'),
            'total_stock_value' => $totalStockValue,
            'total_sell_value' => $totalSellValue,
            'low_stock_count' => Product::where('stock', '>', 0)->where('stock', '<=', 10)->count(),
            'out_of_stock_count' => Product::where('stock', '<=', 0)->count(),
            'total_movements' => StockMovement::count(),
            'today_movements' => StockMovement::whereDate('created_at', today())->count(),
        ];

        // Get categories for filter
        $categories = Category::orderBy('name')->get(['id', 'name']);

        // Get recent stock movements
        $recentMovements = StockMovement::with(['product:id,title,barcode', 'user:id,name'])
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('Dashboard/Reports/Inventories', [
            'products' => $products,
            'filters' => $filters,
            'summary' => $summary,
            'categories' => $categories,
            'recentMovements' => $recentMovements,
            // Legacy support
            'recentAdjustments' => $recentMovements->map(function ($movement) {
                return [
                    'id' => $movement->id,
                    'product' => $movement->product,
                    'user' => $movement->user,
                    'type' => $movement->movement_type,
                    'quantity_change' => $movement->quantity,
                    'created_at' => $movement->created_at,
                ];
            }),
        ]);
    }

    /**
     * Get product detail with all inventory relations
     */
    public function show($id)
    {
        $product = Product::with([
            'category',
            'inventory',
            'purchaseItems.purchase',
            'transactionDetails.transaction',
            'stockMovements' => function ($q) {
                $q->with('user:id,name')->latest()->take(20);
            }
        ])
            ->withSum('purchaseItems as total_purchased', 'quantity')
            ->withSum('transactionDetails as total_sold', 'quantity')
            ->findOrFail($id);

        // Add calculated fields
        $product->average_buy_price = StockMovement::getAverageBuyPrice($id);
        $product->current_stock = StockMovement::getCurrentStock($id);

        // Calculate movement summary from StockMovement
        $movementSummary = [
            'total_in' => StockMovement::where('product_id', $id)
                ->incoming()
                ->sum('quantity'),
            'total_out' => abs(StockMovement::where('product_id', $id)
                ->outgoing()
                ->sum('quantity')),
            'total_corrections' => StockMovement::where('product_id', $id)
                ->where('movement_type', StockMovement::TYPE_CORRECTION)
                ->count(),
            'average_buy_price' => $product->average_buy_price,
            'current_stock' => $product->current_stock,
        ];

        return response()->json([
            'product' => $product,
            'movementSummary' => $movementSummary,
        ]);
    }

    public function create() {}
    public function store(Request $request) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
