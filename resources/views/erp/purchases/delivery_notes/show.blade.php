@extends('layouts.master')

@section('title', 'Details bon reception')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <h4>{{ $note->receipt_number }}</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.delivery-notes.pdf', $note) }}" class="btn btn-light"><i class="fe fe-file-text" aria-hidden="true"></i> PDF</a>
            <a href="{{ route('erp.delivery-notes.excel', ['id' => $note->id]) }}" class="btn btn-light" title="Telecharger au format Excel"><i class="fe fe-grid" aria-hidden="true"></i> Excel</a>
            <a href="{{ route('erp.delivery-notes.edit', $note) }}" class="btn btn-outline-primary"><i class="fe fe-edit-2" aria-hidden="true"></i> Modifier</a>
            @if($note->supplierInvoice)
                <a href="{{ route('erp.supplier-invoices.show', $note->supplierInvoice) }}" class="btn btn-light"><i class="fe fe-external-link" aria-hidden="true"></i> Voir facture fournisseur</a>
            @elseif(in_array($note->status, ['partial', 'complete']))
                <form method="POST" action="{{ route('erp.delivery-notes.convert-to-invoice', $note) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="fe fe-arrow-right" aria-hidden="true"></i> Creer facture fournisseur</button>
                </form>
            @endif
        </div>
    </div>

    <div class="erp-doc-sheet">
        <div class="erp-doc-header">
            <h6>Informations bon de reception</h6>
            <span class="erp-doc-badge">Bon reception</span>
        </div>
        <div class="erp-doc-body">
            <div class="row">
                <div class="col-lg-12 mb-3">
                    <div class="erp-doc-lines">
                        <h6>Resume du document</h6>
                        <div class="erp-kv-grid">
                            <div class="erp-kv-item"><span class="label">Numero</span><span class="value">{{ $note->receipt_number }}</span></div>
                            <div class="erp-kv-item"><span class="label">Statut</span><span class="value">{{ \App\Support\Format::statusBadge($note->status) }}</span></div>
                            <div class="erp-kv-item"><span class="label">Date reception</span><span class="value">{{ optional($note->delivery_date)->format('d/m/Y') ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Commande source</span><span class="value">{{ $note->order->order_number ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Fournisseur</span><span class="value">{{ $note->supplier->company_name ?? '-' }}</span></div>
                            <div class="erp-kv-item"><span class="label">Facture liee</span><span class="value">{{ $note->supplierInvoice->invoice_number ?? '-' }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-doc-lines mt-2">
                <h6>Articles du bon de reception</h6>
                <div class="table-responsive">
                    <table class="table table-sm erp-lines-table mb-0">
                        <thead>
                            <tr>
                                <th>Article</th>
                                <th class="text-end">Qte commandee</th>
                                <th class="text-end">Qte recue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($note->items as $item)
                                <tr>
                                    <td>
                                        {{ \App\Support\Format::designation($item) }}
                                        @if(!$item->product_id)
                                            <span class="badge bg-secondary ms-1">Manuel</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->quantity) }}</td>
                                    <td class="text-end">{{ \App\Support\Format::qty($item->received_qty) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center">Aucun article.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row justify-content-end mt-3">
                <div class="col-lg-4">
                    <div class="erp-summary-card">
                        <h6>Execution</h6>
                        <div class="erp-summary-row"><span>Lignes</span><strong>{{ $note->items->count() }}</strong></div>
                        <div class="erp-summary-row"><span>Qte commandee</span><strong>{{ \App\Support\Format::qty($note->items->sum('quantity')) }}</strong></div>
                        <div class="erp-summary-row total"><span>Qte recue</span><strong>{{ \App\Support\Format::qty($note->items->sum('received_qty')) }}</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
