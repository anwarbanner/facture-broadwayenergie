@extends('layouts.master')

@section('title', 'Accueil')

{{-- Simple mode home page (HomeController@index): three big buttons and the latest quotes. --}}
@section('content')
@php($canCashIn = (bool) auth()->user()?->can('manage-finance'))
<div class="container-fluid erp-pro-page erp-simple-home">
    <h3 class="erp-simple-hello">Bonjour {{ \Illuminate\Support\Str::before(trim((string) auth()->user()?->name), ' ') }}, que voulez-vous faire ?</h3>

    <div class="erp-simple-tiles">
        <a href="{{ route('erp.sales-quotes.create') }}" class="erp-simple-tile erp-simple-tile-main">
            <i class="fe fe-plus-circle" aria-hidden="true"></i>
            <strong>Nouveau devis</strong>
            <span>Creer un devis pour un client</span>
        </a>
        <a href="{{ route('erp.sales-quotes.index') }}" class="erp-simple-tile">
            <i class="fe fe-file-text" aria-hidden="true"></i>
            <strong>Mes devis</strong>
            <span>Retrouver, imprimer, encaisser</span>
        </a>
        <a href="{{ route('erp.contacts.index', ['type' => 'client']) }}" class="erp-simple-tile">
            <i class="fe fe-users" aria-hidden="true"></i>
            <strong>Mes clients</strong>
            <span>Liste des clients</span>
        </a>
    </div>

    <div class="card erp-table-shell">
        <div class="card-body table-responsive">
            <h5 class="mb-3">Derniers devis</h5>
            <table class="table table-hover align-middle mb-0 erp-simple-table">
                <thead><tr><th>Devis</th><th>Client</th><th>Date</th><th class="text-end">Montant TTC</th><th>Etat</th><th></th></tr></thead>
                <tbody>
                @forelse($quotes as $quote)
                    <tr>
                        <td><a href="{{ route('erp.sales-quotes.show', $quote) }}">{{ $quote->quote_number }}</a></td>
                        <td>{{ $quote->client->company_name ?? '-' }}</td>
                        <td>{{ \App\Support\Format::date($quote->date) }}</td>
                        <td class="erp-num">{{ \App\Support\Format::money($quote->total_ttc) }}</td>
                        <td>{{ $quote->simpleStatusBadge() }}</td>
                        <td class="erp-actions">
                            <a href="{{ route('erp.sales-quotes.print', $quote) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" data-no-loader>
                                <i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
                            @if($canCashIn && $quote->canBeCashedIn())
                                <a href="{{ route('erp.sales-quotes.show', $quote) }}#encaisser" class="btn btn-sm btn-success">
                                    <i class="fe fe-dollar-sign" aria-hidden="true"></i> Encaisser</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4">Aucun devis pour le moment. Cliquez sur <strong>Nouveau devis</strong> pour commencer.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
