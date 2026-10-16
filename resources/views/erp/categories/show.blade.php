@extends('layouts.master')

@section('title', 'Details de la categorie')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $category->name }}</h4>
        <a href="{{ route('erp.categories.edit', $category) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Fiche categorie</h6>
            <span class="erp-doc-badge">Categorie</span>
        </div>
        <div class="erp-doc-body">
            <div class="erp-doc-lines">
                <h6>Informations generales</h6>
                <div class="erp-kv-grid">
                    <div class="erp-kv-item"><span class="label">Nom</span><span class="value">{{ $category->name }}</span></div>
                    <div class="erp-kv-item"><span class="label">Parent</span><span class="value">{{ $category->parent->name ?? '-' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ $category->is_active ? 'Actif' : 'Inactif' }}</span></div>
                    <div class="erp-kv-item"><span class="label">Description</span><span class="value">{{ $category->description ?: '-' }}</span></div>
                </div>
            </div>

            <div class="erp-doc-lines mt-3">
                <h6>Produits lies</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Produit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($category->products as $product)
                                <tr>
                                    <td>{{ $product->reference ?: '-' }}</td>
                                    <td>{{ $product->product_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center">Aucun produit lie pour le moment.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

