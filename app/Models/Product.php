<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    
    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'image', 'barcode', 'title', 'description', 'buy_price', 'sell_price', 'category_id', 'stock'
    ];

    /**
     * category
     *
     * @return void
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
    public function purchaseItems() {
    return $this->hasMany(PurchaseItem::class, 'product_id', 'id');
    }

    // Product Model
    public function transactionDetails()
    {
        return $this->belongsToMany(TransactionDetail::class, 'product_transaction_detail', 'product_id', 'transaction_detail_id');
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
