@extends('layouts.master')

@section('title', 'Stock et mouvements')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Mouvements de stock</h4>
    </div>

    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-4"><input type="search" name="q" class="form-control" aria-label="Rechercher" value="{{ request('q') }}" placeholder="Rechercher"></div>
        <div class="col-md-3">
            <select name="type" class="form-control" aria-label="Filtrer par type">
                <option value="">Tous les types</option>
                <option value="in" {{ request('type') === 'in' ? 'selected' : '' }}>Entree</option>
                <option value="out" {{ request('type') === 'out' ? 'selected' : '' }}>Sortie</option>
                <option value="adjustment" {{ request('type') === 'adjustment' ? 'selected' : '' }}>Ajustement</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-light"><i class="fe fe-filter" aria-hidden="true"></i> Filtrer</button></div>
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Produit</th><th>Entrepot</th><th>Type</th><th>Quantite</th><th>Reference</th><th>Utilisateur</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ \App\Support\Format::date($movement->movement_date) }}</td>
                    <td>{{ $movement->product->product_name ?? '-' }}</td>
                    <td>{{ $movement->warehouse->name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::label($movement->movement_type) }}</td>
                    <td>{{ $movement->signed_quantity }}</td>
                    <td>{{ $movement->reference_document }}</td>
                    <td>{{ $movement->user->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Aucun mouvement trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $movements->links() }}
    </div></div>
</div>
@endsection


