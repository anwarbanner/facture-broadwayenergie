@extends('layouts.master')

@section('title', 'Roles et droits')

@php
    $initials = fn ($name) => collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?';
@endphp

@section('content')
<div class="container-fluid erp-pro-page erp-users">
    <div class="erp-page-head">
        <div>
            <h4>Roles et droits</h4>
            <small class="text-muted">Deux roles : chaque personne en a un. Pour changer les droits de quelqu'un, modifiez son role dans Utilisateurs.</small>
        </div>
        <div class="erp-page-actions">
            <a href="{{ route('users.index') }}" class="btn btn-primary"><i class="fe fe-users" aria-hidden="true"></i> Gerer les utilisateurs</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach($roles as $key => $role)
            <div class="col-lg-6">
                <div class="card h-100 erp-role-card {{ $key === 'Admin' ? 'is-admin' : '' }}"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="mb-0"><i class="fe {{ $role['icon'] }}" aria-hidden="true"></i> {{ $role['label'] }}</h5>
                        <a href="{{ route('users.index', ['role' => $key]) }}" class="small">{{ $role['users']->count() }} utilisateur(s)</a>
                    </div>
                    <p class="text-muted mb-3">{{ \Illuminate\Support\Str::after($role['description'], ': ') }}</p>
                    <div class="erp-role-people">
                        @forelse($role['users'] as $user)
                            <a href="{{ route('users.show', $user) }}" class="erp-role-person" title="{{ $user->email }}">
                                <span class="erp-user-avatar {{ $key === 'Admin' ? 'is-admin' : '' }}" aria-hidden="true">{{ $initials($user->name) }}</span>
                                <span>{{ $user->name }}@if($user->status !== 'active') <small class="text-muted">(inactif)</small>@endif</span>
                            </a>
                        @empty
                            <span class="text-muted small">Personne n'a ce role.</span>
                        @endforelse
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <h6 class="mb-2">Qui peut faire quoi</h6>
        <table class="table align-middle mb-0 erp-rights-table">
            <thead><tr><th>Domaine</th><th>Contenu</th><th class="text-center">Administrateur</th><th class="text-center">Technicien</th></tr></thead>
            <tbody>
            @foreach($matrix as [$area, $detail, $admin, $tech])
                <tr>
                    <td class="fw-semibold">{{ $area }}</td>
                    <td class="text-muted">{{ $detail }}</td>
                    @foreach([$admin, $tech] as $allowed)
                        <td class="text-center">
                            @if($allowed)
                                <span class="erp-right yes"><i class="fe fe-check" aria-hidden="true"></i> Oui</span>
                            @else
                                <span class="erp-right no"><i class="fe fe-x" aria-hidden="true"></i> Non</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection
