@extends('layouts.master')

@section('title', 'Reglements')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-list-head erp-page-head">
        <h4>Reglements</h4>
        <div class="erp-page-actions">
            <a href="{{ route('erp.payments.create', ['side' => 'client']) }}" class="btn btn-primary"><i class="fe fe-arrow-down-circle" aria-hidden="true"></i> Encaisser un client</a>
            <a href="{{ route('erp.payments.create', ['side' => 'supplier']) }}" class="btn btn-outline-primary"><i class="fe fe-arrow-up-circle" aria-hidden="true"></i> Payer un fournisseur</a>
        </div>
    </div>

    <form method="GET" class="mb-3 erp-filter">
        <select name="direction" class="form-control" aria-label="Sens du paiement" style="max-width:220px" onchange="this.form.submit()">
            <option value="">Tous les sens</option>
            <option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>Encaissements</option>
            <option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>Decaissements</option>
        </select>
        @if(request('contact_id'))
            <input type="hidden" name="contact_id" value="{{ request('contact_id') }}">
            <a href="{{ route('erp.payments.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Tous les contacts</a>
        @endif
    </form>

    <div class="card erp-table-shell"><div class="card-body table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date</th><th>Contact</th><th>Sens</th><th>Mode</th><th>Reference</th><th>Affecte a</th><th class="text-end">Montant</th><th>Etat</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($payments as $payment)
                <tr class="{{ $payment->isVoided() ? 'text-muted' : '' }}">
                    <td>{{ \App\Support\Format::date($payment->payment_date) }}</td>
                    <td><a href="{{ route('erp.payments.index', ['contact_id' => $payment->contact_id]) }}">{{ $payment->contact->company_name ?? '-' }}</a></td>
                    <td>{{ $payment->directionLabel() }}</td>
                    <td>{{ $payment->methodLabel() }}</td>
                    <td>{{ $payment->reference ?: '-' }}</td>
                    <td>
                        @foreach($payment->allocations as $allocation)
                            @php($doc = $allocation->invoice())
                            @if($doc)
                                <a href="{{ route($allocation->client_invoice_id ? 'erp.client-invoices.show' : 'erp.supplier-invoices.show', $doc) }}">{{ $doc->invoice_number }}</a>
                                <small>({{ \App\Support\Format::money($allocation->amount) }})</small>@if(!$loop->last), @endif
                            @endif
                        @endforeach
                    </td>
                    <td class="erp-num">{{ \App\Support\Format::money($payment->amount) }}</td>
                    <td>
                        @if($payment->isVoided())
                            <span class="badge badge-danger bg-danger" title="{{ $payment->void_reason }}">Annule</span>
                        @else
                            <span class="badge badge-success bg-success">Actif</span>
                        @endif
                    </td>
                    <td class="erp-actions">
                        @unless($payment->isVoided())
                            <form method="POST" action="{{ route('erp.payments.void', $payment) }}" class="d-flex" style="gap:.25rem"
                                  onsubmit="return confirm('Annuler ce reglement ? Les factures concernees seront de nouveau dues.')">
                                @csrf
                                <input type="text" name="void_reason" class="form-control form-control-sm" placeholder="Motif" aria-label="Motif de l'annulation" required>
                                <button class="btn btn-sm btn-outline-danger erp-act-danger"><i class="fe fe-x-circle" aria-hidden="true"></i> Annuler</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center">Aucun reglement.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $payments->links() }}
    </div></div>
</div>
@endsection
