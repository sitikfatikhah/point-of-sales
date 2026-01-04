<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory, HasFormattedTimestamps;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'cashier_id', 'product_id', 'barcode', 'quantity', 'price'
    ];

    /**
     * product
     *
     * @return void
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
