<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * InventoryService
 *
 * Service untuk mengelola pergerakan stok.
 * Semua pergerakan stok (purchase, sale, adjustment) dicatat di StockMovement.
 */
class InventoryService
{
    // ==========================================
    // PURCHASE PROCESSING
    // ==========================================

    /**
     * Process inventory for a purchase (increase stock)
     * Mencatat ke StockMovement ledger
     */
    public static function record(
        int $productId,
        string $type,
        float $qty,
        ?string $note = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $userId = null
    ): void {
        DB::transaction(function () use (
            $productId,
            $type,
            $qty,
            $note,
            $referenceType,
            $referenceId,
            $userId
        ) {
            // Lock and get the product
            $product = Product::lockForUpdate()->findOrFail($productId);
            $currentStock = StockMovement::getCurrentStock($productId);

            // Create stock movement record for purchase
            StockMovement::create([
                'product_id' => $productId,
                'user_id' => $userId ?? auth()->id(),
                'movement_type' => StockMovement::TYPE_PURCHASE,
                'reference_type' => $referenceType ?? 'purchase',
                'reference_id' => $referenceId,
                'quantity' => $qty,
                'unit_price' => 0,  // Assuming no unit price is defined in the `record()` method, you can add a dynamic price if needed
                'total_price' => 0,  // Same for total price
                'quantity_before' => $currentStock,
                'quantity_after' => $currentStock + $qty,
                'notes' => $note,
            ]);

            // Update product stock
            $product->increment('stock', $qty);

            // Update inventory (adjusting stock after purchase)
            $inventory = Inventory::getOrCreateForProduct($product);
            $inventory->quantity = $currentStock + $qty;
            $inventory->save();
        });
    }

    /**
     * JURNAL PEMBELIAN (HIGH LEVEL)
     */
    public function processPurchase(Purchase $purchase): void
    {
        foreach ($purchase->items as $item) {
            if (!$item->product_id) {
                continue;
            }

            self::record(
                productId: $item->product_id,
                type: 'purchase',
                qty: $item->quantity,
                note: "Purchase from {$purchase->supplier_name}",
                referenceType: 'purchase',
                referenceId: $purchase->id,
                userId: auth()->id()
            );
        }
    }

    /**
     * REVERSE JURNAL PEMBELIAN (UNTUK UPDATE / DELETE)
     */
    public function reversePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                if (!$item->product_id) {
                    continue;
                }

                $product = $item->product;
                $currentStock = StockMovement::getCurrentStock($item->product_id);

                // Create correction movement for purchase reversal
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'user_id' => auth()->id(),
                    'movement_type' => StockMovement::TYPE_CORRECTION,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'quantity' => -$item->quantity,
                    'unit_price' => $item->purchase_price,
                    'total_price' => -$item->total_price,
                    'quantity_before' => $currentStock,
                    'quantity_after' => max(0, $currentStock - $item->quantity),
                    'notes' => "Reversed purchase from {$purchase->supplier_name}",
                ]);

                // Update product stock after reversal
                $newStock = max(0, $product->stock - $item->quantity);
                $product->stock = $newStock;
                $product->save();

                // Update inventory
                $inventory = Inventory::where('product_id', $item->product_id)->first();
                if ($inventory) {
                    $inventory->quantity = $newStock;
                    $inventory->save();
                }
            }
        });
    }

    // ==========================================
    // TRANSACTION/SALE PROCESSING
    // ==========================================

    /**
     * Validate stock before creating transaction
     * Returns array with 'valid' boolean and 'errors' array
     */
    public function validateStockForTransaction(array $cartItems): array
    {
        $errors = [];

        foreach ($cartItems as $item) {
            // Handle both object and array formats
            if (is_object($item)) {
                $productId = $item->product_id ?? null;
                $product = $item->product ?? ($productId ? Product::find($productId) : null);
                $requestedQty = $item->quantity;
            } else {
                $productId = $item['product_id'] ?? null;
                $product = $productId ? Product::find($productId) : null;
                $requestedQty = $item['quantity'] ?? 0;
            }

            if (!$product) continue;

            $currentStock = StockMovement::getCurrentStock($product->id);

            if ($currentStock <= 0) {
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'message' => "Stok produk '{$product->title}' habis. Tidak dapat melakukan transaksi.",
                    'available' => $currentStock,
                    'requested' => $requestedQty,
                ];
            } elseif ($currentStock < $requestedQty) {
                $errors[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->title,
                    'message' => "Stok produk '{$product->title}' tidak mencukupi. Stok tersedia: {$currentStock}, diminta: {$requestedQty}",
                    'available' => $currentStock,
                    'requested' => $requestedQty,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate stock and throw exception if insufficient
     * Use this when you want to halt execution on validation failure
     */
    public function validateStockOrFail(array $cartItems): void
    {
        $result = $this->validateStockForTransaction($cartItems);

        if (!$result['valid']) {
            throw new Exception($result['errors'][0]['message']);
        }
    }

    /**
     * Proses transaksi penjualan (OUT stok)
     */
    public function processTransaction($transaction): void
    {
        $transaction->load('details.product');  // Load related products

        foreach ($transaction->details as $detail) {
            $product = $detail->product;
            $currentStock = StockMovement::getCurrentStock($detail->product_id);

            // Create stock movement record for sale
            StockMovement::create([
                'product_id' => $detail->product_id,
                'user_id' => auth()->id(),
                'movement_type' => StockMovement::TYPE_SALE,
                'reference_type' => 'transaction',
                'reference_id' => $transaction->id,
                'quantity' => -$detail->quantity, // Negative for outgoing
                'unit_price' => $detail->price / $detail->quantity,
                'total_price' => $detail->price,
                'quantity_before' => $currentStock,
                'quantity_after' => max(0, $currentStock - $detail->quantity),
                'notes' => "Sale invoice: {$transaction->invoice}",
            ]);

            // Update product stock
            $newStock = max(0, $product->stock - $detail->quantity);
            $product->stock = $newStock;
            $product->save();

            // Update inventory
            $inventory = Inventory::where('product_id', $detail->product_id)->first();
            if ($inventory) {
                $inventory->quantity = $newStock;
                $inventory->save();
            }

            // Record the transaction
            self::record(
                productId: $detail->product_id,
                type: 'sale',
                qty: $detail->quantity,
                note: 'Sale #' . $transaction->invoice,
                referenceType: 'transaction',
                referenceId: $transaction->id,
                userId: auth()->id()
            );
        }
    }

    public function reverseTransaction($transaction): void
    {
        $transaction->load('details.product');

        foreach ($transaction->details as $detail) {
            $product = $detail->product;
            $currentStock = StockMovement::getCurrentStock($detail->product_id);

            // Create return movement for transaction reversal
            StockMovement::create([
                'product_id' => $detail->product_id,
                'user_id' => auth()->id(),
                'movement_type' => StockMovement::TYPE_RETURN,
                'reference_type' => 'transaction',
                'reference_id' => $transaction->id,
                'quantity' => $detail->quantity, // Positive for incoming
                'unit_price' => $detail->price / $detail->quantity,
                'total_price' => $detail->price,
                'quantity_before' => $currentStock,
                'quantity_after' => $currentStock + $detail->quantity,
                'notes' => "Return for invoice: {$transaction->invoice}",
            ]);

            // Update product stock
            $product->increment('stock', $detail->quantity);

            // Update inventory
            $inventory = Inventory::where('product_id', $detail->product_id)->first();
            if ($inventory) {
                $inventory->quantity = $currentStock + $detail->quantity;
                $inventory->save();
            }
        }
    }

    // ==========================================
    // MANUAL ADJUSTMENT PROCESSING
    // ==========================================

    /**
     * Create manual stock adjustment with journal number
     * This creates both InventoryAdjustment and StockMovement records
     */
    public function createAdjustment(
        Product $product,
        float $quantity,
        string $type,
        ?string $reason = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($product, $quantity, $type, $reason, $notes, $userId) {
            $currentStock = StockMovement::getCurrentStock($product->id);
            $journalNumber = InventoryAdjustment::generateJournalNumber();

            // Determine movement type and quantity sign
            $isIncoming = in_array($type, InventoryAdjustment::getIncomingTypes());
            $movementType = $isIncoming ? StockMovement::TYPE_ADJUSTMENT_IN : StockMovement::TYPE_ADJUSTMENT_OUT;
            $quantityChange = $isIncoming ? abs($quantity) : -abs($quantity);
            $newStock = $isIncoming ? $currentStock + abs($quantity) : max(0, $currentStock - abs($quantity));

            // Create inventory adjustment record (for journal)
            $adjustment = InventoryAdjustment::create([
                'journal_number' => $journalNumber,
                'product_id' => $product->id,
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'quantity_change' => $quantityChange,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            // Create stock movement record (for ledger)
            $stockMovement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $userId ?? auth()->id(),
                'movement_type' => $movementType,
                'reference_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'quantity' => $quantityChange,
                'unit_price' => 0, // No price for adjustment
                'total_price' => 0,
                'quantity_before' => $currentStock,
                'quantity_after' => $newStock,
                'journal_number' => $journalNumber,
                'notes' => $reason ?? $notes,
            ]);

            // Update product stock
            $product->stock = $newStock;
            $product->save();

            // Update inventory
            $inventory = Inventory::getOrCreateForProduct($product);
            $inventory->quantity = $newStock;
            $inventory->save();

            return [
                'adjustment' => $adjustment,
                'movement' => $stockMovement,
            ];
        });
    }

    /**
     * Stock correction (set to specific quantity)
     */
    public function stockCorrection(
        Product $product,
        float $newQuantity,
        ?string $reason = null,
        ?int $userId = null
    ): array {
        $currentStock = StockMovement::getCurrentStock($product->id);
        $difference = $newQuantity - $currentStock;

        // Tentukan tipe berdasarkan apakah menambah atau mengurangi
        $type = $difference >= 0
            ? InventoryAdjustment::TYPE_ADJUSTMENT_IN  // Stok naik
            : InventoryAdjustment::TYPE_ADJUSTMENT_OUT; // Stok turun

        return $this->createAdjustment(
            $product,
            abs($difference),
            $type,
            $reason ?? "Stock correction: set from {$currentStock} to {$newQuantity}",
            null,
            $userId
        );
    }

    // ==========================================
    // STOCK QUERIES
    // ==========================================

    /**
     * Ringkasan inventory (DASHBOARD)
     * ⚠️ Menggunakan saldo akhir produk, BUKAN jurnal
     */
    public function getStockHistory(Product $product, ?string $from = null, ?string $to = null)
    {
        $query = StockMovement::forProduct($product->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($from && $to) {
            $query->dateRange($from, $to);
        }

        return $query->get();
    }

    /**
     * Get inventory summary
     */
    public function getInventorySummary(): array
    {
        $totalStockValue = 0;
        $totalSellValue = 0;

        Product::chunk(100, function ($products) use (&$totalStockValue, &$totalSellValue) {
            foreach ($products as $product) {
                $stock = StockMovement::getCurrentStock($product->id);
                $buyPrice = StockMovement::getAverageBuyPrice($product->id);
                $totalStockValue += $stock * $buyPrice;
                $totalSellValue += $stock * $product->sell_price;
            }
        });

        return [
            'total_products' => Product::count(),
            'total_stock_value' => $totalStockValue,
            'total_sell_value' => $totalSellValue,
            'low_stock_count' => Product::where('stock', '<=', 10)->where('stock', '>', 0)->count(),
            'out_of_stock_count' => Product::where('stock', '<=', 0)->count(),
        ];
    }

    /**
     * Sync inventory with stock movements
     */
    public function syncInventoryFromMovements(): int
    {
        $synced = 0;

        Product::chunk(100, function ($products) use (&$synced) {
            foreach ($products as $product) {
                $currentStock = StockMovement::getCurrentStock($product->id);

                $inventory = Inventory::firstOrNew(['product_id' => $product->id]);
                $inventory->barcode = $product->barcode;
                $inventory->quantity = $currentStock;
                $inventory->save();

                $product->stock = $currentStock;
                $product->save();

                $synced++;
            }
        });

        return $synced;
    }

    /**
     * Legacy method for backward compatibility
     */
    public function syncInventoryWithProducts(): int
    {
        return $this->syncInventoryFromMovements();
    }
}
