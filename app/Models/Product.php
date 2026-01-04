<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, HasFormattedTimestamps;

    /**
     * fillable
     *
     * Note: buy_price tidak lagi dimasukkan karena dihitung otomatis dari average pembelian
     *
     * @var array
     */
    protected $fillable = [
        'image', 'barcode', 'title', 'description', 'sell_price', 'category_id', 'stock'
    ];

    /**
     * Appended attributes
     */
    protected $appends = ['average_buy_price', 'current_stock'];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * category
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    // Purchase items for this product
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class, 'product_id', 'id');
    }

    // Inventory for this product
    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    // Inventory adjustments for this product
    public function inventoryAdjustments()
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    // Stock movements for this product
    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // Transaction details for this product (hasMany for aggregation)
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class, 'product_id', 'id');
    }

    // Semua transaction melalui transaction_details
    public function transactions()
    {
        return $this->hasManyThrough(
            Transaction::class,          // Model akhir
            TransactionDetail::class,    // Pivot
            'product_id',                // FK di TransactionDetail menuju Product
            'id',                        // PK di Transaction
            'id',                        // PK di Product
            'transaction_id'             // FK di TransactionDetail menuju Transaction
        );
    }

    // ==========================================
    // ACCESSORS - CALCULATED FIELDS
    // ==========================================

    /**
     * Get average buy price from StockMovement ledger (purchase records)
     * Formula: Total purchase amount / Total purchase quantity
     *
     * @return Attribute
     */
    protected function averageBuyPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => StockMovement::getAverageBuyPrice($this->id)
        );
    }

    /**
     * Override buy_price getter to return average buy price
     * This ensures backward compatibility
     *
     * @return Attribute
     */
    protected function buyPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->average_buy_price ?: ($value ?? 0),
            set: fn ($value) => $value // Allow setting for initial import, but will be overridden by average
        );
    }

    /**
     * Get current stock from StockMovement ledger
     *
     * @return Attribute
     */
    protected function currentStock(): Attribute
    {
        return Attribute::make(
            get: fn () => StockMovement::getCurrentStock($this->id)
        );
    }

    /**
     * Check if product is in stock
     */
    public function isInStock(): bool
    {
        return $this->current_stock > 0;
    }

    /**
     * Check if product has enough stock for given quantity
     */
    public function hasEnoughStock(float $quantity): bool
    {
        return $this->current_stock >= $quantity;
    }

    /**
     * image
     *
     * @return Attribute
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => asset('/storage/products/' . $value),
        );
    }
}
