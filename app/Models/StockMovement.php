<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * StockMovement Model
 *
 * Ledger utama untuk pencatatan semua pergerakan stok:
 * - Purchase: Barang masuk dari pembelian
 * - Sale: Barang keluar dari penjualan
 * - Adjustment In/Out: Koreksi stok manual dengan nomor jurnal
 * - Return: Barang kembali
 * - Damage: Barang rusak
 * - Correction: Koreksi sistem
 */
class StockMovement extends Model
{
    use HasFactory, HasFormattedTimestamps;

    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'user_id',
        'movement_type',
        'reference_type',
        'reference_id',
        'quantity',
        'unit_price',
        'total_price',
        'quantity_before',
        'quantity_after',
        'journal_number',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
    ];

    // Movement Type Constants
    const TYPE_PURCHASE = 'purchase';
    const TYPE_SALE = 'sale';
    const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    const TYPE_RETURN = 'return';
    const TYPE_DAMAGE = 'damage';
    const TYPE_CORRECTION = 'correction';

    /**
     * Get all available movement types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PURCHASE,
            self::TYPE_SALE,
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
            self::TYPE_PURCHASE,
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
            self::TYPE_SALE,
            self::TYPE_ADJUSTMENT_OUT,
            self::TYPE_DAMAGE,
        ];
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relasi ke User (yang melakukan movement)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related reference model (Purchase, Transaction, InventoryAdjustment)
     */
    public function getReference()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        $modelClass = match ($this->reference_type) {
            'purchase' => Purchase::class,
            'transaction' => Transaction::class,
            'adjustment' => InventoryAdjustment::class,
            default => null,
        };

        if ($modelClass) {
            return $modelClass::find($this->reference_id);
        }

        return null;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    /**
     * Scope for filtering by movement type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    /**
     * Scope for filtering incoming movements (stock in)
     */
    public function scopeIncoming($query)
    {
        return $query->whereIn('movement_type', self::getIncomingTypes());
    }

    /**
     * Scope for filtering outgoing movements (stock out)
     */
    public function scopeOutgoing($query)
    {
        return $query->whereIn('movement_type', self::getOutgoingTypes());
    }

    /**
     * Scope for filtering by product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for filtering by reference
     */
    public function scopeForReference($query, string $referenceType, int $referenceId)
    {
        return $query->where('reference_type', $referenceType)
                     ->where('reference_id', $referenceId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope for filtering by journal number
     */
    public function scopeWithJournal($query)
    {
        return $query->whereNotNull('journal_number');
    }

    /**
     * Scope for purchase movements only
     */
    public function scopePurchases($query)
    {
        return $query->where('movement_type', self::TYPE_PURCHASE);
    }

    /**
     * Scope for sale movements only
     */
    public function scopeSales($query)
    {
        return $query->where('movement_type', self::TYPE_SALE);
    }

    // ==========================================
    // STATIC METHODS
    // ==========================================

    /**
     * Get current stock for a product from stock movements
     */
    public static function getCurrentStock(int $productId): float
    {
        $lastMovement = self::forProduct($productId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastMovement ? (float) $lastMovement->quantity_after : 0;
    }

    /**
     * Calculate average buy price for a product
     * Formula: Total purchase amount / Total purchase quantity
     */
    public static function getAverageBuyPrice(int $productId): float
    {
        $purchaseData = self::forProduct($productId)
            ->purchases()
            ->selectRaw('SUM(total_price) as total_amount, SUM(quantity) as total_quantity')
            ->first();

        if (!$purchaseData || $purchaseData->total_quantity == 0) {
            return 0;
        }

        return round($purchaseData->total_amount / $purchaseData->total_quantity, 2);
    }

    /**
     * Generate journal number for manual adjustments
     */
    public static function generateJournalNumber(): string
    {
        $prefix = 'ADJ';
        $date = now()->format('Ymd');
        $lastJournal = self::withJournal()
            ->whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastJournal && preg_match('/ADJ' . $date . '(\d{4})/', $lastJournal->journal_number, $matches)) {
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
     * Check if this is an incoming movement
     */
    public function isIncoming(): bool
    {
        return in_array($this->movement_type, self::getIncomingTypes());
    }

    /**
     * Check if this is an outgoing movement
     */
    public function isOutgoing(): bool
    {
        return in_array($this->movement_type, self::getOutgoingTypes());
    }

    /**
     * Get movement type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->movement_type) {
            self::TYPE_PURCHASE => 'Pembelian',
            self::TYPE_SALE => 'Penjualan',
            self::TYPE_ADJUSTMENT_IN => 'Adjustment Masuk',
            self::TYPE_ADJUSTMENT_OUT => 'Adjustment Keluar',
            self::TYPE_RETURN => 'Return',
            self::TYPE_DAMAGE => 'Barang Rusak',
            self::TYPE_CORRECTION => 'Koreksi',
            default => $this->movement_type,
        };
    }
}
