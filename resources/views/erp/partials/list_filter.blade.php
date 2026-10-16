{{--
    Filter bar of the document lists (FiltersDocuments).
    @param string $placeholder
    @param array $statuses stored status codes offered in the filter
--}}
<form method="GET" class="mb-3 erp-filter" role="search">
    <input type="search" name="q" class="form-control" style="max-width:340px" value="{{ request('q') }}"
           placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
    <select name="status" class="form-control no-search" data-no-search style="max-width:200px" aria-label="Filtrer par statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach($statuses as $status)
            <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\Format::label($status) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-primary"><i class="fe fe-search" aria-hidden="true"></i> Rechercher</button>
    @if(request()->filled('q') || request()->filled('status'))
        <a href="{{ url()->current() }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Effacer</a>
    @endif
</form>
