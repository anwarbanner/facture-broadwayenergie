@extends('layouts.master')

@section('title', 'Details demande d\'achat')

@section('content')
<div class="container-fluid erp-pro-page">
    @php($simple = \App\Support\Branding::simpleMode())
    <div class="erp-page-head">
        <h4>{{ $simple ? 'Devis fournisseur' : '' }} {{ $quote->quote_number }} <small class="text-muted">- {{ $quote->supplier->company_name ?? '' }}</small></h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.purchase-quotes.print', $quote) }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
            <a href="{{ route('erp.purchase-quotes.excel', ['id' => $quote->id]) }}" class="btn btn-light btn-lg" title="Telecharger au format Excel" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            @unless($quote->invoice)
            <a href="{{ route('erp.purchase-quotes.edit', $quote) }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @endunless
            @unless($simple)
            <a href="{{ route('erp.purchase-quotes.pdf', $quote) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
            @if($quote->order)
                <a href="{{ route('erp.purchase-orders.show', $quote->order) }}" class="btn btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir commande achat</a>
            @elseif(!in_array($quote->status, ['rejected', 'converted']))
                <form method="POST" action="{{ route('erp.purchase-quotes.convert-to-order', $quote) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Convertir en commande</button>
                </form>
            @endif
            @endunless
        </div>
    </div>

    {{-- One-line statements on purpose: this view already uses the inline form, which a multi-line block would break. --}}
    @php($supplierInvoice = $quote->invoice)
    @php($finance = (bool) auth()->user()?->can('manage-finance'))
    @php($supplierPaid = $supplierInvoice && round((float) $supplierInvoice->balance_remaining, 2) <= 0)
    @if($supplierInvoice)
        <div class="erp-cashin {{ $supplierPaid ? 'erp-cashin-done' : 'erp-bl-box' }}">
            <div>
                <i class="fe {{ $supplierPaid ? 'fe-check-circle' : 'fe-file-text' }}" aria-hidden="true"></i>
                <strong>Facture fournisseur {{ $supplierInvoice->invoice_number }}</strong>
                - {{ $supplierPaid ? 'payee' : 'reste a payer ' . \App\Support\Format::money($supplierInvoice->balance_remaining) . ' ' . $supplierInvoice->currency }}
            </div>
            <div class="erp-cashin-buttons">
                <a href="{{ route('erp.supplier-invoices.print', $supplierInvoice) }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg" data-no-loader>
                    <i class="fe fe-printer" aria-hidden="true"></i> Imprimer la facture</a>
                <a href="{{ route('erp.supplier-invoices.excel', ['id' => $supplierInvoice->id]) }}" class="btn btn-light btn-lg" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
                <a href="{{ route('erp.supplier-invoices.show', $supplierInvoice) }}#encaisser" class="btn {{ $supplierPaid ? 'btn-light' : 'btn-success' }} btn-lg">
                    <i class="fe {{ $supplierPaid ? 'fe-external-link' : 'fe-dollar-sign' }}" aria-hidden="true"></i> {{ $supplierPaid ? 'Voir la facture' : 'Payer la facture' }}</a>
            </div>
        </div>
    @elseif($finance && !$quote->order && $quote->status !== 'rejected' && (float) $quote->total_ttc > 0)
        <div class="erp-cashin erp-bl-box">
            <div class="erp-cashin-title">
                <strong>Facture fournisseur</strong>
                <span>Le fournisseur vous a envoye sa facture ? Elle reprend les articles et les prix de ce devis.</span>
            </div>
            <form method="POST" action="{{ route('erp.purchase-quotes.invoice', $quote) }}"
                  onsubmit="return confirm('Faire la facture fournisseur de ce devis ? Le devis ne pourra plus etre modifie.')">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg"><i class="fe fe-file-text" aria-hidden="true"></i> Faire la facture fournisseur</button>
            </form>
        </div>
    @endif

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>{{ $simple ? 'Informations du devis fournisseur' : 'Informations demande' }}</h6>
            <span class="erp-doc-badge">{{ $simple ? 'Devis fournisseur' : 'Demande d\'achat' }}</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $quote->quote_number }}</span></div>
                            <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($quote->status) }}</span></div>
                            <div class="erp-kv-item"><span class="label">{{ $simple ? 'Date' : 'Date demande' }}</span><span class="value">{{ $quote->date ? \App\Support\Format::date($quote->date) : '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Validite</span><span class="value">{{ $quote->validity_date ? \App\Support\Format::date($quote->validity_date) : '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Fournisseur</span><span class="value">{{ $quote->supplier->company_name ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Devise</span><span class="value">{{ $quote->currency }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>{{ $simple ? 'Articles' : 'Articles de la demande' }}</h6>
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
                            @forelse($quote->items as $item)
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
                        <h6>Totaux</h6>
                        <div class="erp-summary-row"><span>Total HT</span><strong>{{ \App\Support\Format::money($quote->total_ht) }}</strong></div>
                        <div class="erp-summary-row"><span>TVA ({{ \App\Support\Format::rate($quote->tva_rate) }})</span><strong>{{ \App\Support\Format::money($quote->total_tva) }}</strong></div>
                        <div class="erp-summary-row total"><span>Total TTC</span><strong>{{ \App\Support\Format::money($quote->total_ttc) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
