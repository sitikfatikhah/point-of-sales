<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode',
        'stock',
        'purchase_price',
        'sale_price',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
