@extends('layouts.master')

@section('title', 'Categories')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Categories</h4>
        @if(auth()->user()?->hasRole('Admin'))

            <a href="{{ route('erp.imports.index', ['type' => 'categories']) }}#import-categories" class="btn btn-outline-primary"><i class="fe fe-upload" aria-hidden="true"></i> Importer (Excel)</a>

        @endif
        <a href="{{ route('erp.categories.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouvelle categorie</a>
    </div>


    <form method="GET" class="mb-3 erp-filter">
        <input type="search" name="q" class="form-control" aria-label="Rechercher" value="{{ request('q') }}" placeholder="Rechercher des categories">
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Nom</th><th>Parent</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->parent->name ?? '-' }}</td>
                        <td>{{ $category->is_active ? 'Actif' : 'Inactif' }}</td>
                        <td class="erp-actions">
                            <a class="btn btn-sm btn-light" href="{{ route('erp.categories.show', $category) }}"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.categories.edit', $category) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                            <form method="POST" action="{{ route('erp.categories.destroy', $category) }}" onsubmit="return confirm('Supprimer cette categorie ?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">Aucune categorie trouvee</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $categories->links() }}
    </div></div>
</div>
@endsection



