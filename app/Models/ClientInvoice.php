<?php

namespace App\Models;

use App\Models\Concerns\HasInvoiceLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientInvoice extends Model
{
    use HasFactory, SoftDeletes, HasInvoiceLifecycle;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'sales_order_id',
        'delivery_id',
        'sales_quote_id',
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

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount_ht' => 'decimal:2',
        'tva_amount' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'client_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class, 'delivery_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientInvoiceItem::class, 'invoice_id');
    }

    /** Quote cashed in directly (simple mode: quote -> paid invoice, no order nor delivery). */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class, 'sales_quote_id');
    }

}
