<?php

namespace App\Models;

use Carbon\Carbon;
use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Profit extends Model
{
    use HasFactory, HasFormattedTimestamps;

    /**
     * fillable
     *
     * @var array
     */
    protected $fillable = [
        'transaction_id', 'total'
    ];

    /**
     * transaction
     *
     * @return void
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
