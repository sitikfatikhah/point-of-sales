<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Inventory Model
 *
 * Model ini menyimpan data inventory per produk.
 * Stock balance dihitung dari StockMovement ledger.
 */
class Inventory extends Model
{
    use HasFactory, HasFormattedTimestamps;

    protected $fillable = [
        'product_id',
        'barcode',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relationship to Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all stock movements for this inventory's product
     */
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'product_id', 'product_id');
    }

    /**
     * Get all adjustments for this inventory's product (legacy support)
     */
    public function adjustments()
    {
        return $this->hasMany(InventoryAdjustment::class, 'product_id', 'product_id');
    }

    // ==========================================
    // STOCK CALCULATION FROM STOCK MOVEMENTS
    // ==========================================

    /**
     * Get current stock balance from StockMovement ledger
     */
    public function getStockBalanceAttribute(): float
    {
        return StockMovement::getCurrentStock($this->product_id);
    }

    /**
     * Sync quantity with StockMovement ledger
     */
    public function syncQuantityFromMovements(): void
    {
        $this->quantity = StockMovement::getCurrentStock($this->product_id);
        $this->save();

        // Also update product stock
        if ($this->product) {
            $this->product->stock = $this->quantity;
            $this->product->save();
        }
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Get or create inventory for a product
     */
    public static function getOrCreateForProduct(Product $product): self
    {
        return self::firstOrCreate(
            ['product_id' => $product->id],
            [
                'barcode' => $product->barcode,
                'quantity' => $product->stock ?? 0,
            ]
        );
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope for low stock
     */
    public function scopeLowStock($query, int $threshold = 10)
    {
        return $query->where('quantity', '<=', $threshold)->where('quantity', '>', 0);
    }

    /**
     * Scope for out of stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    /**
     * Scope for available stock
     */
    public function scopeAvailable($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
