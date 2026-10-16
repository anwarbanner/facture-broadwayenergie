<?php

namespace App\Models;

use App\Models\Concerns\HasInvoiceLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierInvoice extends Model
{
    use HasFactory, SoftDeletes, HasInvoiceLifecycle;

    protected $fillable = [
        'invoice_number',
        'supplier_id',
        'purchase_order_id',
        'delivery_note_id',
        'purchase_quote_id',
        'invoice_date',
        'due_date',
        'amount_ht',
        'tva_amount',
        'total_ttc',
        'tva_rate',
        'amount_paid',
        'balance_remaining',
        'status',
        'payment_terms',
        'currency',
        'exchange_rate',
        'created_by',
        'updated_by',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    /** Supplier quote invoiced in one click (simple mode). */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'purchase_quote_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class, 'delivery_note_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class, 'invoice_id');
    }

}
