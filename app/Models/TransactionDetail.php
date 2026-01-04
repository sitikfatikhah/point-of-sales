<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory, HasFormattedTimestamps;

    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id', 'product_id', 'barcode', 'quantity', 'discount', 'price'
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
     * Product relationship (belongsTo)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Products relationship (many-to-many)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_transaction_detail', 'transaction_detail_id', 'product_id');
    }

}
