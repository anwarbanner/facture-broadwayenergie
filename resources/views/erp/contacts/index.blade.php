@extends('layouts.master')

@section('title', 'Contacts')

@section('content')
@if(\App\Support\Branding::simpleMode())
{{-- Simple mode: search, name / phone / city, and a "Faire un devis" button per client. --}}
@php($supplier = request('type') === 'supplier')
@php($noun = $supplier ? 'fournisseur' : 'client')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>{{ $supplier ? 'Mes fournisseurs' : 'Mes clients' }}</h4>
        <a href="{{ route('erp.contacts.create', ['type' => $supplier ? 'supplier' : 'client']) }}" class="btn btn-primary btn-lg"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau {{ $noun }}</a>
    </div>

    <form method="GET" class="row g-2 mb-3 erp-filter">
        <input type="hidden" name="type" value="{{ request('type', 'client') }}">
        <div class="col-md-6"><input type="search" name="q" class="form-control form-control-lg" aria-label="Rechercher un {{ $noun }}" value="{{ request('q') }}" placeholder="Rechercher un {{ $noun }} (nom, telephone...)"></div>
        <div class="col-md-2"><button class="btn btn-light btn-lg"><i class="fe fe-search" aria-hidden="true"></i> Rechercher</button></div>
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0 erp-simple-table">
            <thead><tr><th>Nom</th><th>Telephone</th><th>Ville</th><th></th></tr></thead>
            <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td><strong>{{ $contact->company_name }}</strong></td>
                    <td>{{ $contact->phone ?: $contact->mobile }}</td>
                    <td>{{ $contact->city }}</td>
                    <td class="erp-actions">
                        @if($contact->type === 'client')
                            <a class="btn btn-sm btn-primary" href="{{ route('erp.sales-quotes.create', ['client_id' => $contact->id]) }}"><i class="fe fe-plus" aria-hidden="true"></i> Faire un devis</a>
                        @else
                            <a class="btn btn-sm btn-primary" href="{{ route('erp.purchase-quotes.create', ['supplier_id' => $contact->id]) }}"><i class="fe fe-plus" aria-hidden="true"></i> Faire un devis fournisseur</a>
                        @endif
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.contacts.edit', $contact) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        <form method="POST" action="{{ route('erp.contacts.destroy', $contact) }}" onsubmit="return confirm('Supprimer ce {{ $noun }} ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center py-4">Aucun {{ $noun }} trouve.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $contacts->links() }}
    </div></div>
</div>
@else
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Contacts</h4>
        @if(auth()->user()?->hasRole('Admin'))

            <a href="{{ route('erp.imports.index', ['type' => 'contacts']) }}#import-contacts" class="btn btn-outline-primary"><i class="fe fe-upload" aria-hidden="true"></i> Importer (Excel)</a>

        @endif
        <a href="{{ route('erp.contacts.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau contact</a>
    </div>


    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-4"><input type="search" name="q" class="form-control" aria-label="Rechercher" value="{{ request('q') }}" placeholder="Rechercher"></div>
        <div class="col-md-3">
            <select name="type" class="form-control" aria-label="Filtrer par type">
                <option value="">Tous les types</option>
                @foreach(['client', 'supplier'] as $type)
                    <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ \App\Support\Format::label($type) }}</option>
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

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Type</th><th>Societe</th><th>Email</th><th>Telephone</th><th>Ville</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($contacts as $contact)
                <tr>
                    <td>{{ \App\Support\Format::label($contact->type) }}</td>
                    <td>{{ $contact->company_name }}</td>
                    <td>{{ $contact->email }}</td>
                    <td>{{ $contact->phone ?: $contact->mobile }}</td>
                    <td>{{ $contact->city }}</td>
                    <td>{{ \App\Support\Format::statusBadge($contact->status) }}</td>
                    <td class="erp-actions">
                        <a class="btn btn-sm btn-light" href="{{ route('erp.contacts.show', $contact) }}"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.contacts.edit', $contact) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        <form method="POST" action="{{ route('erp.contacts.destroy', $contact) }}" onsubmit="return confirm('Supprimer ce contact ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center">Aucun contact trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $contacts->links() }}
    </div></div>
</div>
@endif
@endsection



