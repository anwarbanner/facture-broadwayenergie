@extends('layouts.master')

@section('title', 'Utilisateurs')

@php
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
@endphp

@section('content')
<div class="container-fluid erp-pro-page erp-users">
    <div class="erp-list-head erp-page-head">
        <div>
            <h4>Utilisateurs</h4>
            <small class="text-muted">Les personnes qui ont acces a l'ERP, leur role et leur derniere connexion.</small>
        </div>
        <div class="erp-page-actions">
            <a href="{{ route('roles.index') }}" class="btn btn-light"><i class="fe fe-shield" aria-hidden="true"></i> Roles et droits</a>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="fe fe-user-plus" aria-hidden="true"></i> Nouvel utilisateur</a>
        </div>
    </div>

    <div class="erp-kpis mb-3">
        <div class="erp-kpi"><span class="label">Utilisateurs</span><span class="value">{{ $counts['total'] }}</span></div>
        <div class="erp-kpi"><span class="label">Actifs</span><span class="value text-success">{{ $counts['active'] }}</span></div>
        <div class="erp-kpi"><span class="label">Administrateurs</span><span class="value">{{ $counts['admins'] }}</span></div>
        <a class="erp-kpi erp-kpi-link" href="{{ route('users.index', ['status' => 'unactive']) }}">
            <span class="label">Inactifs / en attente</span><span class="value {{ $counts['inactive'] ? 'text-warning' : '' }}">{{ $counts['inactive'] }}</span>
            <small>comptes crees par inscription a activer</small>
        </a>
    </div>

    <form method="GET" class="mb-3 erp-filter" role="search">
        <input type="search" name="q" class="form-control" style="max-width:320px" value="{{ request('q') }}" placeholder="Nom ou e-mail" aria-label="Rechercher un utilisateur">
        <select name="role" class="form-control no-search" data-no-search style="max-width:200px" aria-label="Filtrer par role" onchange="this.form.submit()">
            <option value="">Tous les roles</option>
            @foreach($roles as $role => $description)
                <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role === 'Admin' ? 'Administrateur' : $role }}</option>
            @endforeach
        </select>
        <select name="status" class="form-control no-search" data-no-search style="max-width:180px" aria-label="Filtrer par statut" onchange="this.form.submit()">
            <option value="">Tous les statuts</option>
            @foreach($statuses as $code => $label)
                <option value="{{ $code }}" @selected(request('status') === $code)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary"><i class="fe fe-search" aria-hidden="true"></i> Rechercher</button>
        @if(request()->hasAny(['q', 'role', 'status']))
            <a href="{{ route('users.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Effacer</a>
        @endif
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Utilisateur</th><th>Role</th><th>Statut</th><th>Derniere connexion</th><th>Cree le</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($users as $user)
                @php($role = $user->roles->first()?->name)
                <tr>
                    <td>
                        <a href="{{ route('users.show', $user) }}" class="erp-user-cell">
                            <span class="erp-user-avatar {{ $role === 'Admin' ? 'is-admin' : '' }}" aria-hidden="true">{{ $initials($user->name) }}</span>
                            <span>
                                <span class="d-block fw-semibold">{{ $user->name }}@if($user->is(auth()->user())) <small class="text-muted">(vous)</small>@endif</span>
                                <small class="text-muted">{{ $user->email }}</small>
                            </span>
                        </a>
                    </td>
                    <td>{{ \App\Support\Format::statusBadge($role === 'Admin' ? 'converted' : 'sent', $role === 'Admin' ? 'Administrateur' : ($role ?? 'Aucun')) }}</td>
                    <td>{{ \App\Support\Format::statusBadge($user->status === 'active' ? 'active' : 'inactive', $statuses[$user->status] ?? $user->status) }}</td>
                    <td>
                        @if($last = $lastLogins[$user->id] ?? null)
                            {{ $last->locale('fr')->diffForHumans() }}<small class="d-block text-muted">{{ $last->format('d/m/Y H:i') }}</small>
                        @else
                            <span class="text-muted">Jamais</span>
                        @endif
                    </td>
                    <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('users.show', $user) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @unless($user->is(auth()->user()))
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Supprimer le compte de {{ addslashes($user->name) }} ? Il ne pourra plus se connecter.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-4">Aucun utilisateur ne correspond a la recherche.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $users->links() }}</div>
</div>
@endsection
