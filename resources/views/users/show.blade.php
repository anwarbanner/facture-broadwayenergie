@extends('layouts.master')

@section('title', $user->name)

@php
    $role = $user->roles->first()?->name;
    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
@endphp

@section('content')
<div class="container-fluid erp-pro-page erp-users">
    <div class="erp-page-head">
        <div class="erp-user-cell">
            <span class="erp-user-avatar erp-user-avatar-lg {{ $role === 'Admin' ? 'is-admin' : '' }}" aria-hidden="true">{{ $initials }}</span>
            <span>
                <h4 class="mb-1">{{ $user->name }}@if($user->is(auth()->user())) <small class="text-muted fw-normal">(vous)</small>@endif</h4>
                <span class="d-block text-muted small mb-1">{{ $user->email }}</span>
                {{ \App\Support\Format::statusBadge($role === 'Admin' ? 'converted' : 'sent', $role === 'Admin' ? 'Administrateur' : ($role ?? 'Aucun role')) }}
                {{ \App\Support\Format::statusBadge($user->status === 'active' ? 'active' : 'inactive', $user->status === 'active' ? 'Actif' : 'Inactif') }}
            </span>
        </div>
        <div class="erp-page-actions">
            <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @unless($user->is(auth()->user()))
                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Supprimer le compte de {{ addslashes($user->name) }} ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                </form>
            @endunless
        </div>
    </div>

    <div class="erp-kpis mb-3">
        <div class="erp-kpi"><span class="label">Derniere connexion</span>
            <span class="value" style="font-size:1.05rem">{{ $lastLogin ? $lastLogin->locale('fr')->diffForHumans() : 'Jamais' }}</span>
            @if($lastLogin)<small>{{ $lastLogin->format('d/m/Y H:i') }}</small>@endif
        </div>
        <div class="erp-kpi"><span class="label">Actions enregistrees</span><span class="value">{{ $actionsCount }}</span><small>creations, modifications, finance...</small></div>
        <div class="erp-kpi"><span class="label">Membre depuis</span><span class="value" style="font-size:1.05rem">{{ $user->created_at?->locale('fr')->isoFormat('D MMMM YYYY') }}</span></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card h-100"><div class="card-body">
                <h6 class="mb-2"><i class="fe fe-shield" aria-hidden="true"></i> Ce que ce compte peut faire</h6>
                <p class="mb-2">{{ $roles[$role] ?? 'Aucun role : ce compte ne peut rien faire.' }}</p>
                <a href="{{ route('roles.index') }}" class="small">Voir le detail des droits <i class="fe fe-arrow-right" aria-hidden="true"></i></a>
            </div></div>
        </div>
        <div class="col-xl-8">
            <div class="card erp-table-shell"><div class="card-body table-responsive">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Activite recente</h6>
                    <a href="{{ route('activity-logs.index', ['user_id' => $user->id, 'period' => 'all']) }}" class="small">Tout le journal <i class="fe fe-arrow-right" aria-hidden="true"></i></a>
                </div>
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Quand</th><th>Action</th><th>Document</th><th>Resultat</th></tr></thead>
                    <tbody>
                    @forelse($activity as $log)
                        @php($row = \App\Support\ActivityLogPresenter::present($log))
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at?->locale('fr')->diffForHumans() }}<small class="d-block text-muted">{{ $log->created_at?->format('d/m/Y H:i') }}</small></td>
                            <td>{{ $row['title'] }}</td>
                            <td>
                                @if($row['subject'] && $row['subject_url'])<a href="{{ $row['subject_url'] }}">{{ $row['subject'] }}</a>@else{{ $row['subject'] ?? '-' }}@endif
                            </td>
                            <td>{{ \App\Support\Format::statusBadge($row['refused'] ? 'rejected' : 'accepted', $row['refused'] ? 'Refuse' : 'Reussi') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center py-3">Aucune activite enregistree pour l'instant.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
</div>
@endsection
