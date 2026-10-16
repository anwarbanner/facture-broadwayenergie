@extends('layouts.master')

@section('title', 'Details commande achat')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $order->order_number }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.purchase-orders.pdf', $order) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
            <a href="{{ route('erp.purchase-orders.excel', ['id' => $order->id]) }}" class="btn btn-light" title="Telecharger au format Excel"><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            <a href="{{ route('erp.purchase-orders.edit', $order) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @if($order->deliveryNote)
                <a href="{{ route('erp.delivery-notes.show', $order->deliveryNote) }}" class="btn btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir bon reception</a>
            @elseif(in_array($order->status, ['draft', 'confirmed', 'partial']))
                <form method="POST" action="{{ route('erp.purchase-orders.convert-to-delivery', $order) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Creer bon reception</button>
                </form>
            @endif
        </div>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Informations commande</h6>
            <span class="erp-doc-badge">Commande achat</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $order->order_number }}</span></div>
                            <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($order->status) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date commande</span><span class="value">{{ optional($order->order_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Reception prevue</span><span class="value">{{ optional($order->expected_delivery_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Fournisseur</span><span class="value">{{ $order->supplier->company_name ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Devise</span><span class="value">{{ $order->currency }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>Articles de la commande</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th class="text-end">Quantite</th>
                                <th class="text-end">Recu</th>
                                <th class="text-end">Reste</th>
                                <th class="text-end">Prix HT</th>
                                <th class="text-end">Total HT</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                                <tr>
                                    <td>
                                        {{ \App\Support\Format::designation($item) }}
                                        @if(!$item->product_id)
                                            <span class="badge bg-secondary ms-1">Manuel</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->quantity) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->delivered_qty) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::qty(max((float) $item->quantity - (float) $item->delivered_qty, 0)) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->price_ht) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::money($item->total_ht) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center">Aucun article.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row justify-content-end mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>Totaux</h6>
                        <div class="erp-summary-row"><span>Total HT</span><strong>{{ \App\Support\Format::money($order->total_ht) }}</strong></div>
                        <div class="erp-summary-row"><span>TVA ({{ \App\Support\Format::rate($order->tva_rate) }})</span><strong>{{ \App\Support\Format::money($order->total_tva) }}</strong></div>
                        <div class="erp-summary-row total"><span>Total TTC</span><strong>{{ \App\Support\Format::money($order->total_ttc) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
