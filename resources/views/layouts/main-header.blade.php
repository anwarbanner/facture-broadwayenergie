<!-- main-header -->
@php
    $user = Auth::user();
    $userInitials = collect(preg_split('/\s+/', trim((string) optional($user)->name)))->filter()->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: '?';
@endphp
<div class="main-header sticky side-header nav nav-item erp-header">
    <div class="container-fluid erp-header-inner">
        <div class="main-header-left">
            <div class="app-sidebar__toggle" data-toggle="sidebar">
                <a class="open-toggle erp-icon-btn" href="javascript:void(0)" role="button" aria-label="Ouvrir le menu"><i class="fe fe-menu"></i></a>
                <a class="close-toggle erp-icon-btn" href="javascript:void(0)" role="button" aria-label="Fermer le menu"><i class="fe fe-x"></i></a>
            </div>
            <a href="{{ route('dashboard') }}" class="erp-brand erp-brand-mobile d-lg-none" aria-label="Tableau de bord">
                <x-brand-logo :show-name="false" />
            </a>
        </div>

        <div class="main-header-right erp-header-right">
            {{-- Light / dark mode (public/assets/js/erp-ui.js, remembered on this device) --}}
            <button type="button" class="erp-header-btn erp-theme-toggle" data-theme-toggle aria-pressed="false" title="Passer en mode sombre">
                <i class="fe fe-moon" aria-hidden="true" data-theme-icon></i>
                <span class="erp-header-btn-label" data-theme-label>Mode sombre</span>
            </button>

            {{-- User --}}
            <div class="dropdown">
                <button type="button" class="erp-header-btn erp-user-btn" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Compte">
                    <span class="erp-avatar">{{ $userInitials }}</span>
                    <span class="erp-header-btn-label erp-user-name">{{ optional($user)->name }}</span>
                    <i class="fe fe-chevron-down erp-header-caret"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right erp-dropdown erp-user-menu">
                    <div class="erp-user-card">
                        <span class="erp-avatar erp-avatar-lg">{{ $userInitials }}</span>
                        <div>
                            <strong>{{ optional($user)->name }}</strong>
                            <small>{{ optional($user)->email }}</small>
                            <span class="erp-role-chip">{{ optional($user)->getRoleNames()->first() ?? '-' }}</span>
                        </div>
                    </div>
                    @can('manage-finance')
                        <a class="dropdown-item" href="{{ route('users.index') }}"><i class="fe fe-users"></i> Utilisateurs</a>
                    @endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item erp-logout"><i class="fe fe-log-out"></i> Deconnexion</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /main-header -->
