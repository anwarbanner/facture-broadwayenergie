<?php

namespace App\Services\Accounting;

use App\Models\ClientInvoice;
use App\Models\Payment;
use App\Models\SupplierInvoice;
use App\Support\InvoiceLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Accounting analysis of the ERP (section "Comptabilite", Admin only).
 *
 * Rules:
 * - A journal holds every issued document: validated invoices, and invoices cancelled later (they were issued;
 *   their cancellation is the credit note, which appears with negative amounts). Drafts never appear.
 * - VAT due = VAT collected on sales - VAT deductible on purchases (credit notes included, negative).
 * - Cash = active payments (voided payments excluded), by payment date.
 * - Aging = validated invoices with a remaining balance, by days past due date.
 */
class AccountingService
{
    public const SIDE_SALES = 'sales';
    public const SIDE_PURCHASES = 'purchases';

    public const GROUPS = ['day' => 'Jour', 'week' => 'Semaine', 'month' => 'Mois', 'quarter' => 'Trimestre', 'year' => 'Annee'];

    public const AGING_BUCKETS = ['not_due' => 'Non echu', '1_30' => '1-30 j', '31_60' => '31-60 j', '61_90' => '61-90 j', 'over_90' => '+90 j'];

    /**
     * Issued documents of the period, signed (credit notes negative), oldest first.
     *
     * @return Collection<int, array{date: Carbon, number: string, type: string, contact: string, contact_id: ?int, ht: float, tva: float, ttc: float, balance: float, state: string}>
     */
    public function journal(string $side, Carbon $from, Carbon $to): Collection
    {
        [$model, $contactRelation] = $this->sideModel($side);

        return $model::query()
            ->with($contactRelation)
            ->where('state', '!=', InvoiceLifecycle::STATE_DRAFT)
            ->whereBetween('invoice_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get()
            ->map(function ($invoice) use ($contactRelation) {
                $sign = $invoice->isCreditNote() ? -1 : 1;

                return [
                    'date' => Carbon::parse($invoice->invoice_date),
                    'number' => (string) $invoice->invoice_number,
                    'type' => $invoice->documentLabel(),
                    'contact' => $invoice->{$contactRelation}?->company_name ?? '-',
                    'contact_id' => $invoice->{$contactRelation}?->id,
                    'ht' => $sign * (float) $invoice->amount_ht,
                    'tva' => $sign * (float) $invoice->tva_amount,
                    'ttc' => $sign * (float) $invoice->total_ttc,
                    'balance' => (float) $invoice->balance_remaining,
                    'state' => $invoice->stateLabel(),
                ];
            });
    }

    /** Journal totals by period: key => [label, count, ht, tva, ttc]. */
    public function byPeriod(Collection $journal, string $group): Collection
    {
        return $journal
            ->groupBy(fn ($row) => $this->periodKey($row['date'], $group))
            ->map(fn (Collection $rows, $key) => [
                'key' => $key,
                'label' => $this->periodLabel($rows->first()['date'], $group),
                'count' => $rows->count(),
                'ht' => round($rows->sum('ht'), 2),
                'tva' => round($rows->sum('tva'), 2),
                'ttc' => round($rows->sum('ttc'), 2),
            ])
            ->sortKeys()
            ->values();
    }

    /** VAT collected (sales) / deductible (purchases) / due, by period. */
    public function vat(Carbon $from, Carbon $to, string $group): Collection
    {
        $sales = $this->byPeriod($this->journal(self::SIDE_SALES, $from, $to), $group)->keyBy('key');
        $purchases = $this->byPeriod($this->journal(self::SIDE_PURCHASES, $from, $to), $group)->keyBy('key');

        return $sales->keys()->merge($purchases->keys())->unique()->sort()->values()
            ->map(function ($key) use ($sales, $purchases) {
                $collected = (float) ($sales[$key]['tva'] ?? 0);
                $deductible = (float) ($purchases[$key]['tva'] ?? 0);

                return [
                    'key' => $key,
                    'label' => $sales[$key]['label'] ?? $purchases[$key]['label'],
                    'sales_ht' => (float) ($sales[$key]['ht'] ?? 0),
                    'collected' => $collected,
                    'purchases_ht' => (float) ($purchases[$key]['ht'] ?? 0),
                    'deductible' => $deductible,
                    'due' => round($collected - $deductible, 2),
                ];
            });
    }

    /** Money in / out by period (active payments only). */
    public function cash(Carbon $from, Carbon $to, string $group): Collection
    {
        return Payment::active()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->get(['payment_date', 'direction', 'amount'])
            ->groupBy(fn ($p) => $this->periodKey(Carbon::parse($p->payment_date), $group))
            ->map(fn (Collection $rows, $key) => [
                'key' => $key,
                'label' => $this->periodLabel(Carbon::parse($rows->first()->payment_date), $group),
                'in' => round((float) $rows->where('direction', 'in')->sum('amount'), 2),
                'out' => round((float) $rows->where('direction', 'out')->sum('amount'), 2),
                'count' => $rows->count(),
            ])
            ->map(fn ($row) => $row + ['net' => round($row['in'] - $row['out'], 2)])
            ->sortKeys()
            ->values();
    }

    /** Payment methods of the period: method label => [in, out]. */
    public function cashByMethod(Carbon $from, Carbon $to): Collection
    {
        return Payment::active()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->get(['method', 'direction', 'amount'])
            ->groupBy('method')
            ->map(fn (Collection $rows, $method) => [
                'label' => Payment::METHODS[$method] ?? $method,
                'in' => round((float) $rows->where('direction', 'in')->sum('amount'), 2),
                'out' => round((float) $rows->where('direction', 'out')->sum('amount'), 2),
            ])
            ->sortByDesc(fn ($row) => $row['in'] + $row['out'])
            ->values();
    }

    /**
     * What remains to be paid today, by contact and lateness (receivables for sales, payables for purchases).
     *
     * @return array{rows: Collection, totals: array<string, float>}
     */
    public function aging(string $side, ?Carbon $today = null): array
    {
        [$model, $contactRelation] = $this->sideModel($side);
        $today = ($today ?? now())->copy()->startOfDay();

        $rows = $model::query()
            ->with($contactRelation)
            ->issued()
            ->where('balance_remaining', '>', 0)
            ->get()
            ->groupBy(fn ($invoice) => $invoice->{$contactRelation}?->id)
            ->map(function (Collection $invoices) use ($contactRelation, $today) {
                $buckets = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
                foreach ($invoices as $invoice) {
                    $buckets[$this->agingBucket($invoice->due_date, $today)] += (float) $invoice->balance_remaining;
                }

                return [
                    'contact' => $invoices->first()->{$contactRelation}?->company_name ?? '-',
                    'contact_id' => $invoices->first()->{$contactRelation}?->id,
                    'count' => $invoices->count(),
                    'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
                    'total' => round(array_sum($buckets), 2),
                ];
            })
            ->sortByDesc('total')
            ->values();

        $totals = array_fill_keys(array_keys(self::AGING_BUCKETS), 0.0);
        foreach ($rows as $row) {
            foreach ($row['buckets'] as $bucket => $amount) {
                $totals[$bucket] += $amount;
            }
        }
        $totals = array_map(fn ($v) => round($v, 2), $totals);
        $totals['total'] = round(array_sum($totals), 2);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /** Best clients / suppliers of the period by net amount HT. */
    public function topContacts(string $side, Carbon $from, Carbon $to, int $limit = 10): Collection
    {
        $journal = $this->journal($side, $from, $to);
        $total = max(0.01, abs($journal->sum('ht')));

        return $journal->groupBy('contact_id')
            ->map(fn (Collection $rows) => [
                'contact' => $rows->first()['contact'],
                'contact_id' => $rows->first()['contact_id'],
                'count' => $rows->where('ht', '>', 0)->count(),
                'ht' => round($rows->sum('ht'), 2),
                'ttc' => round($rows->sum('ttc'), 2),
                'share' => round($rows->sum('ht') / $total * 100, 1),
            ])
            ->sortByDesc('ht')
            ->take($limit)
            ->values();
    }

    /** Key figures of the period, plus what is still open today. */
    public function summary(Carbon $from, Carbon $to): array
    {
        $sales = $this->journal(self::SIDE_SALES, $from, $to);
        $purchases = $this->journal(self::SIDE_PURCHASES, $from, $to);
        $cash = $this->cash($from, $to, 'year');

        return [
            'sales_ht' => round($sales->sum('ht'), 2),
            'sales_ttc' => round($sales->sum('ttc'), 2),
            'sales_count' => $sales->where('ht', '>', 0)->count(),
            'purchases_ht' => round($purchases->sum('ht'), 2),
            'purchases_ttc' => round($purchases->sum('ttc'), 2),
            'purchases_count' => $purchases->where('ht', '>', 0)->count(),
            'gross_margin' => round($sales->sum('ht') - $purchases->sum('ht'), 2),
            'vat_collected' => round($sales->sum('tva'), 2),
            'vat_deductible' => round($purchases->sum('tva'), 2),
            'vat_due' => round($sales->sum('tva') - $purchases->sum('tva'), 2),
            'cash_in' => round($cash->sum('in'), 2),
            'cash_out' => round($cash->sum('out'), 2),
            'receivables' => round((float) ClientInvoice::issued()->sum('balance_remaining'), 2),
            'payables' => round((float) SupplierInvoice::issued()->sum('balance_remaining'), 2),
            'overdue_receivables' => round((float) ClientInvoice::overdue()->sum('balance_remaining'), 2),
            'overdue_payables' => round((float) SupplierInvoice::overdue()->sum('balance_remaining'), 2),
        ];
    }

    /**
     * Journal entries for the accountant (config/accounting.php): every document gives balanced lines.
     * - client invoice:   D clients TTC / C sales HT / C VAT collected      (credit note: the reverse)
     * - supplier invoice: D purchases HT / D VAT deductible / C suppliers TTC (credit note: the reverse)
     * - payment:          bank or cash against the client / supplier account (voided payments excluded)
     *
     * @return Collection<int, array{journal: string, date: Carbon, piece: string, account: string, label: string, contact: string, debit: float, credit: float}>
     */
    public function entries(Carbon $from, Carbon $to): Collection
    {
        $journals = config('accounting.journals');
        $accounts = config('accounting.accounts');
        $lines = collect();
        $line = function (string $journal, $date, string $piece, string $account, string $label, string $contact, float $debit, float $credit) use ($lines) {
            if (round($debit, 2) == 0.0 && round($credit, 2) == 0.0) {
                return;
            }
            // A negative amount goes to the other column (credit notes, refunds).
            if ($debit < 0 || $credit < 0) {
                [$debit, $credit] = [max(-$credit, 0) + max($debit, 0), max(-$debit, 0) + max($credit, 0)];
            }
            $lines->push(['journal' => $journal, 'date' => Carbon::parse($date), 'piece' => $piece, 'account' => $account,
                'label' => $label, 'contact' => $contact, 'debit' => round($debit, 2), 'credit' => round($credit, 2)]);
        };

        foreach ([self::SIDE_SALES, self::SIDE_PURCHASES] as $side) {
            foreach ($this->journal($side, $from, $to) as $doc) {
                $label = $doc['type'] . ' ' . $doc['number'] . ' - ' . $doc['contact'];
                if ($side === self::SIDE_SALES) {
                    $line($journals['sales'], $doc['date'], $doc['number'], $accounts['clients'], $label, $doc['contact'], $doc['ttc'], 0);
                    $line($journals['sales'], $doc['date'], $doc['number'], $accounts['sales'], $label, $doc['contact'], 0, $doc['ht']);
                    $line($journals['sales'], $doc['date'], $doc['number'], $accounts['vat_collected'], $label, $doc['contact'], 0, $doc['tva']);
                } else {
                    $line($journals['purchases'], $doc['date'], $doc['number'], $accounts['purchases'], $label, $doc['contact'], $doc['ht'], 0);
                    $line($journals['purchases'], $doc['date'], $doc['number'], $accounts['vat_deductible'], $label, $doc['contact'], $doc['tva'], 0);
                    $line($journals['purchases'], $doc['date'], $doc['number'], $accounts['suppliers'], $label, $doc['contact'], 0, $doc['ttc']);
                }
            }
        }

        $payments = Payment::active()->with('contact')
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')->orderBy('id')->get();
        foreach ($payments as $payment) {
            $cash = $payment->method === 'cash';
            $journal = $cash ? $journals['cash'] : $journals['bank'];
            $treasury = $cash ? $accounts['cash'] : $accounts['bank'];
            $third = $payment->contact?->type === 'supplier' ? $accounts['suppliers'] : $accounts['clients'];
            $contact = $payment->contact?->company_name ?? '-';
            $piece = $payment->reference ?: 'REG-' . $payment->id;
            $label = $payment->directionLabel() . ' ' . $payment->methodLabel() . ' - ' . $contact;
            $amount = (float) $payment->amount;

            if ($payment->direction === 'in') {
                $line($journal, $payment->payment_date, $piece, $treasury, $label, $contact, $amount, 0);
                $line($journal, $payment->payment_date, $piece, $third, $label, $contact, 0, $amount);
            } else {
                $line($journal, $payment->payment_date, $piece, $third, $label, $contact, $amount, 0);
                $line($journal, $payment->payment_date, $piece, $treasury, $label, $contact, 0, $amount);
            }
        }

        return $lines->sortBy(fn ($l) => $l['date']->format('Ymd') . $l['journal'] . $l['piece'])->values();
    }

    // ---- Periods ---------------------------------------------------------------------------------------

    /** Named period of the filter bar -> [from, to]. */
    public static function range(?string $preset, ?string $from, ?string $to, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy();

        return match ($preset) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay()->startOfDay(), $today->copy()->subDay()->endOfDay()],
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$today->copy()->firstOfQuarter(), $today->copy()->lastOfQuarter()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : $today->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $today->copy()->endOfDay(),
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()], // 'month'
        };
    }

    public function periodKey(Carbon $date, string $group): string
    {
        return match ($group) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->format('o-\WW'),
            'quarter' => $date->year . '-T' . $date->quarter,
            'year' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    public function periodLabel(Carbon $date, string $group): string
    {
        $months = ['janvier', 'fevrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre', 'decembre'];

        return match ($group) {
            'day' => $date->format('d/m/Y'),
            'week' => 'Semaine ' . $date->isoWeek() . ' (' . $date->copy()->startOfWeek()->format('d/m') . ' - ' . $date->copy()->endOfWeek()->format('d/m/Y') . ')',
            'quarter' => 'T' . $date->quarter . ' ' . $date->year,
            'year' => (string) $date->year,
            default => ucfirst($months[$date->month - 1]) . ' ' . $date->year,
        };
    }

    private function agingBucket($dueDate, Carbon $today): string
    {
        if (!$dueDate) {
            return 'not_due';
        }
        $late = (int) Carbon::parse($dueDate)->startOfDay()->diffInDays($today, false);

        return match (true) {
            $late <= 0 => 'not_due',
            $late <= 30 => '1_30',
            $late <= 60 => '31_60',
            $late <= 90 => '61_90',
            default => 'over_90',
        };
    }

    /** @return array{0: class-string, 1: string} */
    private function sideModel(string $side): array
    {
        return $side === self::SIDE_PURCHASES ? [SupplierInvoice::class, 'supplier'] : [ClientInvoice::class, 'client'];
    }
}
