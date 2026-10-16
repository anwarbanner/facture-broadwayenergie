@extends('layouts.master')

@section('title', 'Details facture fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    @include('erp.partials.invoice_lifecycle', ['invoice' => $invoice, 'routePrefix' => 'erp.supplier-invoices'])

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Informations facture</h6>
            <span class="erp-doc-badge">{{ $invoice->documentLabel() }} fournisseur</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $invoice->displayNumber() }}</span></div>
                            <div class="erp-kv-item"><span class="label">Etat</span><span class="value">{{ \App\Support\Format::statusBadge($invoice->state, $invoice->stateLabel()) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Paiement</span><span class="value">{{ \App\Support\Format::statusBadge($invoice->status, $invoice->statusLabel()) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date facture</span><span class="value">{{ optional($invoice->invoice_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date echeance</span><span class="value">{{ optional($invoice->due_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Fournisseur</span><span class="value">{{ $invoice->supplier->company_name ?? '-' }}</span></div>
                            @if($invoice->quote)
                                <div class="erp-kv-item"><span class="label">Devis fournisseur</span><span class="value"><a href="{{ route('erp.purchase-quotes.show', $invoice->quote) }}">{{ $invoice->quote->quote_number }}</a></span></div>
                            @endif
                            <div class="erp-kv-item"><span class="label">Devise</span><span class="value">{{ $invoice->currency }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>Articles de la facture</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th class="text-end">Quantite</th>
                                <th class="text-end">Prix HT</th>
                                <th class="text-end">Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->items as $item)
                                <tr>
                                    <td>
                                        {{ \App\Support\Format::designation($item) }}
                                        @if(!$item->product_id)
                                            <span class="badge bg-secondary ms-1">Manuel</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->quantity) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->price_ht) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->total_ht) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center">Aucun article.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row justify-content-end mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>Totaux et reglement</h6>
                        <div class="erp-summary-row"><span>Total HT</span><strong>{{ \App\Support\Format::money($invoice->amount_ht) }}</strong></div>
                        <div class="erp-summary-row"><span>TVA ({{ \App\Support\Format::rate($invoice->tva_rate) }})</span><strong>{{ \App\Support\Format::money($invoice->tva_amount) }}</strong></div>
                        <div class="erp-summary-row"><span>Montant paye</span><strong>{{ \App\Support\Format::money($invoice->amount_paid) }}</strong></div>
                        <div class="erp-summary-row total"><span>Solde restant</span><strong>{{ \App\Support\Format::money($invoice->balance_remaining) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
