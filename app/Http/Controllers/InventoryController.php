<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        // Filters
        $filters = [
            'barcode' => $request->input('barcode'),
            'description' => $request->input('description'),
            'category_id' => $request->input('category_id'),
        ];

        // Product query with per-product aggregates
        $productsQuery = Product::query()
            // Menghitung total pembelian
            ->withSum('purchaseItems as purchase_quantity', 'quantity')
            ->withSum('purchaseItems as purchase_total', 'total_price')

            // Menghitung total penjualan per produk dengan diskon
            ->withSum('transactionDetails as sale_quantity', 'quantity')
            ->withSum('transactionDetails as sale_total', function ($query) {
                $query->select(DB::raw('SUM(quantity * price - (quantity * price * discount / 100))')); // Menghitung harga setelah diskon
            })

            // Urutkan berdasarkan deskripsi produk
            ->orderBy('description');



        // Apply filters
        if ($filters['barcode']) {
            $productsQuery->where('barcode', 'like', "%{$filters['barcode']}%");
        }

        if ($filters['description']) {
            $productsQuery->where('description', 'like', "%{$filters['description']}%");
        }

        if ($filters['category_id']) {
            $productsQuery->where('category_id', $filters['category_id']);
        }

        $products = $productsQuery->get();
        // dd($productsQuery);

        $products->each(function ($product) {
            $product->quantity_balance = ($product->purchase_quantity ?? 0) - ($product->sale_quantity ?? 0);
        });

        // Optional: calculate global summary totals
        $summary = [
            'purchase_quantity' => $products->sum('purchase_quantity'),
            'purchase_total' => $products->sum('purchase_total'),
            'sale_quantity' => $products->sum('sale_quantity'),
            'sale_total' => $products->sum('sale_total'),
            'quantity_balance' => $products->sum('purchase_quantity') - $products->sum('sale_quantity'),
        ];

        return Inertia::render('Dashboard/Reports/Inventories', [
            'products' => $products,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }


    public function create() {}
    public function store(Request $request) {}
    public function show($id) {}
    public function edit($id) {}
    public function update(Request $request, $id) {}
    public function destroy($id) {}
}
