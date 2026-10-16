<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseQuote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'quote_number',
        'supplier_id',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuoteItem::class, 'quote_id');
    }

    /** Supplier invoice made from this quote (simple mode). An invoiced supplier quote is locked. */
    public function invoice(): HasOne
    {
        return $this->hasOne(SupplierInvoice::class, 'purchase_quote_id')->latestOfMany();
    }

    public function order(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class, 'quote_id')->latestOfMany();
    }
}
