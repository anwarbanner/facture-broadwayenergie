@csrf

<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">Facture client</span>
    </div>
    <div class="erp-doc-body">
@php($simple = \App\Support\Branding::simpleMode())
<div class="row">
    @unless($simple)
    <div class="col-md-3 mb-3">
        <label class="form-label">Numero de facture</label>
        <input type="text" class="form-control" value="{{ ($invoice->exists ?? false) && !str_starts_with((string) $invoice->invoice_number, 'BROUILLON-') ? $invoice->invoice_number : '' }}" readonly placeholder="Attribue a la validation">
    </div>
    @endunless
    <div class="{{ $simple ? 'col-md-6' : 'col-md-3' }} mb-3">
        @include('erp.partials.contact_select', ['name' => 'client_id', 'type' => 'client', 'contacts' => $clients, 'selected' => $invoice->client_id ?? ''])
    </div>
    @unless($simple)
    <div class="col-md-3 mb-3">
        <label class="form-label">Commande</label>
        <select name="sales_order_id" class="form-control searchable-select">
            <option value="">-- Aucun --</option>
            @foreach($orders as $order)
                <option value="{{ $order->id }}" {{ (string) old('sales_order_id', $invoice->sales_order_id ?? '') === (string) $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Livraison</label>
        <select name="delivery_id" class="form-control searchable-select">
            <option value="">-- Aucun --</option>
            @foreach($deliveries as $delivery)
                <option value="{{ $delivery->id }}" {{ (string) old('delivery_id', $invoice->delivery_id ?? '') === (string) $delivery->id ? 'selected' : '' }}>{{ $delivery->delivery_number }}</option>
            @endforeach
        </select>
    </div>
    @endunless
    <div class="col-md-2 mb-3">
        <label class="form-label">Date de facture</label>
        <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', isset($invoice) && $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Date d'echeance</label>
        <input type="date" name="due_date" class="form-control" value="{{ old('due_date', isset($invoice) && $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('Y-m-d') : '') }}">
    </div>
    @unless($simple)
    <div class="col-md-2 mb-3">
        <label class="form-label">Etat</label>
        <input type="text" class="form-control" value="Brouillon" readonly>
        <small class="text-muted">Le statut de paiement est calcule apres validation.</small>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Conditions de paiement</label>
        <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms', $invoice->payment_terms ?? '') }}">
    </div>
    @endunless
</div>

@include('erp.partials.document_lines', ['document' => $invoice, 'side' => 'sales'])

<div class="erp-doc-actions">
    <button class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}" type="submit"><i class="fe fe-save" aria-hidden="true"></i> {{ $simple ? 'Enregistrer la facture' : 'Enregistrer' }}</button>
    <a href="{{ route('erp.client-invoices.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>


