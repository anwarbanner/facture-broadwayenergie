<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'delivery_number',
        'sales_order_id',
        'sales_quote_id',
        'client_id',
        'delivery_date',
        'shipping_address',
        'status',
        'moves_stock',
        'follows_quote',
        'tracking_number',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'moves_stock' => 'boolean',
        'follows_quote' => 'boolean',
    ];

    /** Simple mode BL made from a quote: printed only, never moves stock, follows the quote lines. */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class, 'sales_quote_id');
    }

    public function isFromQuote(): bool
    {
        return $this->sales_quote_id !== null;
    }

    /** Simple mode BL (from a quote or typed by hand): no sales order, edited with the simple BL form. */
    public function isSimple(): bool
    {
        return $this->sales_order_id === null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'client_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class, 'delivery_id');
    }

    public function clientInvoice(): HasOne
    {
        return $this->hasOne(ClientInvoice::class, 'delivery_id')->ofMany(['id' => 'max'], function ($query) {
            $query->where('document_type', 'invoice')->where('state', '!=', 'cancelled');
        });
    }
}
