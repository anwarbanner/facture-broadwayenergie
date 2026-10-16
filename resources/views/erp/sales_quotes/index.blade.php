@extends('layouts.master')

@section('title', 'Devis clients')

@section('content')
@php($simple = \App\Support\Branding::simpleMode())
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>{{ $simple ? 'Mes devis' : 'Devis clients' }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.list-export', ['type' => 'sales-quotes'] + request()->only('q', 'status')) }}" class="btn btn-light {{ $simple ? 'btn-lg' : '' }}" data-no-loader>
                <i class="fe fe-download" aria-hidden="true"></i> Exporter la liste en Excel</a>
            <a href="{{ route('erp.sales-quotes.create') }}" class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau devis</a>
        </div>
    </div>


    @include('erp.partials.list_filter', ['placeholder' => 'Numero de devis ou client', 'statuses' => ['draft', 'sent', 'accepted', 'rejected', 'converted']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>Client</th><th>Date</th><th>Statut</th><th>Total TTC</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($quotes as $quote)
                <tr>
                    <td>{{ $quote->quote_number }}</td>
                    <td>{{ $quote->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($quote->date) }}</td>
                    <td>{{ $quote->simpleStatusBadge() }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($quote->total_ttc) }}</td>
                    <td class="erp-actions">
                        @if($simple)
                            <a href="{{ route('erp.sales-quotes.show', $quote) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Ouvrir</a>
                            <a href="{{ route('erp.sales-quotes.print', $quote) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
                            @if($quote->canBeCashedIn() && auth()->user()?->can('manage-finance'))
                                <a href="{{ route('erp.sales-quotes.show', $quote) }}#encaisser" class="btn btn-sm btn-success"><i class="fe fe-dollar-sign" aria-hidden="true"></i> Encaisser</a>
                            @endif
                        @else
                        <a href="{{ route('erp.sales-quotes.show', $quote) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.sales-quotes.edit', $quote) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @if($quote->order)
                            <a href="{{ route('erp.sales-orders.show', $quote->order) }}" class="btn btn-sm btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir commande</a>
                        @elseif(!in_array($quote->status, ['rejected', 'converted']))
                            <form method="POST" action="{{ route('erp.sales-quotes.convert-to-order', $quote) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers commande</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('erp.sales-quotes.destroy', $quote) }}" onsubmit="return confirm('Supprimer ce devis ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucun devis trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $quotes->links() }}
    </div></div>
</div>
@endsection



