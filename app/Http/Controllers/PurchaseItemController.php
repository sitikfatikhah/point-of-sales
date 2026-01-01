<?php

namespace App\Http\Controllers;

use App\Models\PurchaseItem;
use Illuminate\Http\Request;

class PurchaseItemController extends Controller
{
    /**
     * Display a listing of all purchase items.
     */
    public function index()
    {
        $items = PurchaseItem::with('purchase', 'product')->latest()->paginate(10);

        return response()->json($items);
    }

    /**
     * Show the form for creating a new purchase item.
     */
    public function create()
    {
        // Biasanya tidak dipakai, karena item dibuat via PurchaseController
        return response()->json(['message' => 'Use PurchaseController to add items.']);
    }

    /**
     * Store a newly created purchase item.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'purchase_id' => 'required|exists:purchases,id',
            'product_id' => 'required|exists:prducts,id',
            'barcode' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0',
            'warehouse' => 'nullable|string',
            'batch' => 'nullable|string',
            'expired' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
        ]);
        // dd($validated);
        $item = PurchaseItem::create($validated);

        return response()->json([
            'message' => 'Purchase item created successfully.',
            'item' => $item,
        ]);
    }

    /**
     * Display the specified purchase item.
     */
    public function show(PurchaseItem $purchaseItem)
    {
        return response()->json($purchaseItem->load('purchase', 'product'));
    }

    /**
     * Show the form for editing the specified purchase item.
     */
    public function edit(PurchaseItem $purchaseItem)
    {
        // Biasanya tidak dipakai di API, tapi bisa return data
        return response()->json($purchaseItem);
    }

    /**
     * Update the specified purchase item.
     */
    public function update(Request $request, PurchaseItem $purchaseItem)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:prducts,id',
            'barcode' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'total_price' => 'required|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0',
            'warehouse' => 'nullable|string',
            'batch' => 'nullable|string',
            'expired' => 'nullable|date',
            'currency' => 'nullable|string|max:3',
        ]);

        $purchaseItem->update($validated);

        return response()->json([
            'message' => 'Purchase item updated successfully.',
            'item' => $purchaseItem,
        ]);
    }


    /**
     * Remove the specified purchase item from storage.
     */
    public function destroy(PurchaseItem $purchaseItem)
    {
        $purchaseItem->delete();

        return response()->json(['message' => 'Purchase item deleted successfully.']);
    }
}
