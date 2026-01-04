<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /**
     * Relationship to Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all adjustments for this inventory's product
     */
    public function adjustments()
    {
        return $this->hasMany(InventoryAdjustment::class, 'product_id', 'product_id');
    }

    /**
     * Add stock with adjustment tracking
     */
    public function addStock(float $quantity, string $type = 'in', ?string $reason = null, ?string $referenceType = null, ?int $referenceId = null, ?int $userId = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($quantity, $type, $reason, $referenceType, $referenceId, $userId) {
            $quantityBefore = $this->quantity;
            $this->quantity += $quantity;
            $this->save();

            // Also update product stock
            if ($this->product) {
                $this->product->increment('stock', $quantity);
            }

            return InventoryAdjustment::create([
                'product_id' => $this->product_id,
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'quantity_before' => $quantityBefore,
                'quantity_change' => $quantity,
                'quantity_after' => $this->quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Reduce stock with adjustment tracking
     */
    public function reduceStock(float $quantity, string $type = 'out', ?string $reason = null, ?string $referenceType = null, ?int $referenceId = null, ?int $userId = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($quantity, $type, $reason, $referenceType, $referenceId, $userId) {
            $quantityBefore = $this->quantity;

            // Prevent stock from going below zero
            $actualReduction = min($quantity, $this->quantity);
            $this->quantity = max(0, $this->quantity - $quantity);
            $this->save();

            // Also update product stock
            if ($this->product) {
                $newProductStock = max(0, $this->product->stock - $quantity);
                $this->product->stock = $newProductStock;
                $this->product->save();
            }

            return InventoryAdjustment::create([
                'product_id' => $this->product_id,
                'user_id' => $userId ?? auth()->id(),
                'type' => $type,
                'quantity_before' => $quantityBefore,
                'quantity_change' => -$quantity,
                'quantity_after' => $this->quantity,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Set stock to specific quantity with adjustment tracking
     */
    public function setStock(float $newQuantity, ?string $reason = null, ?int $userId = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($newQuantity, $reason, $userId) {
            $quantityBefore = $this->quantity;
            $quantityChange = $newQuantity - $quantityBefore;
            $this->quantity = $newQuantity;
            $this->save();

            // Also update product stock
            if ($this->product) {
                $this->product->stock = $newQuantity;
                $this->product->save();
            }

            return InventoryAdjustment::create([
                'product_id' => $this->product_id,
                'user_id' => $userId ?? auth()->id(),
                'type' => InventoryAdjustment::TYPE_CORRECTION,
                'quantity_before' => $quantityBefore,
                'quantity_change' => $quantityChange,
                'quantity_after' => $newQuantity,
                'reason' => $reason ?? 'Stock correction',
            ]);
        });
    }

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

    /**
     * Scope for low stock
     */
    public function scopeLowStock($query, int $threshold = 10)
    {
        return $query->where('quantity', '<=', $threshold);
    }

    /**
     * Scope for out of stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }
}

