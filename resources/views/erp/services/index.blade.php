@extends('layouts.master')

@section('title', 'Services ERP')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Services</h4>
        <div class="erp-page-actions">
            @if(auth()->user()?->hasRole('Admin'))

                <a href="{{ route('erp.imports.index', ['type' => 'services']) }}#import-services" class="btn btn-outline-primary"><i class="fe fe-upload" aria-hidden="true"></i> Importer (Excel)</a>

            @endif
            <a href="{{ route('erp.services.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau service</a>
        </div>
    </div>


    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-8"><input type="search" name="q" class="form-control" aria-label="Rechercher" placeholder="Rechercher un service" value="{{ request('q') }}"></div>
        <div class="col-md-2">
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
                        <th>Nom du service</th>
                        <th class="text-end">Heures</th>
                        <th class="text-end">Prix HT</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $service)
                        <tr>
                            <td>{{ $service->service_name }}</td>
                            <td class="text-end">{{ \App\Support\Format::money($service->hours_count) }}</td>
                            <td class="text-end">{{ \App\Support\Format::money($service->service_price) }}</td>
                            <td>
                                @if($service->status === 'active')
                                    <span class="badge bg-success">Actif</span>
                                @else
                                    <span class="badge bg-secondary">Inactif</span>
                                @endif
                            </td>
                            <td class="erp-actions">
                                <a class="btn btn-sm btn-light" href="{{ route('erp.services.show', $service) }}"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('erp.services.edit', $service) }}"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                                <form method="POST" action="{{ route('erp.services.destroy', $service) }}" onsubmit="return confirm('Supprimer ce service ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">Aucun service trouve</td></tr>
                    @endforelse
                </tbody>
            </table>
            {{ $services->links() }}
        </div>
    </div>
</div>
@endsection
