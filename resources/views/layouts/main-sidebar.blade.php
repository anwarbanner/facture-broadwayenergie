<!-- main-sidebar -->
@php
    // Menu definition: [label, icon, [[label, route, params, active pattern, finance only?], ...]]
    $menu = [
        ['Produits et stock', 'fe-package', [
            ['Produits', 'erp.products.index', ['type' => 'product'], 'erp.products.*'],
            ['Services', 'erp.services.index', [], 'erp.services.*'],
            ['Categories', 'erp.categories.index', [], 'erp.categories.*'],
            ['Mouvements de stock', 'erp.stock-movements.index', [], 'erp.stock-movements.*'],
        ]],
        ['Ventes', 'fe-trending-up', [
            ['Devis clients', 'erp.sales-quotes.index', [], 'erp.sales-quotes.*'],
            ['Commandes clients', 'erp.sales-orders.index', [], 'erp.sales-orders.*'],
            ['Livraisons', 'erp.deliveries.index', [], 'erp.deliveries.*'],
            ['Factures clients', 'erp.client-invoices.index', [], 'erp.client-invoices.*'],
            ['Encaissements', 'erp.payments.index', ['direction' => 'in'], null, true],
            ['Releves clients', 'erp.client-statements.index', [], 'erp.client-statements.*', true],
        ]],
        ['Achats', 'fe-shopping-cart', [
            ['Demandes d\'achat', 'erp.purchase-quotes.index', [], 'erp.purchase-quotes.*'],
            ['Commandes d\'achat', 'erp.purchase-orders.index', [], 'erp.purchase-orders.*'],
            ['Bons de reception', 'erp.delivery-notes.index', [], 'erp.delivery-notes.*'],
            ['Factures fournisseur', 'erp.supplier-invoices.index', [], 'erp.supplier-invoices.*'],
            ['Decaissements', 'erp.payments.index', ['direction' => 'out'], null, true],
            ['Releves fournisseur', 'erp.supplier-statements.index', [], 'erp.supplier-statements.*', true],
        ]],
        ['Comptabilite', 'fe-pie-chart', [
            ['Synthese', 'erp.accounting.index', ['tab' => 'summary'], null, true],
            ['Journal des ventes', 'erp.accounting.index', ['tab' => 'sales'], null, true],
            ['Journal des achats', 'erp.accounting.index', ['tab' => 'purchases'], null, true],
            ['TVA', 'erp.accounting.index', ['tab' => 'vat'], null, true],
            ['Tresorerie', 'erp.accounting.index', ['tab' => 'cash'], null, true],
            ['Echeanciers', 'erp.accounting.index', ['tab' => 'receivables'], null, true],
            ['Classements', 'erp.accounting.index', ['tab' => 'ranking'], null, true],
            ['Ecritures comptables', 'erp.accounting.index', ['tab' => 'entries'], null, true],
        ]],
        ['Contacts', 'fe-users', [
            ['Clients', 'erp.contacts.index', ['type' => 'client'], null],
            ['Fournisseurs', 'erp.contacts.index', ['type' => 'supplier'], null],
        ]],
    ];
    // Simple mode: a short flat menu (big entries, no sub-menus). Admin keeps the Administration group below.
    $simple = \App\Support\Branding::simpleMode();
    $simpleLinks = [
        ['Nouveau devis', 'fe-plus-circle', 'erp.sales-quotes.create', [], 'erp.sales-quotes.create'],
        ['Mes devis', 'fe-file-text', 'erp.sales-quotes.index', [], 'erp.sales-quotes.index|erp.sales-quotes.show|erp.sales-quotes.edit'],
        ['Bons de livraison', 'fe-truck', 'erp.deliveries.index', [], 'erp.deliveries.*'],
        ['Factures', 'fe-check-circle', 'erp.client-invoices.index', [], 'erp.client-invoices.*'],
        ['Devis fournisseurs', 'fe-shopping-cart', 'erp.purchase-quotes.index', [], 'erp.purchase-quotes.*'],
        ['Factures fournisseurs', 'fe-file-minus', 'erp.supplier-invoices.index', [], 'erp.supplier-invoices.*'],
        ['Clients', 'fe-users', 'erp.contacts.index', ['type' => 'client'], 'erp.contacts.*'],
        ['Fournisseurs', 'fe-briefcase', 'erp.contacts.index', ['type' => 'supplier'], 'erp.contacts.*'],
        ['Produits', 'fe-package', 'erp.products.index', ['type' => 'product'], 'erp.products.*|erp.services.*'],
    ];
    // Clients and Fournisseurs share the contact pages: the ?type= of the page (or of the contact shown) decides.
    $contactType = request('type') ?: (request()->route('contact')?->type ?? 'client');
    $simpleActive = fn (string $pattern, array $params) => request()->routeIs(...explode('|', $pattern))
        && (!str_starts_with($pattern, 'erp.contacts') || ($params['type'] ?? null) === $contactType);
    if ($simple) {
        $menu = [];
    }
    if (optional(Auth::user())->hasRole('Admin')) {
        $menu[] = ['Administration', 'fe-settings', [
            ['Utilisateurs', 'users.index', [], 'users.*'],
            ['Roles', 'roles.index', [], 'roles.*'],
            ['Journal d\'activite', 'activity-logs.index', [], 'activity-logs.*'],
            ['Signature et cachet', 'settings.signature.edit', [], 'settings.signature.*'],
            ['Import Excel', 'erp.imports.index', [], 'erp.imports.*'],
        ]];
    }

    // An item is active when its route pattern matches, or (list filters) when route + query both match.
    $isActive = function (array $item): bool {
        [$label, $route, $params, $pattern] = $item;
        if ($pattern) {
            return request()->routeIs($pattern);
        }
        if (!request()->routeIs($route)) {
            return false;
        }
        foreach ($params as $key => $value) {
            if (request()->query($key) !== $value) {
                return false;
            }
        }

        return true;
    };
@endphp
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar sidebar-scroll erp-sidebar">
    <div class="main-sidebar-header active">
        <a class="erp-brand" href="{{ route('dashboard') }}">
            <x-brand-logo />
        </a>
    </div>
    <div class="main-sidemenu">
        <ul class="side-menu">
            <li class="slide">
                <a class="side-menu__item {{ request()->routeIs('dashboard', 'dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="side-menu__icon fe fe-home"></i><span class="side-menu__label">{{ $simple ? 'Accueil' : 'Tableau de bord' }}</span>
                </a>
            </li>

            @if($simple)
                @foreach($simpleLinks as [$label, $icon, $route, $params, $pattern])
                    <li class="slide">
                        <a class="side-menu__item erp-simple-link {{ $simpleActive($pattern, $params) ? 'active' : '' }}" href="{{ route($route, $params) }}">
                            <i class="side-menu__icon fe {{ $icon }}"></i><span class="side-menu__label">{{ $label }}</span>
                        </a>
                    </li>
                @endforeach
            @endif

            @foreach($menu as [$groupLabel, $groupIcon, $items])
                @php
                    $visible = array_filter($items, fn ($item) => empty($item[4]) || auth()->user()?->can('manage-finance'));
                    $groupActive = collect($visible)->contains(fn ($item) => $isActive($item));
                @endphp
                @continue(empty($visible))
                <li class="slide {{ $groupActive ? 'is-expanded' : '' }}">
                    <a class="side-menu__item {{ $groupActive ? 'active' : '' }}" data-toggle="slide" href="javascript:void(0)" role="button" aria-expanded="{{ $groupActive ? 'true' : 'false' }}">
                        <i class="side-menu__icon fe {{ $groupIcon }}"></i><span class="side-menu__label">{{ $groupLabel }}</span><i class="angle fe fe-chevron-down"></i>
                    </a>
                    <ul class="slide-menu {{ $groupActive ? 'open' : '' }}">
                        @foreach($visible as $item)
                            <li><a class="slide-item {{ $isActive($item) ? 'active' : '' }}" href="{{ route($item[1], $item[2]) }}">{{ $item[0] }}</a></li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </div>
</aside>
<!-- /main-sidebar -->
