<?php

namespace App\Services\Invoicing;

use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Models\SupplierInvoice;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Carbon;

/**
 * Account statement of a client or supplier, computed live from documents and payments.
 *
 * Balance = amount still owed (by the client to us, or by us to the supplier):
 *   + validated or cancelled invoice (it was issued)   - credit note
 *   - settling payment                                  + refund of a credit note
 * A cancelled invoice and its credit note cancel each other out.
 */
class StatementService
{
    public const SIDE_CLIENT = 'client';
    public const SIDE_SUPPLIER = 'supplier';

    /**
     * @return array{opening: float, closing: float, lines: array<int, array>, invoice_count: int, payments_total: float}
     */
    public function build(string $side, int $contactId, string $start, string $end): array
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->endOfDay();

        $events = array_merge($this->documentEvents($side, $contactId), $this->paymentEvents($side, $contactId));
        usort($events, fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        $opening = 0.0;
        $running = null;
        $lines = [];
        $invoiceCount = 0;
        $paymentsTotal = 0.0;

        foreach ($events as $event) {
            if ($event['date']->lt($start)) {
                $opening += $event['amount'];
                continue;
            }
            if ($event['date']->gt($end)) {
                continue;
            }

            $running = ($running ?? $opening) + $event['amount'];
            $lines[] = $event + ['balance' => round($running, 2)];

            $invoiceCount += $event['kind'] === 'invoice' ? 1 : 0;
            $paymentsTotal += $event['kind'] === 'payment' ? -$event['amount'] : 0;
        }

        return [
            'opening' => round($opening, 2),
            'closing' => round($running ?? $opening, 2),
            'lines' => $lines,
            'invoice_count' => $invoiceCount,
            'payments_total' => round($paymentsTotal, 2),
        ];
    }

    private function documentEvents(string $side, int $contactId): array
    {
        $model = $side === self::SIDE_CLIENT ? ClientInvoice::class : SupplierInvoice::class;
        $contactColumn = $side === self::SIDE_CLIENT ? 'client_id' : 'supplier_id';

        return $model::query()
            ->where($contactColumn, $contactId)
            ->where('state', '!=', InvoiceLifecycle::STATE_DRAFT)
            ->get()
            ->map(function ($doc) {
                $isCredit = $doc->isCreditNote();

                return [
                    'id' => $doc->id,
                    'order' => 0,
                    'date' => Carbon::parse($doc->invoice_date),
                    'kind' => $isCredit ? 'credit_note' : 'invoice',
                    'label' => $isCredit ? 'Avoir' : ('Facture' . ($doc->isCancelled() ? ' (annulee)' : '')),
                    'reference' => $doc->invoice_number,
                    'document' => $doc,
                    'amount' => ($isCredit ? -1 : 1) * (float) $doc->total_ttc,
                ];
            })
            ->all();
    }

    private function paymentEvents(string $side, int $contactId): array
    {
        // Direction that reduces what is owed: client pays us (in), we pay the supplier (out).
        $settling = $side === self::SIDE_CLIENT ? 'in' : 'out';

        return Payment::query()
            ->active()
            ->where('contact_id', $contactId)
            ->get()
            ->map(function (Payment $payment) use ($settling) {
                $isSettlement = $payment->direction === $settling;

                return [
                    'id' => $payment->id,
                    'order' => 1,
                    'date' => Carbon::parse($payment->payment_date),
                    'kind' => $isSettlement ? 'payment' : 'refund',
                    'label' => ($isSettlement ? 'Reglement' : 'Remboursement') . ' (' . $payment->methodLabel() . ')',
                    'reference' => $payment->reference ?: ('REG-' . $payment->id),
                    'document' => null,
                    'amount' => ($isSettlement ? -1 : 1) * (float) $payment->amount,
                ];
            })
            ->all();
    }
}
