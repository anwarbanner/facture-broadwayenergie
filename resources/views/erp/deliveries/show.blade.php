@extends('layouts.master')

@section('title', 'Details de la livraison')

@section('content')
<div class="container-fluid erp-pro-page">
    @php($simple = \App\Support\Branding::simpleMode())
    <div class="erp-page-head">
        <h4>Bon de livraison {{ $delivery->delivery_number }} <small class="text-muted">- {{ $delivery->client->company_name ?? '' }}</small></h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.deliveries.print', $delivery) }}" target="_blank" rel="noopener" class="btn btn-primary btn-lg" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer le BL</a>
            <a href="{{ route('erp.deliveries.excel', ['id' => $delivery->id]) }}" class="btn btn-light btn-lg" title="Telecharger au format Excel" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            @if($delivery->isSimple())
                {{-- Simple BL: edit the BL itself, or (from a quote) the quote, which updates the BL while it follows it. --}}
                @unless($delivery->clientInvoice)
                    <a href="{{ route('erp.deliveries.simple-edit', $delivery) }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier le BL</a>
                @endunless
                @if($delivery->quote && !$delivery->quote->invoice()->exists())
                    <a href="{{ route('erp.sales-quotes.edit', $delivery->quote) }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-3" aria-hidden="true"></i> Modifier le devis</a>
                @endif
            @endif
            @if($delivery->quote)
                <a href="{{ route('erp.sales-quotes.show', $delivery->quote) }}" class="btn btn-light btn-lg"><i class="fe fe-file-text" aria-hidden="true"></i> Voir le devis</a>
            @endif
            @unless($simple)
            <a href="{{ route('erp.deliveries.pdf', $delivery) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
            @endunless
            @unless($delivery->isSimple())
            <a href="{{ route('erp.deliveries.edit', $delivery) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @endunless
            @if($delivery->clientInvoice)
                <a href="{{ route('erp.client-invoices.show', $delivery->clientInvoice) }}" class="btn btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir facture</a>
            @elseif(!$delivery->isSimple() && in_array($delivery->status, ['pending', 'shipped', 'delivered']))
                <form method="POST" action="{{ route('erp.deliveries.convert-to-invoice', $delivery) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers facture</button>
                </form>
            @endif
        </div>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Informations livraison</h6>
            <span class="erp-doc-badge">Livraison</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $delivery->delivery_number }}</span></div>
                            <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($delivery->status) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date livraison</span><span class="value">{{ optional($delivery->delivery_date)->format('d/m/Y') ?? '-' }}</span></div>
                            @unless($delivery->isSimple())
                            <div class="erp-kv-item"><span class="label">Numero de suivi</span><span class="value">{{ $delivery->tracking_number ?: '-' }}</span></div>
                            @endunless
                            <div class="erp-kv-item"><span class="label">Client</span><span class="value">{{ $delivery->client->company_name ?? '-' }}</span></div>
                            @if($delivery->quote)
                            <div class="erp-kv-item"><span class="label">Devis</span><span class="value">{{ $delivery->quote->quote_number }}</span></div>
                            @elseif(!$delivery->isSimple())
                            <div class="erp-kv-item"><span class="label">Commande source</span><span class="value">{{ $delivery->order->order_number ?? '-' }}</span></div>
                            @endif
                            @if($delivery->shipping_address)
                            <div class="erp-kv-item"><span class="label">Adresse de livraison</span><span class="value">{{ $delivery->shipping_address }}</span></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>Articles de la livraison</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Article</th>
                                @if($delivery->isSimple())
                                <th class="text-end">Quantite</th>
                                @else
                                <th class="text-end">Qte commandee</th>
                                <th class="text-end">Qte expediee</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($delivery->items as $item)
                                <tr>
                                    <td>{{ \App\Support\Format::designation($item) }}</td>
                                    @unless($delivery->isSimple())
                                    <td class="text-end">{{ \App\Support\Format::qty($item->quantity) }}</td>
                                    @endunless
                                    <td class="text-end">{{ \App\Support\Format::qty($item->shipped_qty) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center">Aucun article.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row justify-content-end mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>{{ $delivery->isSimple() ? 'Resume' : 'Execution' }}</h6>
                        <div class="erp-summary-row"><span>Lignes</span><strong>{{ $delivery->items->count() }}</strong></div>
                        @unless($delivery->isSimple())
                        <div class="erp-summary-row"><span>Qte commandee</span><strong>{{ \App\Support\Format::qty($delivery->items->sum('quantity')) }}</strong></div>
                        @endunless
                        <div class="erp-summary-row total"><span>{{ $delivery->isSimple() ? 'Quantite totale' : 'Qte expediee' }}</span><strong>{{ \App\Support\Format::qty($delivery->items->sum('shipped_qty')) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($delivery->isFromQuote() && $delivery->follows_quote)
        <p class="text-muted mt-2">Ce BL suit le devis : si vous modifiez le devis, le BL est mis a jour.</p>
    @elseif($delivery->isFromQuote())
        <p class="text-muted mt-2">Ce BL a ete modifie a la main : il ne suit plus le devis.</p>
    @endif

    @if($delivery->isSimple() && !$delivery->clientInvoice)
        <form method="POST" action="{{ route('erp.deliveries.destroy', $delivery) }}" class="mt-3 text-end" onsubmit="return confirm('Supprimer ce bon de livraison ?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer ce bon de livraison</button>
        </form>
    @endif
</div>
@endsection

