<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseQuoteItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['quote_id', 'product_id', 'service_id', 'item_name', 'quantity', 'price_ht', 'tva_rate', 'total_ht'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'quote_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id')->withDefault();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id')->withDefault();
    }
}
