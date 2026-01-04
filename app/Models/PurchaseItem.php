<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseItem extends Model
{
    use HasFactory, HasFormattedTimestamps;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'barcode',
        'quantity',
        'purchase_price',
        'total_price',
        'tax_percent',
        'discount_percent',
        'warehouse',
        'batch',
        'expired',
        'currency',
    ];

    // Relasi ke Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relasi ke Purchase
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
