@extends('layouts.master')

@section('title', 'Apercu de l\'import')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <div>
            <h4>Apercu de l'import : {{ mb_strtolower($label) }}</h4>
            <small class="text-muted">Fichier « {{ $file }} » : rien n'est encore enregistre. Verifiez, puis confirmez.</small>
        </div>
    </div>

    <div class="erp-kpis mb-3">
        <div class="erp-kpi"><span class="label"><i class="fe fe-plus-circle" aria-hidden="true"></i> A creer</span><span class="value text-success">{{ $counts['create'] }}</span></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-edit-2" aria-hidden="true"></i> A mettre a jour</span><span class="value">{{ $counts['update'] }}</span><small>deja presents dans l'ERP</small></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-alert-triangle" aria-hidden="true"></i> Lignes en erreur</span><span class="value {{ $counts['error'] ? 'text-danger' : '' }}">{{ $counts['error'] }}</span><small>{{ $counts['error'] ? 'elles seront ignorees' : 'aucune' }}</small></div>
    </div>

    @if($unknown)
        <div class="alert alert-warning">Colonne(s) non reconnue(s), ignoree(s) : {{ implode(', ', $unknown) }}.</div>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        @if($counts['create'] + $counts['update'] > 0)
            <form method="POST" action="{{ route('erp.imports.confirm', $type) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <button type="submit" class="btn btn-primary"><i class="fe fe-check" aria-hidden="true"></i>
                    Importer {{ $counts['create'] + $counts['update'] }} ligne(s){{ $counts['error'] ? ' (sans les erreurs)' : '' }}</button>
            </form>
        @endif
        <a href="{{ route('erp.imports.index', ['type' => $type]) }}#import-{{ $type }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler et changer de fichier</a>
    </div>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th style="width:90px">Ligne Excel</th><th>Element</th><th style="width:170px">Ce qui va se passer</th><th>Detail</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr class="{{ $row['action'] === 'error' ? 'erp-row-refused' : '' }}">
                    <td class="erp-num">{{ $row['line'] }}</td>
                    <td class="fw-semibold">{{ $row['label'] ?: '-' }}</td>
                    <td>
                        @switch($row['action'])
                            @case('create')<span class="text-success fw-semibold"><i class="fe fe-plus-circle" aria-hidden="true"></i> Creation</span>@break
                            @case('update')<span class="text-primary fw-semibold"><i class="fe fe-edit-2" aria-hidden="true"></i> Mise a jour</span>@break
                            @default<span class="text-danger fw-semibold"><i class="fe fe-x-circle" aria-hidden="true"></i> Ignoree</span>
                        @endswitch
                    </td>
                    <td class="{{ $row['errors'] ? 'text-danger' : 'text-muted' }}">{{ $row['errors'] ? implode(' ; ', $row['errors']) : 'OK' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection
