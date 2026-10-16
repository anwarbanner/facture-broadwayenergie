@extends('layouts.master')

@section('title', 'Bons de reception')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Bons de reception</h4>
        <a href="{{ route('erp.delivery-notes.create') }}" class="btn btn-primary"><i class="fe fe-plus" aria-hidden="true"></i> Nouveau bon de reception</a>
    </div>
    @include('erp.partials.list_filter', ['placeholder' => 'Numero de reception ou fournisseur', 'statuses' => ['partial', 'complete']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>Commande</th><th>Fournisseur</th><th>Date</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($notes as $note)
                <tr>
                    <td>{{ $note->receipt_number }}</td>
                    <td>{{ $note->order->order_number ?? '-' }}</td>
                    <td>{{ $note->supplier->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($note->delivery_date) }}</td>
                    <td>{{ \App\Support\Format::statusBadge($note->status) }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('erp.delivery-notes.show', $note) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        <a href="{{ route('erp.delivery-notes.edit', $note) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        @if($note->supplierInvoice)
                            <a href="{{ route('erp.supplier-invoices.show', $note->supplierInvoice) }}" class="btn btn-sm btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir facture fournisseur</a>
                        @elseif(in_array($note->status, ['partial', 'complete']))
                            <form method="POST" action="{{ route('erp.delivery-notes.convert-to-invoice', $note) }}">
                                @csrf
                                <button class="btn btn-sm btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Vers facture</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('erp.delivery-notes.destroy', $note) }}" onsubmit="return confirm('Supprimer ce bon de reception ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucun bon de reception trouve</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $notes->links() }}
    </div></div>
</div>
@endsection


