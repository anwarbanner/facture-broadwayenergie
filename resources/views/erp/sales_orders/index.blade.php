@extends('layouts.master')

@section('title', 'Commandes clients')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Commandes clients</h4>
        <a href="{{ route('erp.sales-orders.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouvelle commande</a>
    </div>

    @include('erp.partials.list_filter', ['placeholder' => 'Numero de commande ou client', 'statuses' => ['draft', 'confirmed', 'partial', 'delivered', 'cancelled']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>Client</th><th>Date</th><th>Statut</th><th>Total TTC</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ $order->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($order->order_date) }}</td>
                    <td>{{ \App\Support\Format::statusBadge($order->status) }}</td>
                    <td class="erp-num">{{ \App\Support\Format::money($order->total_ttc) }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('erp.sales-orders.show', $order) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.sales-orders.edit', $order) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @if($order->delivery)
                            <a href="{{ route('erp.deliveries.show', $order->delivery) }}" class="btn btn-sm btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir livraison</a>
                        @elseif(in_array($order->status, ['draft', 'confirmed', 'partial']))
                            <form method="POST" action="{{ route('erp.sales-orders.convert-to-delivery', $order) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers livraison</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('erp.sales-orders.destroy', $order) }}" onsubmit="return confirm('Supprimer cette commande ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucune commande trouvee</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $orders->links() }}
    </div></div>
</div>
@endsection



