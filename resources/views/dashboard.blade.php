@extends('layouts.master')

@section('title', 'Tableau de bord')

@php
    $m = fn ($v) => \App\Support\Format::money($v);
    $user = auth()->user();
    $firstName = \Illuminate\Support\Str::before(trim((string) $user?->name), ' ') ?: 'bonjour';
    $today = now()->locale('fr');
@endphp

@section('content')
<div class="container-fluid erp-pro-page erp-home">
    {{-- Greeting + quick actions --}}
    <div class="erp-home-head">
        <div>
            <h4>Bonjour {{ $firstName }}</h4>
            <small class="text-muted">{{ ucfirst($today->isoFormat('dddd D MMMM YYYY')) }} · {{ \App\Support\Branding::companyName() }}</small>
        </div>
        <div class="erp-quick-actions">
            <a href="{{ route('erp.sales-quotes.create') }}" class="btn btn-primary"><i class="fe fe-file-plus" aria-hidden="true"></i> Nouveau devis</a>
            <a href="{{ route('erp.client-invoices.create') }}" class="btn btn-outline-primary"><i class="fe fe-file-text" aria-hidden="true"></i> Nouvelle facture</a>
            @if($finance)
                <a href="{{ route('erp.payments.create', ['side' => 'client']) }}" class="btn btn-outline-primary"><i class="fe fe-dollar-sign" aria-hidden="true"></i> Encaisser</a>
            @endif
            <a href="{{ route('erp.contacts.create') }}" class="btn btn-light"><i class="fe fe-user-plus" aria-hidden="true"></i> Nouveau contact</a>
        </div>
    </div>

    {{-- Figures of the month (finance) --}}
    @if($finance && $month)
        <div class="erp-kpis mb-3">
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'sales', 'preset' => 'month']) }}">
                <span class="label">Chiffre d'affaires HT · {{ $today->isoFormat('MMMM') }}</span>
                <span class="value">{{ $m($month['sales_ht']) }}</span>
                <small>{{ $month['sales_count'] }} facture(s) validee(s)</small>
            </a>
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'cash', 'preset' => 'month']) }}">
                <span class="label">Encaisse ce mois</span>
                <span class="value text-success">{{ $m($month['cash_in']) }}</span>
                <small>decaisse {{ $m($month['cash_out']) }}</small>
            </a>
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'receivables']) }}">
                <span class="label">A encaisser</span>
                <span class="value">{{ $m($month['receivables']) }}</span>
                <small class="{{ $month['overdue_receivables'] > 0 ? 'text-danger fw-semibold' : '' }}">dont en retard {{ $m($month['overdue_receivables']) }}</small>
            </a>
            <a class="erp-kpi erp-kpi-link" href="{{ route('erp.accounting.index', ['tab' => 'payables']) }}">
                <span class="label">A payer aux fournisseurs</span>
                <span class="value">{{ $m($month['payables']) }}</span>
                <small class="{{ $month['overdue_payables'] > 0 ? 'text-danger fw-semibold' : '' }}">dont en retard {{ $m($month['overdue_payables']) }}</small>
            </a>
        </div>
    @endif

    <div class="row g-3 mb-3">
        {{-- What needs attention --}}
        <div class="{{ $finance ? 'col-xl-5' : 'col-xl-7' }}">
            <div class="card h-100"><div class="card-body">
                <h6 class="mb-3">A faire</h6>
                @forelse($todo as $item)
                    <a href="{{ $item['url'] }}" class="erp-todo erp-todo-{{ $item['tone'] }}">
                        <span class="erp-todo-icon"><i class="fe {{ $item['icon'] }}" aria-hidden="true"></i></span>
                        <span class="erp-todo-text">
                            <strong>{{ $item['count'] }}</strong> {{ mb_strtolower($item['label']) }}
                            @if($item['amount'])<small class="d-block text-muted">{{ $m($item['amount']) }} TTC</small>@endif
                        </span>
                        <i class="fe fe-chevron-right text-muted" aria-hidden="true"></i>
                    </a>
                @empty
                    <div class="erp-todo-empty"><i class="fe fe-check-circle" aria-hidden="true"></i> Tout est a jour : rien d'urgent aujourd'hui.</div>
                @endforelse
            </div></div>
        </div>

        {{-- 6-month trend (finance) --}}
        @if($finance && $trend)
            <div class="col-xl-7">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Ventes et achats HT · 6 derniers mois</h6>
                        <a href="{{ route('erp.accounting.index', ['tab' => 'summary', 'preset' => 'year']) }}" class="small">Comptabilite <i class="fe fe-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <div class="erp-chart"><canvas id="erp-trend" aria-label="Ventes et achats HT par mois" role="img"></canvas></div>
                    <details class="mt-2 small">
                        <summary class="text-muted">Voir les chiffres</summary>
                        <table class="table table-sm mb-0 mt-2">
                            <thead><tr><th>Mois</th><th class="text-end">Ventes HT</th><th class="text-end">Achats HT</th></tr></thead>
                            <tbody>
                            @foreach($trend['labels'] as $i => $label)
                                <tr><td>{{ $label }}</td><td class="erp-num">{{ $m($trend['sales'][$i]) }}</td><td class="erp-num">{{ $m($trend['purchases'][$i]) }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    </details>
                </div></div>
            </div>
        @else
            <div class="col-xl-5">
                <div class="card h-100"><div class="card-body">
                    <h6 class="mb-3">En bref</h6>
                    <div class="erp-mini-stats">
                        <a href="{{ route('erp.contacts.index', ['type' => 'client']) }}"><strong>{{ $counts['clients'] }}</strong><span>clients</span></a>
                        <a href="{{ route('erp.contacts.index', ['type' => 'supplier']) }}"><strong>{{ $counts['suppliers'] }}</strong><span>fournisseurs</span></a>
                        <a href="{{ route('erp.products.index') }}"><strong>{{ $counts['products'] }}</strong><span>produits</span></a>
                        <a href="{{ route('erp.sales-quotes.index') }}"><strong>{{ $counts['quotes_month'] }}</strong><span>devis ce mois</span></a>
                    </div>
                </div></div>
            </div>
        @endif
    </div>

    <div class="row g-3">
        {{-- Last documents --}}
        <div class="col-xl-8">
            <div class="card erp-table-shell"><div class="card-body table-responsive">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Derniers documents de vente</h6>
                    <a href="{{ route('erp.sales-quotes.index') }}" class="small">Tous les devis <i class="fe fe-arrow-right" aria-hidden="true"></i></a>
                </div>
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Document</th><th>Client</th><th class="text-end">Montant TTC</th><th>Statut</th></tr></thead>
                    <tbody>
                    @forelse($recent as $doc)
                        <tr>
                            <td><a href="{{ $doc['url'] }}" class="fw-semibold">{{ $doc['type'] }} {{ $doc['number'] }}</a>
                                <small class="d-block text-muted">{{ $doc['date']?->locale('fr')->diffForHumans() }}</small></td>
                            <td>{{ $doc['contact'] ?? '-' }}</td>
                            <td class="erp-num">{{ $m($doc['amount']) }}</td>
                            <td>{{ \App\Support\Format::statusBadge($doc['status'], $doc['status_label']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-4">Aucun document pour l'instant. <a href="{{ route('erp.sales-quotes.create') }}">Creer un premier devis</a></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>

        {{-- Stock --}}
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Stock bas</h6>
                    <a href="{{ route('erp.products.index') }}" class="small">Produits <i class="fe fe-arrow-right" aria-hidden="true"></i></a>
                </div>
                @forelse($lowStock as $product)
                    <a href="{{ route('erp.products.show', $product) }}" class="erp-stock-row">
                        <span class="text-truncate">{{ $product->product_name }}</span>
                        <span class="text-nowrap"><strong class="text-danger">{{ \App\Support\Format::qty($product->stock_quantity) }}</strong>
                            <small class="text-muted">/ min {{ \App\Support\Format::qty($product->min_stock_alert_level) }}</small></span>
                    </a>
                @empty
                    <div class="erp-todo-empty"><i class="fe fe-check-circle" aria-hidden="true"></i> Aucun produit sous son seuil d'alerte.</div>
                @endforelse
            </div></div>
        </div>
    </div>
</div>
@endsection

@section('js')
@if($finance && $trend)
    <script src="{{ \App\Support\Asset::url('assets/plugins/chart.js/Chart.bundle.min.js') }}"></script>
    <script>
    (function () {
        var canvas = document.getElementById('erp-trend');
        if (!canvas || !window.Chart) return;
        var money = function (v) { return Number(v).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        // Categorical slots 1 and 2 of the validated palette (blue / orange), light and dark steps.
        var colours = function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            return dark
                ? { sales: '#3987e5', purchases: '#d95926', gap: '#162133', text: '#9fadbf', grid: '#26334a', zero: '#34425a' }
                : { sales: '#2a78d6', purchases: '#eb6834', gap: '#ffffff', text: '#5b6b7f', grid: '#eef1f5', zero: '#d9e1ea' };
        };
        var c = colours();
        var chart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: @json($trend['short']),
                datasets: [
                    { label: 'Ventes HT', data: @json($trend['sales']), backgroundColor: c.sales, borderColor: c.gap, borderWidth: 1 },
                    { label: 'Achats HT', data: @json($trend['purchases']), backgroundColor: c.purchases, borderColor: c.gap, borderWidth: 1 }
                ]
            },
            options: {
                maintainAspectRatio: false,
                legend: { position: 'top', align: 'end', labels: { boxWidth: 12, fontColor: c.text } },
                tooltips: {
                    mode: 'index', intersect: false,
                    callbacks: { label: function (item, data) { return data.datasets[item.datasetIndex].label + ' : ' + money(item.yLabel); } }
                },
                hover: { mode: 'index', intersect: false },
                scales: {
                    xAxes: [{ gridLines: { display: false }, ticks: { fontColor: c.text }, barPercentage: 0.8, categoryPercentage: 0.6 }],
                    yAxes: [{ gridLines: { color: c.grid, zeroLineColor: c.zero },
                              ticks: { beginAtZero: true, fontColor: c.text, maxTicksLimit: 5, callback: function (v) { return Number(v).toLocaleString('fr-FR'); } } }]
                }
            }
        });

        document.addEventListener('erp-theme-change', function () {
            var t = colours();
            chart.data.datasets[0].backgroundColor = t.sales;
            chart.data.datasets[1].backgroundColor = t.purchases;
            chart.data.datasets.forEach(function (d) { d.borderColor = t.gap; });
            chart.options.legend.labels.fontColor = t.text;
            chart.options.scales.xAxes[0].ticks.fontColor = t.text;
            chart.options.scales.yAxes[0].ticks.fontColor = t.text;
            chart.options.scales.yAxes[0].gridLines.color = t.grid;
            chart.options.scales.yAxes[0].gridLines.zeroLineColor = t.zero;
            chart.update();
        });
    })();
    </script>
@endif
@endsection
