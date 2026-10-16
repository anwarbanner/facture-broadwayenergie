@extends('layouts.master')

@section('title', $side === 'client' ? 'Encaisser un client' : 'Payer un fournisseur')

@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ $side === 'client' ? 'Encaisser un client' : 'Payer un fournisseur' }}</h4>

    {{-- Step 1: pick the client / supplier (reloads the page with its open invoices). --}}
    <form method="GET" action="{{ route('erp.payments.create') }}" class="card mb-3"><div class="card-body row g-2 align-items-end">
        <input type="hidden" name="side" value="{{ $side }}">
        <div class="col-md-6">
            <label class="form-label">{{ $side === 'client' ? 'Client' : 'Fournisseur' }}</label>
            <select name="contact_id" class="form-control searchable-select" onchange="this.form.submit()" required>
                <option value="">-- Selectionner --</option>
                @foreach($contacts as $item)
                    <option value="{{ $item->id }}" {{ $contact && $contact->id === $item->id ? 'selected' : '' }}>{{ $item->company_name }}</option>
                @endforeach
            </select>
        </div>
    </div></form>

    @if($contact)
        {{-- Step 2: one payment, spread over the open invoices. --}}
        <form method="POST" action="{{ route('erp.payments.store') }}">
            @csrf
            <input type="hidden" name="side" value="{{ $side }}">
            <input type="hidden" name="contact_id" value="{{ $contact->id }}">

            <div class="card mb-3"><div class="card-body row g-2">
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select name="method" class="form-control" required>
                        @foreach(\App\Models\Payment::METHODS as $value => $label)
                            <option value="{{ $value }}" {{ old('method', 'transfer') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Reference (n° cheque, virement...)</label>
                    <input type="text" name="reference" class="form-control" value="{{ old('reference') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                </div>
            </div></div>

            <div class="card erp-table-shell"><div class="card-body table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Facture</th><th>Date</th><th>Echeance</th><th>Statut</th><th class="text-end">Total TTC</th><th class="text-end">Solde restant</th><th style="width:180px">Montant a regler</th></tr></thead>
                    <tbody>
                    @forelse($openInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                            <td>{{ $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d/m/Y') : '-' }}</td>
                            <td>{{ \App\Support\Format::statusBadge($invoice->status, $invoice->statusLabel()) }}</td>
                            <td class="text-end">{{ \App\Support\Format::money($invoice->total_ttc) }}</td>
                            <td class="text-end">{{ \App\Support\Format::money($invoice->balance_remaining) }}</td>
                            <td>
                                <input type="number" step="0.01" min="0" max="{{ $invoice->balance_remaining }}"
                                       name="amounts[{{ $invoice->id }}]" class="form-control" value="{{ old('amounts.' . $invoice->id) }}">
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">Aucune facture validee avec un solde restant pour ce contacts.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>

            @if($openInvoices->isNotEmpty())
                <div class="mt-3"><button class="btn btn-primary"><i class="fe fe-check" aria-hidden="true"></i> Enregistrer le reglement</button></div>
            @endif
        </form>
    @endif
</div>
@endsection
