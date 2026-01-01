<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::query()
            ->with('items')
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_percent', 'total_price')
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dashboard/Purchase/Index', [
            'purchases' => $purchases,
        ]);
    }

    public function create()
    {
        // Supaya bisa cari produk berdasarkan barcode di React
        $products = \App\Models\Product::all();

        return Inertia::render('Dashboard/Purchase/Create', [
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());

        $request->validate([
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
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $purchase = Purchase::create([
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'status' => $request->status ?? 'received',
                'notes' => $request->notes,
                'tax_included' => $request->tax_included ?? false,
                'reference' => $request->reference,
            ]);

            foreach ($request->items as $item) {
                $purchase->items()->create([
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

            DB::commit();

            return to_route('purchase.index')
                ->with('success', 'Purchase created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal menyimpan pembelian: ' . $e->getMessage()]);
        }
    }

    public function edit(Purchase $purchase)
    {
        $products = \App\Models\Product::all();

        return Inertia::render('Dashboard/Purchase/Edit', [
            'purchase' => $purchase->load('items'),
            'products' => $products,
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $request->validate([
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
            'items.*.tax_percent' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $purchase->update([
                'supplier_name' => $request->supplier_name,
                'purchase_date' => $request->purchase_date,
                'status' => $request->status ?? 'received',
                'notes' => $request->notes,
                'tax_included' => $request->tax_included ?? false,
                'reference' => $request->reference,
            ]);

            // hapus semua item lama
            $purchase->items()->delete();

            // simpan ulang item
            foreach ($request->items as $item) {
                $purchase->items()->create([
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

            DB::commit();

            return to_route('purchase.index')
                ->with('success', 'Purchase updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal mengupdate pembelian: ' . $e->getMessage()]);
        }
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->invoice) {
            Storage::delete('public/purchases/' . $purchase->invoice);
        }

        $purchase->delete();

        return to_route('purchase.index')
            ->with('success', 'Purchase deleted successfully.');
    }
}
