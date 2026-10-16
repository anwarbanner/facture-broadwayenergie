<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Section "Comptabilite": journals, VAT, cash, aging, rankings. Every tab can be exported to CSV (Excel). */
class AccountingController extends Controller
{
    public const TABS = [
        'summary' => 'Synthese',
        'sales' => 'Journal des ventes',
        'purchases' => 'Journal des achats',
        'vat' => 'TVA',
        'cash' => 'Tresorerie',
        'receivables' => 'Echeancier clients',
        'payables' => 'Echeancier fournisseurs',
        'ranking' => 'Classements',
        'entries' => 'Ecritures comptables',
    ];

    public const PRESETS = [
        'today' => 'Aujourd\'hui',
        'yesterday' => 'Hier',
        'week' => 'Cette semaine',
        'month' => 'Ce mois',
        'last_month' => 'Mois dernier',
        'quarter' => 'Ce trimestre',
        'year' => 'Cette annee',
        'last_year' => 'Annee derniere',
        'custom' => 'Personnalise',
    ];

    public function __construct(private AccountingService $accounting)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'in:' . implode(',', array_keys(self::TABS))],
            'preset' => ['nullable', 'in:' . implode(',', array_keys(self::PRESETS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group' => ['nullable', 'in:' . implode(',', array_keys(AccountingService::GROUPS))],
            'export' => ['nullable', 'in:csv'],
        ]);

        $tab = $filters['tab'] ?? 'summary';
        $preset = $filters['preset'] ?? (isset($filters['from']) || isset($filters['to']) ? 'custom' : 'month');
        [$from, $to] = AccountingService::range($preset, $filters['from'] ?? null, $filters['to'] ?? null);
        $group = $filters['group'] ?? $this->defaultGroup($from, $to);

        $data = $this->tabData($tab, $from, $to, $group);

        if (($filters['export'] ?? null) === 'csv') {
            return $this->csv($tab, $data, $from, $to);
        }

        return view('erp.accounting.index', compact('tab', 'preset', 'from', 'to', 'group', 'data') + [
            'tabs' => self::TABS,
            'presets' => self::PRESETS,
            'groups' => AccountingService::GROUPS,
            'buckets' => AccountingService::AGING_BUCKETS,
        ]);
    }

    private function tabData(string $tab, Carbon $from, Carbon $to, string $group): array
    {
        $a = $this->accounting;

        return match ($tab) {
            'sales', 'purchases' => (function () use ($a, $tab, $from, $to, $group) {
                $journal = $a->journal($tab, $from, $to);

                return ['journal' => $journal, 'periods' => $a->byPeriod($journal, $group)];
            })(),
            'vat' => ['periods' => $a->vat($from, $to, $group)],
            'cash' => ['periods' => $a->cash($from, $to, $group), 'methods' => $a->cashByMethod($from, $to)],
            'receivables' => $a->aging(AccountingService::SIDE_SALES),
            'payables' => $a->aging(AccountingService::SIDE_PURCHASES),
            'entries' => ['entries' => $a->entries($from, $to)],
            'ranking' => [
                'clients' => $a->topContacts(AccountingService::SIDE_SALES, $from, $to),
                'suppliers' => $a->topContacts(AccountingService::SIDE_PURCHASES, $from, $to),
            ],
            default => [
                'summary' => $a->summary($from, $to),
                'months' => $a->vat($from, $to, $group),
            ],
        };
    }

    /** Short periods by day, a quarter by month, longer ranges by month too. */
    private function defaultGroup(Carbon $from, Carbon $to): string
    {
        return $from->diffInDays($to) <= 31 ? 'day' : 'month';
    }

    private function csv(string $tab, array $data, Carbon $from, Carbon $to): StreamedResponse
    {
        $n = fn ($v) => number_format((float) $v, 2, ',', '');
        [$header, $rows] = match ($tab) {
            'sales', 'purchases' => [
                ['Date', 'Numero', 'Type', $tab === 'sales' ? 'Client' : 'Fournisseur', 'HT', 'TVA', 'TTC', 'Reste a payer', 'Etat'],
                $data['journal']->map(fn ($r) => [$r['date']->format('d/m/Y'), $r['number'], $r['type'], $r['contact'], $n($r['ht']), $n($r['tva']), $n($r['ttc']), $n($r['balance']), $r['state']]),
            ],
            'vat' => [
                ['Periode', 'Ventes HT', 'TVA collectee', 'Achats HT', 'TVA deductible', 'TVA due'],
                $data['periods']->map(fn ($r) => [$r['label'], $n($r['sales_ht']), $n($r['collected']), $n($r['purchases_ht']), $n($r['deductible']), $n($r['due'])]),
            ],
            'cash' => [
                ['Periode', 'Encaissements', 'Decaissements', 'Solde', 'Operations'],
                $data['periods']->map(fn ($r) => [$r['label'], $n($r['in']), $n($r['out']), $n($r['net']), $r['count']]),
            ],
            'receivables', 'payables' => [
                array_merge([$tab === 'receivables' ? 'Client' : 'Fournisseur', 'Factures'], array_values(AccountingService::AGING_BUCKETS), ['Total']),
                $data['rows']->map(fn ($r) => array_merge([$r['contact'], $r['count']], array_map($n, array_values($r['buckets'])), [$n($r['total'])])),
            ],
            'entries' => [
                ['Journal', 'Date', 'Piece', 'Compte', 'Libelle', 'Tiers', 'Debit', 'Credit'],
                $data['entries']->map(fn ($e) => [$e['journal'], $e['date']->format('d/m/Y'), $e['piece'], $e['account'], $e['label'], $e['contact'], $n($e['debit']), $n($e['credit'])]),
            ],
            'ranking' => [
                ['Classement', 'Contact', 'Factures', 'HT', 'TTC', 'Part %'],
                $data['clients']->map(fn ($r) => ['Client', $r['contact'], $r['count'], $n($r['ht']), $n($r['ttc']), $n($r['share'])])
                    ->merge($data['suppliers']->map(fn ($r) => ['Fournisseur', $r['contact'], $r['count'], $n($r['ht']), $n($r['ttc']), $n($r['share'])])),
            ],
            default => [
                ['Indicateur', 'Montant'],
                collect([
                    ['Chiffre d\'affaires HT', $data['summary']['sales_ht']], ['Achats HT', $data['summary']['purchases_ht']],
                    ['Marge brute HT', $data['summary']['gross_margin']], ['TVA collectee', $data['summary']['vat_collected']],
                    ['TVA deductible', $data['summary']['vat_deductible']], ['TVA due', $data['summary']['vat_due']],
                    ['Encaissements', $data['summary']['cash_in']], ['Decaissements', $data['summary']['cash_out']],
                    ['Creances clients (aujourd\'hui)', $data['summary']['receivables']], ['Dettes fournisseurs (aujourd\'hui)', $data['summary']['payables']],
                ])->map(fn ($r) => [$r[0], $n($r[1])]),
            ],
        };

        $file = 'comptabilite-' . $tab . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM: accents readable in Excel
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
