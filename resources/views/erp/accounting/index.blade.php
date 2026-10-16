@extends('layouts.master')

@section('title', 'Comptabilite - ' . $tabs[$tab])

@php
    $m = fn ($v) => \App\Support\Format::money($v);
    $query = fn (array $extra = []) => array_merge(request()->only(['preset', 'from', 'to', 'group']), ['tab' => $tab], $extra);
    $periodTabs = ['summary', 'sales', 'purchases', 'vat', 'cash', 'ranking', 'entries'];
    $groupTabs = ['summary', 'sales', 'purchases', 'vat', 'cash'];
@endphp

@section('content')
<div class="container-fluid erp-pro-page erp-accounting">
    <div class="erp-page-head">
        <div>
            <h4>Comptabilite</h4>
            <small class="text-muted">
                @if(in_array($tab, $periodTabs, true))
                    Periode du <strong>{{ $from->format('d/m/Y') }}</strong> au <strong>{{ $to->format('d/m/Y') }}</strong>
                @else
                    Situation au <strong>{{ now()->format('d/m/Y') }}</strong> (factures validees non soldees)
                @endif
            </small>
        </div>
        <div class="erp-page-actions">
            <a href="{{ route('erp.accounting.index', $query(['export' => 'csv'])) }}" class="btn btn-outline-primary"><i class="fe fe-download" aria-hidden="true"></i> Exporter (Excel)</a>
            <button type="button" class="btn btn-light" onclick="window.print()"><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</button>
        </div>
    </div>

    {{-- Tabs --}}
    <nav class="erp-tabs mb-3" aria-label="Analyses comptables">
        @foreach($tabs as $key => $label)
            <a href="{{ route('erp.accounting.index', array_merge($query(), ['tab' => $key])) }}"
               class="erp-tab {{ $tab === $key ? 'active' : '' }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Period filter --}}
    @if(in_array($tab, $periodTabs, true))
        <form method="GET" class="erp-filter mb-3" data-accounting-filter>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label class="mb-0">
                <span class="d-block small text-muted">Periode</span>
                <select name="preset" class="form-control no-search" data-no-search data-preset>
                    @foreach($presets as $key => $label)
                        <option value="{{ $key }}" @selected($preset === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="mb-0">
                <span class="d-block small text-muted">Du</span>
                <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}" data-date>
            </label>
            <label class="mb-0">
                <span class="d-block small text-muted">Au</span>
                <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}" data-date>
            </label>
            @if(in_array($tab, $groupTabs, true))
                <label class="mb-0">
                    <span class="d-block small text-muted">Regrouper par</span>
                    <select name="group" class="form-control no-search" data-no-search>
                        @foreach($groups as $key => $label)
                            <option value="{{ $key }}" @selected($group === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <button type="submit" class="btn btn-primary align-self-end"><i class="fe fe-refresh-cw" aria-hidden="true"></i> Afficher</button>
        </form>
    @endif

    {{-- ============================== Synthese ============================== --}}
    @if($tab === 'summary')
        @php($s = $data['summary'])
        <div class="erp-kpis mb-3">
            <div class="erp-kpi"><span class="label">Chiffre d'affaires HT</span><span class="value">{{ $m($s['sales_ht']) }}</span><small>{{ $s['sales_count'] }} facture(s) · {{ $m($s['sales_ttc']) }} TTC</small></div>
            <div class="erp-kpi"><span class="label">Achats HT</span><span class="value">{{ $m($s['purchases_ht']) }}</span><small>{{ $s['purchases_count'] }} facture(s) · {{ $m($s['purchases_ttc']) }} TTC</small></div>
            <div class="erp-kpi"><span class="label">Marge brute HT</span><span class="value {{ $s['gross_margin'] < 0 ? 'text-danger' : '' }}">{{ $m($s['gross_margin']) }}</span><small>ventes HT - achats HT</small></div>
            <div class="erp-kpi"><span class="label">TVA a payer</span><span class="value {{ $s['vat_due'] < 0 ? 'text-success' : '' }}">{{ $m($s['vat_due']) }}</span><small>{{ $s['vat_due'] < 0 ? 'credit de TVA' : 'collectee ' . $m($s['vat_collected']) . ' - deductible ' . $m($s['vat_deductible']) }}</small></div>
            <div class="erp-kpi"><span class="label">Encaissements</span><span class="value">{{ $m($s['cash_in']) }}</span><small>decaissements {{ $m($s['cash_out']) }}</small></div>
            <div class="erp-kpi"><span class="label">Tresorerie nette</span><span class="value {{ $s['cash_in'] - $s['cash_out'] < 0 ? 'text-danger' : '' }}">{{ $m($s['cash_in'] - $s['cash_out']) }}</span><small>sur la periode</small></div>
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'receivables']) }}"><span class="label">A encaisser (clients)</span><span class="value">{{ $m($s['receivables']) }}</span><small class="{{ $s['overdue_receivables'] > 0 ? 'text-danger' : '' }}">dont en retard {{ $m($s['overdue_receivables']) }}</small></a>
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'payables']) }}"><span class="label">A payer (fournisseurs)</span><span class="value">{{ $m($s['payables']) }}</span><small class="{{ $s['overdue_payables'] > 0 ? 'text-danger' : '' }}">dont en retard {{ $m($s['overdue_payables']) }}</small></a>
        </div>

        <div class="card erp-table-shell"><div class="card-body table-responsive">
            <h6 class="mb-2">Ventes, achats et TVA par {{ mb_strtolower($groups[$group]) }}</h6>
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Periode</th><th class="text-end">Ventes HT</th><th class="text-end">Achats HT</th><th class="text-end">Marge HT</th><th class="text-end">TVA due</th></tr></thead>
                <tbody>
                @forelse($data['months'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="erp-num">{{ $m($row['sales_ht']) }}</td>
                        <td class="erp-num">{{ $m($row['purchases_ht']) }}</td>
                        <td class="erp-num">{{ $m($row['sales_ht'] - $row['purchases_ht']) }}</td>
                        <td class="erp-num">{{ $m($row['due']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">Aucune facture validee sur cette periode.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>
    @endif

    {{-- ============================== Journaux ============================== --}}
    @if(in_array($tab, ['sales', 'purchases'], true))
        @php($journal = $data['journal'])
        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card erp-table-shell"><div class="card-body table-responsive">
                    <h6 class="mb-2">Releve par {{ mb_strtolower($groups[$group]) }}</h6>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Periode</th><th class="text-end">Nb</th><th class="text-end">HT</th><th class="text-end">TTC</th></tr></thead>
                        <tbody>
                        @forelse($data['periods'] as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="erp-num">{{ $row['count'] }}</td><td class="erp-num">{{ $m($row['ht']) }}</td><td class="erp-num">{{ $m($row['ttc']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center">Aucune piece.</td></tr>
                        @endforelse
                        </tbody>
                        @if($data['periods']->isNotEmpty())
                            <tfoot><tr class="erp-total-row"><th>Total</th><th class="erp-num">{{ $journal->count() }}</th><th class="erp-num">{{ $m($journal->sum('ht')) }}</th><th class="erp-num">{{ $m($journal->sum('ttc')) }}</th></tr></tfoot>
                        @endif
                    </table>
                </div></div>
            </div>
            <div class="col-xl-8">
                <div class="card erp-table-shell"><div class="card-body table-responsive">
                    <h6 class="mb-2">{{ $tabs[$tab] }} <small class="text-muted">- factures validees, avoirs en negatif</small></h6>
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Date</th><th>Numero</th><th>Type</th><th>{{ $tab === 'sales' ? 'Client' : 'Fournisseur' }}</th><th class="text-end">HT</th><th class="text-end">TVA</th><th class="text-end">TTC</th><th class="text-end">Reste</th></tr></thead>
                        <tbody>
                        @forelse($journal as $row)
                            <tr>
                                <td>{{ $row['date']->format('d/m/Y') }}</td>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['type'] }}@if($row['state'] === 'Annulee par avoir') <small class="text-muted">(annulee)</small>@endif</td>
                                <td>{{ $row['contact'] }}</td>
                                <td class="erp-num {{ $row['ht'] < 0 ? 'text-danger' : '' }}">{{ $m($row['ht']) }}</td>
                                <td class="erp-num {{ $row['tva'] < 0 ? 'text-danger' : '' }}">{{ $m($row['tva']) }}</td>
                                <td class="erp-num {{ $row['ttc'] < 0 ? 'text-danger' : '' }}">{{ $m($row['ttc']) }}</td>
                                <td class="erp-num">{{ $m($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">Aucune facture validee sur cette periode.</td></tr>
                        @endforelse
                        </tbody>
                        @if($journal->isNotEmpty())
                            <tfoot><tr class="erp-total-row"><th colspan="4">Total ({{ $journal->count() }} pieces)</th><th class="erp-num">{{ $m($journal->sum('ht')) }}</th><th class="erp-num">{{ $m($journal->sum('tva')) }}</th><th class="erp-num">{{ $m($journal->sum('ttc')) }}</th><th class="erp-num">{{ $m($journal->sum('balance')) }}</th></tr></tfoot>
                        @endif
                    </table>
                </div></div>
            </div>
        </div>
    @endif

    {{-- ============================== TVA ============================== --}}
    @if($tab === 'vat')
        @php($p = $data['periods'])
        <div class="erp-kpis mb-3">
            <div class="erp-kpi"><span class="label">TVA collectee (ventes)</span><span class="value">{{ $m($p->sum('collected')) }}</span></div>
            <div class="erp-kpi"><span class="label">TVA deductible (achats)</span><span class="value">{{ $m($p->sum('deductible')) }}</span></div>
            <div class="erp-kpi"><span class="label">{{ $p->sum('due') < 0 ? 'Credit de TVA' : 'TVA a payer' }}</span><span class="value">{{ $m(abs($p->sum('due'))) }}</span></div>
        </div>
        <div class="card erp-table-shell"><div class="card-body table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Periode</th><th class="text-end">Ventes HT</th><th class="text-end">TVA collectee</th><th class="text-end">Achats HT</th><th class="text-end">TVA deductible</th><th class="text-end">TVA due</th></tr></thead>
                <tbody>
                @forelse($p as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="erp-num">{{ $m($row['sales_ht']) }}</td>
                        <td class="erp-num">{{ $m($row['collected']) }}</td>
                        <td class="erp-num">{{ $m($row['purchases_ht']) }}</td>
                        <td class="erp-num">{{ $m($row['deductible']) }}</td>
                        <td class="erp-num fw-semibold {{ $row['due'] < 0 ? 'text-success' : '' }}">{{ $m($row['due']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">Aucune facture validee sur cette periode.</td></tr>
                @endforelse
                </tbody>
                @if($p->isNotEmpty())
                    <tfoot><tr class="erp-total-row"><th>Total</th><th class="erp-num">{{ $m($p->sum('sales_ht')) }}</th><th class="erp-num">{{ $m($p->sum('collected')) }}</th><th class="erp-num">{{ $m($p->sum('purchases_ht')) }}</th><th class="erp-num">{{ $m($p->sum('deductible')) }}</th><th class="erp-num">{{ $m($p->sum('due')) }}</th></tr></tfoot>
                @endif
            </table>
            <p class="text-muted small mt-2 mb-0">TVA calculee sur les factures validees (date de facture) ; les avoirs viennent en deduction. Un montant negatif est un credit de TVA.</p>
        </div></div>
    @endif

    {{-- ============================== Tresorerie ============================== --}}
    @if($tab === 'cash')
        @php($p = $data['periods'])
        <div class="erp-kpis mb-3">
            <div class="erp-kpi"><span class="label">Encaissements</span><span class="value">{{ $m($p->sum('in')) }}</span></div>
            <div class="erp-kpi"><span class="label">Decaissements</span><span class="value">{{ $m($p->sum('out')) }}</span></div>
            <div class="erp-kpi"><span class="label">Solde de la periode</span><span class="value {{ $p->sum('net') < 0 ? 'text-danger' : '' }}">{{ $m($p->sum('net')) }}</span></div>
        </div>
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card erp-table-shell"><div class="card-body table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Periode</th><th class="text-end">Encaissements</th><th class="text-end">Decaissements</th><th class="text-end">Solde</th><th class="text-end">Operations</th></tr></thead>
                        <tbody>
                        @forelse($p as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="erp-num">{{ $m($row['in']) }}</td><td class="erp-num">{{ $m($row['out']) }}</td><td class="erp-num fw-semibold {{ $row['net'] < 0 ? 'text-danger' : '' }}">{{ $m($row['net']) }}</td><td class="erp-num">{{ $row['count'] }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center">Aucun reglement sur cette periode.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card erp-table-shell"><div class="card-body table-responsive">
                    <h6 class="mb-2">Par mode de paiement</h6>
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Mode</th><th class="text-end">Recu</th><th class="text-end">Paye</th></tr></thead>
                        <tbody>
                        @forelse($data['methods'] as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="erp-num">{{ $m($row['in']) }}</td><td class="erp-num">{{ $m($row['out']) }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-center">-</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    @endif

    {{-- ============================== Echeanciers ============================== --}}
    @if(in_array($tab, ['receivables', 'payables'], true))
        @php($t = $data['totals'])
        <div class="erp-kpis mb-3">
            <div class="erp-kpi"><span class="label">{{ $tab === 'receivables' ? 'Total a encaisser' : 'Total a payer' }}</span><span class="value">{{ $m($t['total']) }}</span></div>
            <div class="erp-kpi"><span class="label">Non echu</span><span class="value">{{ $m($t['not_due']) }}</span></div>
            <div class="erp-kpi"><span class="label">En retard</span><span class="value {{ $t['total'] - $t['not_due'] > 0 ? 'text-danger' : '' }}">{{ $m($t['total'] - $t['not_due']) }}</span><small>dont +90 j : {{ $m($t['over_90']) }}</small></div>
        </div>
        <div class="card erp-table-shell"><div class="card-body table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ $tab === 'receivables' ? 'Client' : 'Fournisseur' }}</th><th class="text-end">Factures</th>@foreach($buckets as $label)<th class="text-end">{{ $label }}</th>@endforeach<th class="text-end">Total</th></tr></thead>
                <tbody>
                @forelse($data['rows'] as $row)
                    <tr>
                        <td>@if($row['contact_id'])<a href="{{ route('erp.contacts.show', $row['contact_id']) }}">{{ $row['contact'] }}</a>@else{{ $row['contact'] }}@endif</td>
                        <td class="erp-num">{{ $row['count'] }}</td>
                        @foreach($row['buckets'] as $bucket => $amount)
                            <td class="erp-num {{ $amount > 0 && $bucket !== 'not_due' ? 'text-danger' : '' }}">{{ $amount > 0 ? $m($amount) : '-' }}</td>
                        @endforeach
                        <td class="erp-num fw-semibold">{{ $m($row['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($buckets) + 3 }}" class="text-center">Aucune facture validee en attente de paiement.</td></tr>
                @endforelse
                </tbody>
                @if($data['rows']->isNotEmpty())
                    <tfoot><tr class="erp-total-row"><th>Total</th><th class="erp-num">{{ $data['rows']->sum('count') }}</th>@foreach(array_keys($buckets) as $bucket)<th class="erp-num">{{ $m($t[$bucket]) }}</th>@endforeach<th class="erp-num">{{ $m($t['total']) }}</th></tr></tfoot>
                @endif
            </table>
            <p class="text-muted small mt-2 mb-0">Retard compte a partir de la date d'echeance de chaque facture.</p>
        </div></div>
    @endif

    {{-- ============================== Ecritures comptables ============================== --}}
    @if($tab === 'entries')
        @php($e = $data['entries'])
        @php($debit = round($e->sum('debit'), 2))
        @php($credit = round($e->sum('credit'), 2))
        <div class="erp-kpis mb-3">
            <div class="erp-kpi"><span class="label">Lignes d'ecriture</span><span class="value">{{ $e->count() }}</span><small>{{ $e->pluck('piece')->unique()->count() }} piece(s)</small></div>
            <div class="erp-kpi"><span class="label">Total debit</span><span class="value">{{ $m($debit) }}</span></div>
            <div class="erp-kpi"><span class="label">Total credit</span><span class="value">{{ $m($credit) }}</span></div>
            <div class="erp-kpi"><span class="label">Equilibre</span>
                @if(abs($debit - $credit) < 0.01)
                    <span class="value text-success"><i class="fe fe-check-circle" aria-hidden="true"></i> OK</span><small>debit = credit</small>
                @else
                    <span class="value text-danger">{{ $m($debit - $credit) }}</span><small>ecart a verifier</small>
                @endif
            </div>
        </div>
        <div class="card erp-table-shell"><div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Journal</th><th>Date</th><th>Piece</th><th>Compte</th><th>Libelle</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                @forelse($e as $row)
                    <tr>
                        <td><span class="erp-status erp-status-neutral">{{ $row['journal'] }}</span></td>
                        <td class="text-nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                        <td class="text-nowrap">{{ $row['piece'] }}</td>
                        <td class="fw-semibold">{{ $row['account'] }}</td>
                        <td>{{ $row['label'] }}</td>
                        <td class="erp-num">{{ $row['debit'] > 0 ? $m($row['debit']) : '' }}</td>
                        <td class="erp-num">{{ $row['credit'] > 0 ? $m($row['credit']) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">Aucune ecriture sur cette periode.</td></tr>
                @endforelse
                </tbody>
                @if($e->isNotEmpty())
                    <tfoot><tr class="erp-total-row"><th colspan="5">Total</th><th class="erp-num">{{ $m($debit) }}</th><th class="erp-num">{{ $m($credit) }}</th></tr></tfoot>
                @endif
            </table>
            <p class="text-muted small mt-2 mb-0">
                Factures et avoirs valides (journaux {{ config('accounting.journals.sales') }} / {{ config('accounting.journals.purchases') }}) et reglements
                ({{ config('accounting.journals.bank') }} banque, {{ config('accounting.journals.cash') }} especes). Comptes :
                clients {{ config('accounting.accounts.clients') }}, fournisseurs {{ config('accounting.accounts.suppliers') }},
                ventes {{ config('accounting.accounts.sales') }}, achats {{ config('accounting.accounts.purchases') }},
                TVA facturee {{ config('accounting.accounts.vat_collected') }}, TVA recuperable {{ config('accounting.accounts.vat_deductible') }},
                banque {{ config('accounting.accounts.bank') }}, caisse {{ config('accounting.accounts.cash') }}
                - modifiables dans le fichier .env (ACCOUNTING_ACCOUNT_...). « Exporter (Excel) » donne le fichier pour votre comptable.
            </p>
        </div></div>
    @endif

    {{-- ============================== Classements ============================== --}}
    @if($tab === 'ranking')
        <div class="row g-3">
            @foreach(['clients' => 'Meilleurs clients (CA HT)', 'suppliers' => 'Principaux fournisseurs (achats HT)'] as $key => $title)
                <div class="col-xl-6">
                    <div class="card erp-table-shell"><div class="card-body table-responsive">
                        <h6 class="mb-2">{{ $title }}</h6>
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>#</th><th>{{ $key === 'clients' ? 'Client' : 'Fournisseur' }}</th><th class="text-end">Factures</th><th class="text-end">HT</th><th style="width:34%">Part</th></tr></thead>
                            <tbody>
                            @forelse($data[$key] as $i => $row)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>@if($row['contact_id'])<a href="{{ route('erp.contacts.show', $row['contact_id']) }}">{{ $row['contact'] }}</a>@else{{ $row['contact'] }}@endif</td>
                                    <td class="erp-num">{{ $row['count'] }}</td>
                                    <td class="erp-num">{{ $m($row['ht']) }}</td>
                                    <td>
                                        <div class="erp-share" title="{{ $row['contact'] }} : {{ number_format($row['share'], 1, ',', ' ') }} %">
                                            <span class="erp-share-bar" style="width: {{ max(0, min(100, $row['share'])) }}%"></span>
                                            <span class="erp-share-value">{{ number_format($row['share'], 1, ',', ' ') }} %</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center">Aucune facture validee sur cette periode.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
// Picking dates switches the period to "Personnalise"; picking a named period fills the dates on submit (server side).
(function () {
    var form = document.querySelector('[data-accounting-filter]');
    if (!form) return;
    var preset = form.querySelector('[data-preset]');
    form.querySelectorAll('[data-date]').forEach(function (input) {
        input.addEventListener('change', function () { preset.value = 'custom'; });
    });
    preset.addEventListener('change', function () {
        if (preset.value !== 'custom') {
            form.querySelectorAll('[data-date]').forEach(function (input) { input.disabled = true; });
            form.submit();
        }
    });
})();
</script>
@endsection
