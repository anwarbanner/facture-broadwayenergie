@extends('layouts.master')

@section('title', 'Faire un bon de livraison')

{{-- Simple mode (DeliveryController@chooseQuote): the quotes that have no BL yet, one click each. --}}
@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Faire un bon de livraison</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.deliveries.index') }}" class="btn btn-light btn-lg"><i class="fe fe-arrow-left" aria-hidden="true"></i> Retour</a>
            <a href="{{ route('erp.deliveries.simple-create') }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-3" aria-hidden="true"></i> BL sans devis</a>
        </div>
    </div>
    <p class="text-muted">Choisissez le devis : le bon de livraison reprend ses articles (sans les prix). Le stock ne change pas.
        Pas de devis ? Cliquez sur <strong>BL sans devis</strong>.</p>

    <form method="GET" class="row g-2 mb-3 erp-filter">
        <div class="col-md-6"><input type="search" name="q" class="form-control form-control-lg" aria-label="Rechercher un devis" value="{{ request('q') }}" placeholder="Numero de devis ou nom du client"></div>
        <div class="col-md-2"><button class="btn btn-light btn-lg"><i class="fe fe-search" aria-hidden="true"></i> Rechercher</button></div>
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0 erp-simple-table">
            <thead><tr><th>Devis</th><th>Client</th><th>Date</th><th class="text-end">Montant TTC</th><th></th></tr></thead>
            <tbody>
            @forelse($quotes as $quote)
                <tr>
                    <td><a href="{{ route('erp.sales-quotes.show', $quote) }}">{{ $quote->quote_number }}</a></td>
                    <td>{{ $quote->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($quote->date) }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($quote->total_ttc) }}</td>
                    <td class="erp-actions">
                        <form method="POST" action="{{ route('erp.sales-quotes.delivery', $quote) }}">
                            @csrf
                            <input type="hidden" name="open" value="delivery">
                            <button type="submit" class="btn btn-primary"><i class="fe fe-truck" aria-hidden="true"></i> Faire le BL</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-4">
                    Tous les devis ont deja leur bon de livraison.
                    <a href="{{ route('erp.sales-quotes.create') }}" class="btn btn-primary ms-2"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau devis</a>
                </td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $quotes->links() }}
    </div></div>
</div>
@endsection
