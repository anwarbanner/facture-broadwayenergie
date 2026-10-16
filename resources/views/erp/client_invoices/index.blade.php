@extends('layouts.master')

@section('title', 'Factures clients')

@section('content')
@php($simple = \App\Support\Branding::simpleMode())
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>{{ $simple ? 'Factures' : 'Factures clients' }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.list-export', ['type' => 'client-invoices'] + request()->only('q', 'status')) }}" class="btn btn-light {{ $simple ? 'btn-lg' : '' }}" data-no-loader>
                <i class="fe fe-download" aria-hidden="true"></i> Exporter la liste en Excel</a>
            <a href="{{ route('erp.client-invoices.create') }}" class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}"><i class="fe fe-plus" aria-hidden="true"></i> {{ $simple ? 'Facture sans devis' : 'Nouvelle facture' }}</a>
        </div>
    </div>
    @if($simple)
        <p class="text-muted">Une facture se fait depuis un devis (<strong>Faire la facture</strong> ou <strong>Encaisser</strong>), ou avec <strong>Facture sans devis</strong>.</p>
    @endif

    @include('erp.partials.list_filter', ['placeholder' => 'Numero de facture ou client', 'statuses' => ['unpaid', 'partial', 'paid', 'overdue']])
    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Numero</th><th>Client</th><th>Date</th><th>Statut</th><th>Total TTC</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->displayNumber() }}@if($invoice->isCreditNote()) <small class="text-muted">(avoir)</small>@endif
                        @if($invoice->quote)<br><small><a href="{{ route('erp.sales-quotes.show', $invoice->quote) }}">Devis {{ $invoice->quote->quote_number }}</a></small>@endif</td>
                    <td>{{ $invoice->client->company_name ?? '-' }}</td>
                    <td>{{ \App\Support\Format::date($invoice->invoice_date) }}</td>
                    <td>{{ \App\Support\Format::statusBadge($invoice->state, $invoice->stateLabel()) }}@unless($invoice->isDraft() || $invoice->isCancelled()) {{ \App\Support\Format::statusBadge($invoice->status, $invoice->statusLabel()) }}@endunless</td>
                    <td class="erp-num">{{ \App\Support\Format::money($invoice->total_ttc) }}</td>
                    <td class="erp-actions">
                        <a href="{{ route('erp.client-invoices.show', $invoice) }}" class="btn btn-sm btn-light"><i class="fe fe-eye" aria-hidden="true"></i> Voir</a>
                        @if($simple)
                        <a href="{{ route('erp.client-invoices.print', $invoice) }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary" data-no-loader><i class="fe fe-printer" aria-hidden="true"></i> Imprimer</a>
                        <a href="{{ route('erp.client-invoices.excel', ['id' => $invoice->id]) }}" class="btn btn-sm btn-light" data-no-loader><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
                        @else
                        <a href="{{ route('erp.client-invoices.pdf', $invoice) }}" class="btn btn-sm btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
                        @endif
                        @if($invoice->isDraft())
                        <a href="{{ route('erp.client-invoices.edit', $invoice) }}" class="btn btn-sm btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
                        <form method="POST" action="{{ route('erp.client-invoices.destroy', $invoice) }}" onsubmit="return confirm('Supprimer ce brouillon ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center">Aucune facture trouvee</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $invoices->links() }}
    </div></div>
</div>
@endsection



