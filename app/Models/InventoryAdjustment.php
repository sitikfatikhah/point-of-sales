<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    use HasFactory, HasFormattedTimestamps;

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'reference_type',
        'reference_id',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:2',
        'quantity_change' => 'decimal:2',
        'quantity_after' => 'decimal:2',
    ];

    // Constants for types
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';
    const TYPE_DAMAGE = 'damage';
    const TYPE_CORRECTION = 'correction';

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_IN,
            self::TYPE_OUT,
            self::TYPE_ADJUSTMENT,
            self::TYPE_PURCHASE,
            self::TYPE_SALE,
            self::TYPE_RETURN,
            self::TYPE_DAMAGE,
            self::TYPE_CORRECTION,
        ];
    }

    /**
     * Relationship to Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship to User (who made the adjustment)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related reference model (Purchase, Transaction, etc.)
     */
    public function reference()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        $modelClass = match ($this->reference_type) {
            'purchase' => Purchase::class,
            'transaction' => Transaction::class,
            'purchase_item' => PurchaseItem::class,
            default => null,
        };

        if ($modelClass) {
            return $modelClass::find($this->reference_id);
        }

        return null;
    }

    /**
     * Scope for filtering by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for filtering by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_IN => 'Barang Masuk',
            self::TYPE_OUT => 'Barang Keluar',
            self::TYPE_ADJUSTMENT => 'Penyesuaian',
            self::TYPE_PURCHASE => 'Pembelian',
            self::TYPE_SALE => 'Penjualan',
            self::TYPE_RETURN => 'Retur',
            self::TYPE_DAMAGE => 'Kerusakan',
            self::TYPE_CORRECTION => 'Koreksi',
            default => 'Unknown',
        };
    }

    /**
     * Check if this is an incoming adjustment
     */
    public function isIncoming(): bool
    {
        return in_array($this->type, [self::TYPE_IN, self::TYPE_PURCHASE, self::TYPE_RETURN]);
    }

    /**
     * Check if this is an outgoing adjustment
     */
    public function isOutgoing(): bool
    {
        return in_array($this->type, [self::TYPE_OUT, self::TYPE_SALE, self::TYPE_DAMAGE]);
    }
}
