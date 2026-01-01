<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductTransactionDetail extends Model
{
    use HasFactory;

    /**
     * Nama tabel (karena tidak mengikuti konvensi Laravel)
     */
    protected $table = 'product_transaction_detail';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'transaction_detail_id',
        'product_id',
        'quantity',
        'price',
        'discount',
    ];

    /**
     * Jika tabel TIDAK punya timestamps
     */
    public $timestamps = false;

    /**
     * Relasi ke TransactionDetail
     */
    public function transactionDetail()
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    /**
     * Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
