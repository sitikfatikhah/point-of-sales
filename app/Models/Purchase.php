<?php

namespace App\Models;

use App\Traits\HasFormattedTimestamps;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFormattedTimestamps;

    protected $fillable = [
        'supplier_name',
        'purchase_date',
        'notes',
        'tax_included',
        'status',
        'reference',
    ];


    /**
     * Relasi ke item pembelian (multi-item)
     */
    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Relasi ke user yang menerima pembelian
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'received_by', 'id');
    }
}
