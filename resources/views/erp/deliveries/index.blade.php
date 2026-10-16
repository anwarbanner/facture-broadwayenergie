@extends('layouts.master')

@section('title', 'Livraisons')

@section('content')
@php($simple = \App\Support\Branding::simpleMode())
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>{{ $simple ? 'Bons de livraison' : 'Livraisons' }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.list-export', ['type' => 'deliveries'] + request()->only('q', 'status')) }}" class="btn btn-light {{ $simple ? 'btn-lg' : '' }}" data-no-loader>
                <i class="fe fe-download" aria-hidden="true"></i> Exporter la liste en Excel</a>
            @if($simple)
            <a href="{{ route('erp.deliveries.simple-create') }}" class="btn btn-outline-primary btn-lg"><i class="fe fe-edit-3" aria-hidden="true"></i> BL sans devis</a>
            <a href="{{ route('erp.deliveries.choose-quote') }}" class="btn btn-primary btn-lg"><i class="fe fe-plus" aria-hidden="true"></i> BL depuis un devis</a>
            @else
            <a href="{{ route('erp.deliveries.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouvelle livraison</a>
            @endif
        </div>
    </div>

    @include('erp.partials.list_filter', ['placeholder' => 'Numero de livraison ou client', 'statuses' => ['pending', 'shipped', 'delivered', 'returned']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>{{ $simple ? 'Devis' : 'Commande' }}</th><th>Client</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($deliveries as $delivery)
                <tr>
                    <td>{{ $delivery->delivery_number }}</td>
                    <td>{{ $delivery->quote->quote_number ?? $delivery->order->order_number ?? '-' }}</td>
                    <td>{{ $delivery->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($delivery->delivery_date) }}</td>
                    <td>{{ \App\Support\Format::statusBadge($delivery->status) }}</td>
                    <td class="erp-actions">
                        @if($simple)
                        <a href="{{ route('erp.deliveries.show', $delivery) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Ouvrir</a>
                        <a href="{{ route('erp.deliveries.print', $delivery) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
                        <a href="{{ route('erp.deliveries.excel', ['id' => $delivery->id]) }}" class="btn btn-sm btn-light" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
                        @else
                        <a href="{{ route('erp.deliveries.show', $delivery) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.deliveries.edit', $delivery) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @if($delivery->clientInvoice)
                            <a href="{{ route('erp.client-invoices.show', $delivery->clientInvoice) }}" class="btn btn-sm btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir facture</a>
                        @elseif(in_array($delivery->status, ['pending', 'shipped', 'delivered']))
                            <form method="POST" action="{{ route('erp.deliveries.convert-to-invoice', $delivery) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers facture</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('erp.deliveries.destroy', $delivery) }}" onsubmit="return confirm('Supprimer cette livraison ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucune livraison trouvee</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $deliveries->links() }}
    </div></div>
</div>
@endsection



