@extends('layouts.master')

@section('title', 'Details releve fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>Releve fournisseur #{{ $statement->id }}</h4>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Releve fournisseur</h6>
            <span class="erp-doc-badge">Fournisseur</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-8 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Informations generales</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Fournisseur</span><span class="value">{{ $statement->supplier->company_name ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Releve</span><span class="value">#{{ $statement->id }}</span></div>
                            <div class="erp-kv-item"><span class="label">Periode debut</span><span class="value">{{ optional($statement->period_start)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Periode fin</span><span class="value">{{ optional($statement->period_end)->format('d/m/Y') ?? '-' }}</span></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-3">
                    <div class="erp-summary-card">
                        <h6>Synthese</h6>
                        <div class="erp-summary-row"><span>Solde d'ouverture</span><strong>{{ \App\Support\Format::money($ledger['opening']) }}</strong></div>
                        <div class="erp-summary-row"><span>Factures de la periode</span><strong>{{ $ledger['invoice_count'] }}</strong></div>
                        <div class="erp-summary-row"><span>Reglements de la periode</span><strong>{{ \App\Support\Format::money($ledger['payments_total']) }}</strong></div>
                        <div class="erp-summary-row total"><span>Solde de cloture</span><strong>{{ \App\Support\Format::money($ledger['closing']) }}</strong></div>
                    </div>
                </div>
            </div>

            @include('erp.partials.statement_ledger', ['ledger' => $ledger, 'documentRoute' => 'erp.supplier-invoices.show', 'owedLabel' => 'montant du au fournisseur'])
        </div>
    </div>
</div>
@endsection
