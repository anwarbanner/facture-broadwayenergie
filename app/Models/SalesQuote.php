<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesQuote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_number',
        'client_id',
        'project_id',
        'date',
        'validity_date',
        'status',
        'total_ht',
        'total_tva',
        'total_ttc',
        'tva_rate',
        'currency',
        'exchange_rate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'validity_date' => 'date',
        'total_ht' => 'decimal:2',
        'total_tva' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'client_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesQuoteItem::class, 'quote_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(SalesOrder::class, 'quote_id')->latestOfMany();
    }

    /** Bon de livraison made from the quote (simple mode, no stock movement). */
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'sales_quote_id')->latestOfMany();
    }

    /** Invoice made from the quote (simple mode: "Faire la facture" / "Encaisser"). An invoiced quote is locked. */
    public function invoice(): HasOne
    {
        return $this->hasOne(ClientInvoice::class, 'sales_quote_id')->latestOfMany();
    }

    /** Simple mode lists: "Encaisse" once its invoice is fully paid, "Facture" while it waits for the payment. */
    public function simpleStatusBadge(): \Illuminate\Support\HtmlString
    {
        if (!$this->invoice) {
            return \App\Support\Format::statusBadge($this->status);
        }

        return round((float) $this->invoice->balance_remaining, 2) <= 0
            ? \App\Support\Format::statusBadge('paid', 'Encaisse')
            : \App\Support\Format::statusBadge('unpaid', 'Facture');
    }

    /** "Encaisser" is still offered: not paid yet, and not handled through a sales order. */
    public function canBeCashedIn(): bool
    {
        return !$this->order && $this->status !== 'rejected'
            && (!$this->invoice || round((float) $this->invoice->balance_remaining, 2) > 0);
    }
}
