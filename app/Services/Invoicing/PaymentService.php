<?php

namespace App\Services\Invoicing;

use App\Models\ClientInvoice;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SupplierInvoice;
use App\Support\FinancialHelper;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments and refunds.
 *
 * - A payment is money in or out for one contact, allocated to one or more validated documents.
 * - Settling an invoice and refunding a credit note are both payments, in opposite directions.
 * - invoice.amount_paid / balance_remaining / status are caches rebuilt from active allocations.
 * - Payments are never deleted: a wrong one is voided and the documents are recomputed.
 */
class PaymentService
{
    /**
     * @param array<int, array{invoice: ClientInvoice|SupplierInvoice, amount: float}> $allocations
     */
    public function record(Contact $contact, array $allocations, array $details): Payment
    {
        $allocations = array_values(array_filter($allocations, fn ($a) => round((float) $a['amount'], 2) > 0));
        if (empty($allocations)) {
            throw ValidationException::withMessages(['amount' => 'Saisissez au moins un montant a regler.']);
        }

        return DB::transaction(function () use ($contact, $allocations, $details) {
            $direction = null;
            $total = 0.0;
            $locked = [];

            foreach ($allocations as $allocation) {
                $invoice = $allocation['invoice']->newQuery()->lockForUpdate()->findOrFail($allocation['invoice']->getKey());
                $amount = round((float) $allocation['amount'], 2);
                $label = $invoice->documentLabel() . ' ' . $invoice->displayNumber();
                // Single-invoice form field is "amount"; grouped form fields are "amounts[<invoice id>]".
                $field = count($allocations) === 1 ? 'amount' : 'amounts.' . $invoice->id;

                if ((int) $invoice->{$this->contactColumn($invoice)} !== (int) $contact->id) {
                    throw ValidationException::withMessages([$field => "$label n'appartient pas a ce contact."]);
                }
                if (!$invoice->isValidated()) {
                    throw ValidationException::withMessages([$field => "$label doit etre validee pour recevoir un reglement."]);
                }
                if ($amount > round((float) $invoice->balance_remaining, 2)) {
                    throw ValidationException::withMessages([
                        $field => "$label : le montant depasse le solde restant (" . number_format((float) $invoice->balance_remaining, 2, '.', ' ') . ').',
                    ]);
                }

                $lineDirection = $this->direction($invoice);
                if ($direction !== null && $direction !== $lineDirection) {
                    throw ValidationException::withMessages(['amount' => 'Un meme reglement ne peut pas melanger factures et remboursements d\'avoirs.']);
                }
                $direction = $lineDirection;
                $total += $amount;
                $locked[] = [$invoice, $amount];
            }

            $payment = Payment::create([
                'contact_id' => $contact->id,
                'direction' => $direction,
                'payment_date' => $details['payment_date'] ?? now()->toDateString(),
                'amount' => round($total, 2),
                'method' => $details['method'] ?? 'transfer',
                'reference' => $details['reference'] ?? null,
                'notes' => $details['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($locked as [$invoice, $amount]) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    $invoice->allocationColumn() => $invoice->id,
                    'amount' => $amount,
                ]);
                $this->refresh($invoice);
            }

            return $payment;
        });
    }

    public function recordForInvoice(ClientInvoice|SupplierInvoice $invoice, float $amount, array $details = []): Payment
    {
        $contact = Contact::findOrFail($invoice->{$this->contactColumn($invoice)});

        return $this->record($contact, [['invoice' => $invoice, 'amount' => $amount]], $details);
    }

    public function void(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $locked = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['payment' => 'Ce reglement est deja annule.']);
            }

            $locked->forceFill([
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
                'updated_by' => Auth::id(),
            ])->save();

            foreach ($locked->allocations()->with(['clientInvoice', 'supplierInvoice'])->get() as $allocation) {
                if ($invoice = $allocation->invoice()) {
                    $this->refresh($invoice);
                }
            }
        });

        $payment->refresh();
    }

    /**
     * Rebuilds amount_paid, balance_remaining and status from active allocations.
     *
     * - invoice:      net = total - its validated credit notes; balance = net - paid (0 once cancelled)
     * - credit note:  what was paid on the invoice beyond its net amount must be refunded; that amount is
     *                 shared between the invoice's credit notes (oldest first), minus refunds already made
     */
    public function refresh(ClientInvoice|SupplierInvoice $invoice): void
    {
        if ($invoice->isDraft()) {
            return;
        }

        $total = (float) $invoice->total_ttc;
        $allocated = $this->allocatedCash($invoice);

        if ($invoice->isCreditNote()) {
            $owed = $this->refundOwed($invoice);
            $balance = max(round($owed - $allocated, 2), 0.0);

            $invoice->forceFill([
                'amount_paid' => round($total - $balance, 2),
                'balance_remaining' => $balance,
                'status' => $balance > 0 ? 'unpaid' : 'paid',
            ])->save();

            return;
        }

        $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : null;

        if ($invoice->isCancelled()) {
            $paid = FinancialHelper::paidAmount($total, $allocated);
            $invoice->forceFill([
                'amount_paid' => $paid,
                'balance_remaining' => 0,
                'status' => FinancialHelper::invoiceStatus($total, $paid, $dueDate),
            ])->save();
        } else {
            $net = $this->netTotal($invoice);
            $paid = FinancialHelper::paidAmount($net, $allocated);
            $invoice->forceFill([
                'amount_paid' => $paid,
                'balance_remaining' => FinancialHelper::balance($net, $paid),
                'status' => FinancialHelper::invoiceStatus($net, $paid, $dueDate),
            ])->save();
        }

        // What was paid beyond what remains billed is what the credit notes must refund.
        foreach ($invoice->creditNotes()->where('state', InvoiceLifecycle::STATE_VALIDATED)->get() as $creditNote) {
            $this->refresh($creditNote);
        }
    }

    /** Total of an invoice minus its validated credit notes. */
    private function netTotal(ClientInvoice|SupplierInvoice $invoice): float
    {
        $credited = (float) $invoice->creditNotes()->where('state', InvoiceLifecycle::STATE_VALIDATED)->sum('total_ttc');

        return max(round((float) $invoice->total_ttc - $credited, 2), 0.0);
    }

    /** Share of the invoice overpayment that this credit note must refund (oldest credit notes first). */
    private function refundOwed(ClientInvoice|SupplierInvoice $creditNote): float
    {
        $original = $creditNote->creditedInvoice()->first();
        if (!$original) {
            return 0.0;
        }

        $pool = max(round($this->allocatedCash($original) - $this->netTotal($original), 2), 0.0);
        foreach ($original->creditNotes()->where('state', InvoiceLifecycle::STATE_VALIDATED)->get() as $note) {
            $share = min((float) $note->total_ttc, $pool);
            if ($note->is($creditNote)) {
                return round($share, 2);
            }
            $pool = max(round($pool - $share, 2), 0.0);
        }

        return 0.0;
    }

    /** Daily job: invoices whose due date passed without full payment become "overdue". */
    public function refreshOpenInvoices(): int
    {
        $count = 0;
        foreach ([ClientInvoice::class, SupplierInvoice::class] as $model) {
            $model::issued()->where('balance_remaining', '>', 0)->chunkById(200, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $before = $invoice->status;
                    $this->refresh($invoice);
                    $count += $invoice->status !== $before ? 1 : 0;
                }
            });
        }

        return $count;
    }

    /** Money in for client invoices / supplier refunds, money out for supplier invoices / client refunds. */
    private function direction(ClientInvoice|SupplierInvoice $invoice): string
    {
        $isClient = $invoice instanceof ClientInvoice;

        return ($isClient xor $invoice->isCreditNote()) ? 'in' : 'out';
    }

    private function allocatedCash(ClientInvoice|SupplierInvoice $invoice): float
    {
        return (float) $invoice->paymentAllocations()
            ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))
            ->sum('amount');
    }

    private function contactColumn(ClientInvoice|SupplierInvoice $invoice): string
    {
        return $invoice instanceof ClientInvoice ? 'client_id' : 'supplier_id';
    }
}
