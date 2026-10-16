@extends('layouts.master')

@section('title', 'Import Excel')

@php
    $icons = ['products' => 'fe-package', 'services' => 'fe-briefcase', 'categories' => 'fe-folder', 'contacts' => 'fe-users'];
    $selected = request('type');
@endphp

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <div>
            <h4>Import Excel</h4>
            <small class="text-muted">Ajoutez ou mettez a jour beaucoup d'elements d'un coup. 1. Telechargez le modele &nbsp;2. Remplissez-le &nbsp;3. Envoyez-le : un apercu vous montre tout avant d'enregistrer.</small>
        </div>
    </div>

    <div class="row g-3">
        @foreach($types as $type => [$label, $columns])
            <div class="col-md-6 col-xl-3">
                <div class="card h-100 erp-import-card {{ $selected === $type ? 'is-selected' : '' }}" id="import-{{ $type }}"><div class="card-body d-flex flex-column">
                    <h6 class="mb-1"><i class="fe {{ $icons[$type] }}" aria-hidden="true"></i> {{ $label }}</h6>
                    <small class="text-muted mb-2">Colonnes : {{ collect($columns)->map(fn ($c) => $c[0] . ($c[1] ? '*' : ''))->implode(', ') }}</small>
                    <a href="{{ route('erp.imports.template', $type) }}" class="btn btn-sm btn-outline-primary mb-3 align-self-start" data-no-loader>
                        <i class="fe fe-download" aria-hidden="true"></i> Telecharger le modele</a>
                    <form method="POST" action="{{ route('erp.imports.preview', $type) }}" enctype="multipart/form-data" class="mt-auto">
                        @csrf
                        <label class="form-label small" for="file-{{ $type }}">Fichier rempli (.xlsx, .xls, .csv)</label>
                        <input type="file" id="file-{{ $type }}" name="file" class="form-control form-control-sm mb-2" accept=".xlsx,.xls,.csv,.ods" required>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fe fe-eye" aria-hidden="true"></i> Voir l'apercu</button>
                    </form>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mt-3"><div class="card-body small text-muted">
        <strong>Bon a savoir :</strong> un element deja present est <strong>mis a jour</strong> au lieu d'etre cree en double
        (produit : meme reference, sinon meme nom ; service et categorie : meme nom ; contact : meme type et meme societe).
        Le stock initial ne s'applique qu'aux nouveaux produits (il passe par un mouvement de stock).
        Pour remplir un <strong>devis, une commande ou une facture</strong> depuis Excel, utilisez le bouton
        « Importer des lignes (Excel) » dans le document.
    </div></div>
</div>
@endsection
