@extends('layouts.master')

@section('title', 'Details du produit')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $product->product_name }}</h4>
        <a href="{{ route('erp.products.edit', $product) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Fiche produit</h6>
            <span class="erp-doc-badge">Produit</span>
        </div>
        <div class="erp-doc-body">
            <div class="erp-doc-lines">
                <h6>Informations generales</h6>
                <div class="erp-kv-grid">
                    <div class="erp-kv-item"><span class="label">Reference</span><span class="value">{{ $product->reference ?: '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Categorie</span><span class="value">{{ $product->category->name ?? '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Type</span><span class="value">{{ \App\Support\Format::label($product->type) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($product->status) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Prix de vente HT</span><span class="value">{{ \App\Support\Format::money($product->sale_price_ht) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Prix de revient HT</span><span class="value">{{ \App\Support\Format::money($product->cost_price_ht) }}</span></div>
                    <div class="erp-kv-item"><span class="label">Unite</span><span class="value">{{ $product->unit_type ?: '-' }}</span></div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>Stock et marge</h6>
                        <div class="erp-summary-row"><span>Stock actuel</span><strong>{{ \App\Support\Format::qty($product->stock_quantity) }}</strong></div>
                        <div class="erp-summary-row"><span>Seuil alerte</span><strong>{{ \App\Support\Format::qty($product->min_stock_alert_level) }}</strong></div>
                        <div class="erp-summary-row"><span>Marge</span><strong>{{ \App\Support\Format::money($product->margin_amount) }}</strong></div>
                        <div class="erp-summary-row total"><span>Taux de marge</span><strong>{{ \App\Support\Format::money($product->margin_rate) }}%</strong></div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="erp-doc-lines">
                        <h6>Mouvements de stock</h6>
                        <div class="table-responsive">
                            <table class="table table-sm erp-lines-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="text-end">Quantite</th>
                                        <th>Reference</th>
                                        <th>Utilisateur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($product->stockMovements as $movement)
                                    <tr>
                                        <td>{{ optional($movement->movement_date)->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>{{ \App\Support\Format::label($movement->movement_type) }}</td>
                                        <td class="text-end">{{ $movement->signed_quantity }}</td>
                                        <td>{{ $movement->reference_document ?: '-' }}</td>
                                        <td>{{ $movement->user->name ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center">Aucun mouvement</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

