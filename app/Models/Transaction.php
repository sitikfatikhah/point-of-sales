<?php

namespace App\Models;

use Carbon\Carbon;
use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory, HasFormattedTimestamps;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'cashier_id',
        'customer_id',
        'invoice',
        'cash',
        'change',
        'discount',
        'grand_total',
        'payment_method',
        'payment_status',
        'payment_reference',
        'payment_url',
    ];

    /**
     * details
     *
     * @return void
     */
    public function details()
    {
        return $this->hasMany(TransactionDetail::class);
    }

    /**
     * customer
     *
     * @return void
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function transactionDetails()
    {
        return $this->belongsTo(TransactionDetail::class);
    }

    /**
     * cashier
     *
     * @return void
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * profits
     *
     * @return void
     */
    public function profits()
    {
        return $this->hasMany(Profit::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
