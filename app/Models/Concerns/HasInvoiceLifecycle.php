<?php

namespace App\Models\Concerns;

use App\Models\PaymentAllocation;
use App\Support\InvoiceLifecycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Shared lifecycle for ClientInvoice and SupplierInvoice.
 *
 * state:          draft -> validated -> cancelled (once credit notes cover the whole invoice)
 * document_type:  invoice | credit_note
 * status:         payment status, always computed (unpaid, partial, paid, overdue)
 */
trait HasInvoiceLifecycle
{
    public function initializeHasInvoiceLifecycle(): void
    {
        $this->mergeFillable(['document_type', 'state', 'credited_invoice_id', 'credit_reason', 'validated_at', 'validated_by']);
        $this->mergeCasts(['validated_at' => 'datetime']);
    }

    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(static::class, 'credited_invoice_id');
    }

    /** Latest credit note of this invoice (the one that cancelled it, when it is cancelled). */
    public function creditNote(): HasOne
    {
        return $this->hasOne(static::class, 'credited_invoice_id')->latestOfMany();
    }

    /** All credit notes of this invoice: partial ones (returns, discounts) and the final one. */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(static::class, 'credited_invoice_id')->orderBy('id');
    }

    /** Column of payment_allocations pointing to this kind of invoice. */
    public function allocationColumn(): string
    {
        return $this->getTable() === 'client_invoices' ? 'client_invoice_id' : 'supplier_invoice_id';
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, $this->allocationColumn());
    }

    public function isDraft(): bool
    {
        return $this->state === InvoiceLifecycle::STATE_DRAFT;
    }

    public function isValidated(): bool
    {
        return $this->state === InvoiceLifecycle::STATE_VALIDATED;
    }

    public function isCancelled(): bool
    {
        return $this->state === InvoiceLifecycle::STATE_CANCELLED;
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === InvoiceLifecycle::TYPE_CREDIT_NOTE;
    }

    /** Validated invoices (not drafts, not cancelled, not credit notes): what the business really billed. */
    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('document_type', InvoiceLifecycle::TYPE_INVOICE)->where('state', InvoiceLifecycle::STATE_VALIDATED);
    }

    public function scopeValidatedCreditNotes(Builder $query): Builder
    {
        return $query->where('document_type', InvoiceLifecycle::TYPE_CREDIT_NOTE)->where('state', InvoiceLifecycle::STATE_VALIDATED);
    }

    /** Live invoices (draft or validated) attached to a source document; cancelled ones and credit notes are ignored. */
    public function scopeIssuedOrDraftFor(Builder $query, string $column, int $id): Builder
    {
        return $query->where($column, $id)
            ->where('document_type', InvoiceLifecycle::TYPE_INVOICE)
            ->where('state', '!=', InvoiceLifecycle::STATE_CANCELLED);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->issued()->whereIn('status', ['unpaid', 'partial', 'overdue'])->whereDate('due_date', '<', now());
    }

    /** Drafts carry a random provisional number that must never be shown as a real invoice number. */
    public function displayNumber(): string
    {
        return $this->isDraft() ? 'Brouillon #' . $this->id : (string) $this->invoice_number;
    }

    public function documentLabel(): string
    {
        return $this->isCreditNote() ? 'Avoir' : 'Facture';
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            InvoiceLifecycle::STATE_DRAFT => 'Brouillon',
            InvoiceLifecycle::STATE_VALIDATED => 'Validee',
            InvoiceLifecycle::STATE_CANCELLED => 'Annulee par avoir',
            default => (string) $this->state,
        };
    }

    public function statusLabel(): string
    {
        if ($this->isDraft() || $this->isCancelled()) {
            return '-';
        }

        if ($this->isCreditNote()) {
            return (float) $this->balance_remaining > 0 ? 'A rembourser' : 'Solde';
        }

        return match ($this->status) {
            'unpaid' => 'Non payee',
            'partial' => 'Partiellement payee',
            'paid' => 'Payee',
            'overdue' => 'En retard',
            default => (string) $this->status,
        };
    }
}
