@extends('layouts.master')

@section('title', 'Demandes d\'achat')

@section('content')
@php($simple = \App\Support\Branding::simpleMode())
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>{{ $simple ? 'Devis fournisseurs' : 'Demandes d\'achat' }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.list-export', ['type' => 'purchase-quotes'] + request()->only('q', 'status')) }}" class="btn btn-light {{ $simple ? 'btn-lg' : '' }}" data-no-loader>
                <i class="fe fe-download" aria-hidden="true"></i> Exporter la liste en Excel</a>
            <a href="{{ route('erp.purchase-quotes.create') }}" class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}"><i class="fe fe-plus" aria-hidden="true"></i> {{ $simple ? 'Nouveau devis fournisseur' : 'Nouvelle demande d\'achat' }}</a>
        </div>
    </div>
    @include('erp.partials.list_filter', ['placeholder' => 'Numero de demande ou fournisseur', 'statuses' => ['draft', 'sent', 'accepted', 'rejected']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>Fournisseur</th><th>Date</th><th>Statut</th><th>Total TTC</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($quotes as $quote)
                <tr>
                    <td>{{ $quote->quote_number }}</td>
                    <td>{{ $quote->supplier->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($quote->date) }}</td>
                    <td>@if($quote->invoice){{ \App\Support\Format::statusBadge(round((float) $quote->invoice->balance_remaining, 2) <= 0 ? 'paid' : 'unpaid', round((float) $quote->invoice->balance_remaining, 2) <= 0 ? 'Paye' : 'Facture') }}@else{{ \App\Support\Format::statusBadge($quote->status) }}@endif</td>
                    <td class="erp-num">{{ \App\Support\Format::money($quote->total_ttc) }}</td>
                    <td class="erp-actions">
                        @if($simple)
                        <a href="{{ route('erp.purchase-quotes.show', $quote) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Ouvrir</a>
                        <a href="{{ route('erp.purchase-quotes.print', $quote) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
                        <a href="{{ route('erp.purchase-quotes.excel', ['id' => $quote->id]) }}" class="btn btn-sm btn-light" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
                        @else
                        <a href="{{ route('erp.purchase-quotes.show', $quote) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.purchase-quotes.edit', $quote) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @if($quote->order)
                            <a href="{{ route('erp.purchase-orders.show', $quote->order) }}" class="btn btn-sm btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir commande achat</a>
                        @elseif(!in_array($quote->status, ['rejected', 'converted']))
                            <form method="POST" action="{{ route('erp.purchase-quotes.convert-to-order', $quote) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers commande</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('erp.purchase-quotes.destroy', $quote) }}" onsubmit="return confirm('Supprimer cette demande d\'achat ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">{{ $simple ? 'Aucun devis fournisseur' : 'Aucune demande d\'achat trouvee' }}</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $quotes->links() }}
    </div></div>
</div>
@endsection


