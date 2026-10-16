@extends('layouts.master')

@section('title', 'Journal d\'activite')

@section('content')
<div class="container-fluid erp-pro-page erp-activity">
    <div class="erp-page-head">
        <div>
            <h4>Journal d'activite</h4>
            <small class="text-muted">Qui a fait quoi, sur quel document, quand et depuis quel appareil.</small>
        </div>
    </div>

    {{-- Figures of the period --}}
    <div class="erp-kpis mb-3">
        <div class="erp-kpi"><span class="label">Actions</span><span class="value">{{ $stats['total'] }}</span><small>{{ $stats['users'] }} utilisateur(s) actif(s)</small></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-plus-circle" aria-hidden="true"></i> Creations</span><span class="value">{{ $stats['create'] }}</span></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifications</span><span class="value">{{ $stats['update'] }}</span></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-trash-2" aria-hidden="true"></i> Suppressions</span><span class="value">{{ $stats['delete'] }}</span></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-dollar-sign" aria-hidden="true"></i> Finance et transformations</span><span class="value">{{ $stats['finance'] }}</span></div>
        <div class="erp-kpi"><span class="label"><i class="fe fe-log-in" aria-hidden="true"></i> Connexions</span><span class="value">{{ $stats['login'] }}</span></div>
        <a class="erp-kpi erp-kpi-link" href="{{ request()->fullUrlWithQuery(['category' => 'refused', 'page' => null]) }}">
            <span class="label"><i class="fe fe-alert-triangle" aria-hidden="true"></i> Refus et echecs</span>
            <span class="value {{ $stats['refused'] > 0 ? 'text-danger' : '' }}">{{ $stats['refused'] }}</span>
            <small>actions refusees, mots de passe incorrects</small>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="erp-filter mb-3" data-activity-filter>
        <label class="mb-0">
            <span class="d-block small text-muted">Periode</span>
            <select name="period" class="form-control no-search" data-no-search data-period>
                @foreach($periods as $key => $label)
                    <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="mb-0 {{ $period === 'custom' ? '' : 'd-none' }}" data-custom-date>
            <span class="d-block small text-muted">Du</span>
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
        </label>
        <label class="mb-0 {{ $period === 'custom' ? '' : 'd-none' }}" data-custom-date>
            <span class="d-block small text-muted">Au</span>
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
        </label>
        <label class="mb-0">
            <span class="d-block small text-muted">Utilisateur</span>
            <select name="user_id" class="form-control no-search" data-no-search>
                <option value="">Tous</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="mb-0">
            <span class="d-block small text-muted">Type d'action</span>
            <select name="category" class="form-control no-search" data-no-search>
                <option value="">Toutes</option>
                @foreach($categories as $key => [$label])
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
                <option value="refused" @selected(request('category') === 'refused')>Refus et echecs</option>
            </select>
        </label>
        <label class="mb-0 flex-grow-1">
            <span class="d-block small text-muted">Rechercher</span>
            <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Numero de document, nom, utilisateur, e-mail...">
        </label>
        <label class="mb-0 align-self-end d-flex align-items-center gap-2 small text-muted" style="min-height:38px">
            <input type="checkbox" name="views" value="1" @checked($withViews)> Afficher aussi les consultations de pages
        </label>
        <button type="submit" class="btn btn-primary align-self-end"><i class="fe fe-search" aria-hidden="true"></i> Afficher</button>
        @if(request()->hasAny(['period', 'user_id', 'category', 'q', 'views', 'from', 'to']))
            <a href="{{ route('activity-logs.index') }}" class="btn btn-light align-self-end"><i class="fe fe-x" aria-hidden="true"></i> Effacer</a>
        @endif
    </form>

    {{-- Journal --}}
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table align-middle mb-0 erp-activity-table">
            <thead>
                <tr><th style="width:84px">Heure</th><th>Utilisateur</th><th>Action</th><th>Document / element</th><th>Detail</th><th>Resultat</th><th>Appareil</th></tr>
            </thead>
            <tbody>
            @php($currentDay = null)
            @forelse($rows as $row)
                @php($log = $row['log'])
                @php($day = $log->created_at?->copy()->locale('fr'))
                @if($day && $day->toDateString() !== $currentDay)
                    @php($currentDay = $day->toDateString())
                    <tr class="erp-day-row"><td colspan="7">
                        {{ $day->isToday() ? 'Aujourd\'hui' : ($day->isYesterday() ? 'Hier' : ucfirst($day->isoFormat('dddd'))) }}
                        <span>{{ $day->isoFormat('D MMMM YYYY') }}</span>
                    </td></tr>
                @endif
                <tr class="{{ $row['refused'] ? 'erp-row-refused' : '' }}">
                    <td class="text-nowrap" title="{{ $day?->isoFormat('dddd D MMMM YYYY [a] HH:mm:ss') }}">
                        <strong>{{ $log->created_at?->format('H:i') }}</strong>
                        <small class="d-block text-muted">{{ $day?->diffForHumans() }}</small>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="erp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($log->user_name ?: '?', 0, 1)) }}</span>
                            <div>
                                <div class="fw-semibold">{{ $log->user_name ?: 'Visiteur non connecte' }}</div>
                                <small class="text-muted">{{ $log->role_name === 'Admin' ? 'Administrateur' : ($log->role_name ?: '-') }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="erp-act-badge tone-{{ $row['tone'] }}"><i class="fe {{ $row['icon'] }}" aria-hidden="true"></i> {{ $row['label'] }}</span>
                        <div class="small mt-1">{{ $row['title'] }}</div>
                    </td>
                    <td>
                        @if($row['subject'])
                            @if($row['subject_url'])
                                <a href="{{ $row['subject_url'] }}" class="fw-semibold">{{ $row['subject'] }}</a>
                            @else
                                <span class="fw-semibold">{{ $row['subject'] }}</span>
                            @endif
                        @else
                            <span class="text-muted">-</span>
                        @endif
                        @if(!empty($log->meta['result_url']))
                            <small class="d-block"><i class="fe fe-arrow-right" aria-hidden="true"></i>
                                <a href="{{ $log->meta['result_url'] }}">{{ $log->meta['result_label'] }}</a></small>
                        @endif
                    </td>
                    <td class="erp-activity-detail">{{ $row['detail'] ?: '-' }}</td>
                    <td class="text-nowrap">
                        @if($row['refused'])
                            <span class="text-danger fw-semibold"><i class="fe fe-x-circle" aria-hidden="true"></i> Refuse</span>
                        @else
                            <span class="text-success fw-semibold"><i class="fe fe-check-circle" aria-hidden="true"></i> Reussi</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $row['device'] }}</div>
                        <small class="text-muted">{{ $row['place'] }}</small>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-4">Aucune activite sur cette periode.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>

<script>
(function () {
    var form = document.querySelector('[data-activity-filter]');
    if (!form) return;
    var period = form.querySelector('[data-period]');
    period.addEventListener('change', function () {
        var custom = period.value === 'custom';
        form.querySelectorAll('[data-custom-date]').forEach(function (el) { el.classList.toggle('d-none', !custom); });
        if (!custom) form.submit();
    });
})();
</script>
@endsection
