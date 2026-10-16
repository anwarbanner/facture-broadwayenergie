<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['invoice_id', 'credited_item_id', 'delivery_item_id', 'product_id', 'service_id', 'item_name', 'quantity', 'price_ht', 'tva_rate', 'total_ht'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Products::class, 'product_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
