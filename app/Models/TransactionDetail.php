<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;
    
    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id', 'product_id', 'quantity','discount', 'price' // ubah barcode jadi product_id
    ];

    /**
     * Transaction relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Product relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    // TransactionDetail Model
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_transaction_detail', 'transaction_detail_id', 'product_id');
    }

}
