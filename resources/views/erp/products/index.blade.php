@extends('layouts.master')

@section('title', 'Produits ERP')

@section('content')
@if(\App\Support\Branding::simpleMode())
{{-- Simple mode: search, name and price; no stock columns. --}}
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Mes produits</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.products.export') }}" class="btn btn-light btn-lg" data-no-loader><i class="fe fe-download" aria-hidden="true"></i> Exporter en Excel</a>
            <a href="{{ route('erp.products.create') }}" class="btn btn-primary btn-lg"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau produit</a>
        </div>
    </div>

    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-6"><input type="search" name="q" class="form-control form-control-lg" aria-label="Rechercher un produit" placeholder="Rechercher un produit" value="{{ request('q') }}"></div>
        <div class="col-md-2"><button class="btn btn-light btn-lg"><i class="fe fe-search" aria-hidden="true"></i> Rechercher</button></div>
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0 erp-simple-table">
            <thead><tr><th>Produit</th><th class="text-end">Prix de vente HT</th><th>Unite</th><th></th></tr></thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td><strong>{{ $product->product_name }}</strong>@if($product->description)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($product->description, 80) }}</small>@endif</td>
                    <td class="erp-num">{{ \App\Support\Format::money($product->sale_price_ht) }}</td>
                    <td>{{ $product->unit_type }}</td>
                    <td class="erp-actions">
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.products.edit', $product) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        <form method="POST" action="{{ route('erp.products.destroy', $product) }}" onsubmit="return confirm('Supprimer ce produit ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center py-4">Aucun produit trouve.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $products->links() }}
    </div></div>
</div>
@else
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Produits</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.products.export') }}" class="btn btn-light"><i class="fe fe-download" aria-hidden="true"></i> Exporter Excel</a>
            @if(auth()->user()?->hasRole('Admin'))

                <a href="{{ route('erp.imports.index', ['type' => 'products']) }}#import-products" class="btn btn-outline-primary"><i class="fe fe-upload" aria-hidden="true"></i> Importer (Excel)</a>

            @endif
            <a href="{{ route('erp.products.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau produit</a>
        </div>
    </div>


    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-4"><input type="search" name="q" class="form-control" aria-label="Rechercher" placeholder="Rechercher" value="{{ request('q') }}"></div>
        <div class="col-md-3">
            <select name="category_id" class="form-control" aria-label="Filtrer par categorie">
                <option value="">Toutes les categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-control" aria-label="Filtrer par statut">
                <option value="">Tous les statuts</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Actif</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactif</option>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-light"><i class="fe fe-filter" aria-hidden="true"></i> Filtrer</button></div>
    </form>

    <div class="card erp-table-shell">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Reference</th><th>Nom</th><th>Type</th><th>Categorie</th><th>Stock</th><th>Seuil min</th><th>Statut</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php
                            $stockDisplay = rtrim(rtrim(\App\Support\Format::qty($product->stock_quantity), '0'), '.');
                            $minStockDisplay = rtrim(rtrim(\App\Support\Format::qty($product->min_stock_alert_level), '0'), '.');
                        @endphp
                        <tr>
                            <td>{{ $product->reference }}</td>
                            <td>{{ $product->product_name }}</td>
                            <td>{{ \App\Support\Format::label($product->type) }}</td>
                            <td>{{ $product->category->name ?? '-' }}</td>
                            <td>{{ $stockDisplay }}</td>
                            <td>{{ $minStockDisplay }}</td>
                            <td>
                                @if((float) $product->stock_quantity <= (float) $product->min_stock_alert_level)
                                    <span class="badge bg-danger">Stock faible</span>
                                @else
                                    <span class="badge bg-success">Normal</span>
                                @endif
                            </td>
                            <td class="erp-actions">
                                <a class="btn btn-sm btn-light" href="{{ route('erp.products.show', $product) }}"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.products.edit', $product) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                                <form method="POST" action="{{ route('erp.products.destroy', $product) }}" onsubmit="return confirm('Supprimer ce produit ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">Aucun produit trouve</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $products->links() }}
        </div>
    </div>
</div>
@endif
@endsection



