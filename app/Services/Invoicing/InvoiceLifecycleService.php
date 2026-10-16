<?php

namespace App\Services\Invoicing;

use App\Models\ClientInvoice;
use App\Models\SupplierInvoice;
use App\Services\Numbering\DocumentNumberService;
use App\Support\Branding;
use App\Support\FinancialHelper;
use App\Support\Format;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Business rules for client and supplier invoices.
 *
 * - A draft has a provisional number and is the only editable/deletable state.
 * - Validation assigns the definitive, gap-free number and locks the invoice.
 * - The payment status is always computed from payments (see PaymentService), never typed in.
 * - A validated invoice is corrected only by credit notes (partial or total); once they cover it, it is cancelled.
 */
class InvoiceLifecycleService
{
    private const EPSILON = 0.0005;

    public function __construct(
        private DocumentNumberService $numbering,
        private PaymentService $payments,
    ) {
    }

    public static function provisionalNumber(): string
    {
        return 'BROUILLON-' . Str::upper(Str::random(10));
    }

    public function validate(ClientInvoice|SupplierInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $locked = $invoice->newQuery()->lockForUpdate()->findOrFail($invoice->getKey());

            if (!$locked->isDraft()) {
                throw ValidationException::withMessages(['state' => 'Seul un brouillon peut etre valide.']);
            }

            if ((float) $locked->total_ttc <= 0 || !$locked->items()->exists()) {
                throw ValidationException::withMessages(['state' => 'Une facture sans ligne ou a 0 ne peut pas etre validee.']);
            }

            $locked->forceFill([
                'invoice_number' => $this->numbering->next($locked->getTable(), 'invoice_number', $this->prefix($locked)),
                'state' => InvoiceLifecycle::STATE_VALIDATED,
                'validated_at' => now(),
                'validated_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $locked->save();
            $this->payments->refresh($locked);
        });

        $invoice->refresh();
    }

    /**
     * Invoice lines with what was already credited and what can still be credited.
     *
     * @return Collection<int, array{item: \Illuminate\Database\Eloquent\Model, credited: float, remaining: float}>
     */
    public function creditableLines(ClientInvoice|SupplierInvoice $invoice): Collection
    {
        $noteIds = $invoice->creditNotes()->where('state', InvoiceLifecycle::STATE_VALIDATED)->pluck('id');
        $credited = $invoice->items()->getRelated()->newQuery()
            ->whereIn('invoice_id', $noteIds)
            ->whereNotNull('credited_item_id')
            ->groupBy('credited_item_id')
            ->selectRaw('credited_item_id, SUM(quantity) AS qty')
            ->pluck('qty', 'credited_item_id');

        return $invoice->items()->with(['product', 'service'])->orderBy('id')->get()
            ->map(fn ($item) => [
                'item' => $item,
                'credited' => round((float) ($credited[$item->id] ?? 0), 3),
                'remaining' => max(round((float) $item->quantity - (float) ($credited[$item->id] ?? 0), 3), 0.0),
            ]);
    }

    /** Amounts of the invoice not covered by its credit notes yet: ['ht', 'tva', 'ttc']. */
    public function remainingAmounts(ClientInvoice|SupplierInvoice $invoice): array
    {
        $notes = $invoice->creditNotes()->where('state', InvoiceLifecycle::STATE_VALIDATED)->get(['amount_ht', 'tva_amount', 'total_ttc']);

        return [
            'ht' => max(round((float) $invoice->amount_ht - (float) $notes->sum('amount_ht'), 2), 0.0),
            'tva' => max(round((float) $invoice->tva_amount - (float) $notes->sum('tva_amount'), 2), 0.0),
            'ttc' => max(round((float) $invoice->total_ttc - (float) $notes->sum('total_ttc'), 2), 0.0),
        ];
    }

    /**
     * Credit note on a validated invoice.
     *
     * - $quantities null and no discount: everything still creditable (the invoice is then cancelled).
     * - $quantities [invoice line id => qty]: partial credit note (returned goods, billing error on a line).
     * - $discount ['label' => ..., 'amount_ht' => ...]: free line, e.g. a commercial discount.
     *
     * VAT is the invoice's rate on the credit note total. When the credit notes cover the whole invoice,
     * the last one takes exactly what remains (no rounding cent left) and the invoice becomes cancelled.
     * What was already paid beyond what remains billed is carried by the credit notes as an amount to
     * refund (PaymentService::refresh).
     */
    public function createCreditNote(ClientInvoice|SupplierInvoice $invoice, ?array $quantities = null, ?array $discount = null, ?string $reason = null): ClientInvoice|SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $quantities, $discount, $reason) {
            $locked = $invoice->newQuery()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($locked->isCreditNote() || !$locked->isValidated()) {
                throw ValidationException::withMessages(['state' => 'Un avoir ne peut etre fait que sur une facture validee et non annulee.']);
            }

            $full = $quantities === null && $discount === null;
            $rate = (float) ($locked->tva_rate ?? Branding::defaultVatRate());
            $lines = [];
            $everythingCredited = true;

            foreach ($this->creditableLines($locked)->values() as $index => $line) {
                $item = $line['item'];
                $qty = $full ? $line['remaining'] : round((float) ($quantities[$item->id] ?? 0), 3);

                if ($qty > $line['remaining'] + self::EPSILON) {
                    throw ValidationException::withMessages([
                        "quantities.{$item->id}" => 'Ligne ' . ($index + 1) . ' (' . Format::designation($item) . ') : au plus '
                            . rtrim(rtrim(number_format($line['remaining'], 3, ',', ' '), '0'), ',') . ' a crediter.',
                    ]);
                }
                if ($line['remaining'] - max($qty, 0) > self::EPSILON) {
                    $everythingCredited = false;
                }
                if ($qty <= 0) {
                    continue;
                }

                $lines[] = [
                    'credited_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'service_id' => $item->service_id,
                    'item_name' => $item->item_name,
                    'quantity' => $qty,
                    'price_ht' => (float) $item->price_ht,
                    'tva_rate' => $rate,
                    'total_ht' => round($qty * (float) $item->price_ht, 2),
                ];
            }

            $discountHt = round((float) ($discount['amount_ht'] ?? 0), 2);
            if ($discountHt > 0) {
                $lines[] = [
                    'credited_item_id' => null,
                    'product_id' => null,
                    'service_id' => null,
                    'item_name' => trim((string) ($discount['label'] ?? '')) ?: 'Remise commerciale',
                    'quantity' => 1,
                    'price_ht' => $discountHt,
                    'tva_rate' => $rate,
                    'total_ht' => $discountHt,
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['quantities' => 'Indiquez au moins une quantite ou un montant a crediter.']);
            }

            $remaining = $this->remainingAmounts($locked);
            $creditHt = round(array_sum(array_column($lines, 'total_ht')), 2);

            // Every line returned in full closes the invoice even after an earlier discount; otherwise a credit
            // note can never exceed what is still billed.
            if (!$everythingCredited && $creditHt > $remaining['ht'] + 0.005) {
                throw ValidationException::withMessages([
                    'discount_amount' => "L'avoir (" . Format::money($creditHt) . ' HT) depasse ce qui reste facture ('
                        . Format::money($remaining['ht']) . ' HT).',
                ]);
            }
            $completes = $everythingCredited || abs($creditHt - $remaining['ht']) <= 0.005;

            $totals = $completes
                ? ['total_ht' => $remaining['ht'], 'total_tva' => $remaining['tva'], 'total_ttc' => $remaining['ttc']]
                : FinancialHelper::documentTotals($creditHt, $rate);

            $creditNote = $locked->replicate([
                'invoice_number', 'amount_paid', 'balance_remaining', 'status', 'state', 'credit_reason',
                'validated_at', 'validated_by', 'created_by', 'updated_by',
            ]);
            $creditNote->forceFill([
                'document_type' => InvoiceLifecycle::TYPE_CREDIT_NOTE,
                'credited_invoice_id' => $locked->id,
                'credit_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : ($full ? 'Annulation de la facture' : null),
                'invoice_date' => now()->toDateString(),
                'due_date' => null,
                'state' => InvoiceLifecycle::STATE_VALIDATED,
                'validated_at' => now(),
                'validated_by' => Auth::id(),
                'amount_ht' => $totals['total_ht'],
                'tva_amount' => $totals['total_tva'],
                'total_ttc' => $totals['total_ttc'],
                'tva_rate' => $rate,
                // Settled / to-refund amounts are computed by PaymentService::refresh() below.
                'amount_paid' => 0,
                'balance_remaining' => 0,
                'status' => 'unpaid',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $creditNote->invoice_number = $this->numbering->next($locked->getTable(), 'invoice_number', $this->prefix($creditNote));
            $creditNote->save();

            foreach ($lines as $line) {
                $creditNote->items()->create($line);
            }

            if ($completes) {
                $locked->forceFill(['state' => InvoiceLifecycle::STATE_CANCELLED, 'updated_by' => Auth::id()])->save();
            }

            // Invoice balance -> what remains billed minus payments; credit notes -> what must be refunded.
            $this->payments->refresh($locked);

            return $creditNote->refresh();
        });
    }

    private function prefix(ClientInvoice|SupplierInvoice $invoice): string
    {
        $key = ($invoice instanceof ClientInvoice ? 'client_' : 'supplier_')
            . ($invoice->isCreditNote() ? 'credit_note' : 'invoice');

        return config('numbering.' . $key, [
            'client_invoice' => 'FAC',
            'client_credit_note' => 'AV',
            'supplier_invoice' => 'FSA',
            'supplier_credit_note' => 'AVF',
        ][$key]);
    }
}
