<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryNoteItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['delivery_note_id', 'purchase_order_item_id', 'product_id', 'service_id', 'item_name', 'quantity', 'received_qty'];

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'delivery_note_id');
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
