<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'client_invoice_id', 'supplier_invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function clientInvoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function invoice(): ClientInvoice|SupplierInvoice|null
    {
        return $this->clientInvoice ?? $this->supplierInvoice;
    }
}
