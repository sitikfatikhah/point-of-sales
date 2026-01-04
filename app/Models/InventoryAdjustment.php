<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * InventoryAdjustment Model
 *
 * Model ini HANYA untuk pencatatan adjustment manual dengan nomor jurnal.
 * Data purchase dan sale TIDAK dicatat di sini, melainkan di StockMovement.
 */
class InventoryAdjustment extends Model
{
    use HasFactory, HasFormattedTimestamps;

    protected $fillable = [
        'journal_number',
        'product_id',
        'user_id',
        'type',
        'quantity_change',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:2',
    ];

    // Constants for types (only manual adjustment types)
    const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    const TYPE_RETURN = 'return';
    const TYPE_DAMAGE = 'damage';
    const TYPE_CORRECTION = 'correction';

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_ADJUSTMENT_OUT,
            self::TYPE_RETURN,
            self::TYPE_DAMAGE,
            self::TYPE_CORRECTION,
        ];
    }

    /**
     * Get types that increase stock
     */
    public static function getIncomingTypes(): array
    {
        return [
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_RETURN,
        ];
    }

    /**
     * Get types that decrease stock
     */
    public static function getOutgoingTypes(): array
    {
        return [
            self::TYPE_ADJUSTMENT_OUT,
            self::TYPE_DAMAGE,
        ];
    }

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
     * Relationship to User (who made the adjustment)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get related stock movement
     */
    public function stockMovement()
    {
        return $this->hasOne(StockMovement::class, 'reference_id')
            ->where('reference_type', 'adjustment');
    }

    // ==========================================
    // SCOPES
    // ==========================================

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
     * Scope for only records with journal number
     */
    public function scopeWithJournal($query)
    {
        return $query->whereNotNull('journal_number');
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Generate journal number for adjustment
     */
    public static function generateJournalNumber(): string
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        $lastAdjustment = self::withJournal()
            ->whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAdjustment && preg_match('/ADJ' . $date . '(\d{4})/', $lastAdjustment->journal_number, $matches)) {
            $sequence = str_pad((int)$matches[1] + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $sequence = '0001';
        }

        return "{$prefix}{$date}{$sequence}";
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Check if this is an incoming adjustment
     */
    public function isIncoming(): bool
    {
        return in_array($this->type, self::getIncomingTypes());
    }

    /**
     * Check if this is an outgoing adjustment
     */
    public function isOutgoing(): bool
    {
        return in_array($this->type, self::getOutgoingTypes());
    }

    /**
     * Get type label in Indonesian
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
            self::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
            self::TYPE_RETURN => 'Return Barang',
            self::TYPE_DAMAGE => 'Barang Rusak',
            self::TYPE_CORRECTION => 'Koreksi Stok',
            default => $this->type,
        };
    }
}
