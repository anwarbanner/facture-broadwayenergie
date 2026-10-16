@csrf

<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">Facture fournisseur</span>
    </div>
    <div class="erp-doc-body">
@php($simple = \App\Support\Branding::simpleMode())
<div class="row">
    @unless($simple)
    <div class="col-md-3 mb-3">
        <label class="form-label">Numero facture fournisseur</label>
        <input type="text" class="form-control" value="{{ ($invoice->exists ?? false) && !str_starts_with((string) $invoice->invoice_number, 'BROUILLON-') ? $invoice->invoice_number : '' }}" readonly placeholder="Attribue a la validation">
    </div>
    @endunless
    <div class="{{ $simple ? 'col-md-6' : 'col-md-3' }} mb-3">
        @include('erp.partials.contact_select', ['name' => 'supplier_id', 'type' => 'supplier', 'contacts' => $suppliers, 'selected' => $invoice->supplier_id ?? ''])
    </div>
    @unless($simple)
    <div class="col-md-3 mb-3">
        <label class="form-label">Commande achat</label>
        <select name="purchase_order_id" class="form-control searchable-select">
            <option value="">-- Aucun --</option>
            @foreach($orders as $order)
                <option value="{{ $order->id }}" {{ (string) old('purchase_order_id', $invoice->purchase_order_id ?? '') === (string) $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Bon de reception</label>
        <select name="delivery_note_id" class="form-control searchable-select">
            <option value="">-- Aucun --</option>
            @foreach($notes as $note)
                <option value="{{ $note->id }}" {{ (string) old('delivery_note_id', $invoice->delivery_note_id ?? '') === (string) $note->id ? 'selected' : '' }}>{{ $note->receipt_number }}</option>
            @endforeach
        </select>
    </div>
    @endunless
    <div class="col-md-2 mb-3">
        <label class="form-label">Date facture</label>
        <input type="date" name="invoice_date" class="form-control" value="{{ old('invoice_date', isset($invoice) && $invoice->invoice_date ? \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Date echeance</label>
        <input type="date" name="due_date" class="form-control" value="{{ old('due_date', isset($invoice) && $invoice->due_date ? \Illuminate\Support\Carbon::parse($invoice->due_date)->format('Y-m-d') : '') }}">
    </div>
    @unless($simple)
    <div class="col-md-2 mb-3">
        <label class="form-label">Etat</label>
        <input type="text" class="form-control" value="Brouillon" readonly>
        <small class="text-muted">Le statut de paiement est calcule apres validation.</small>
    </div>
    <div class="col-md-2 mb-3">
        <label class="form-label">Conditions paiement</label>
        <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms', $invoice->payment_terms ?? '') }}">
    </div>
    @endunless
</div>

@include('erp.partials.document_lines', ['document' => $invoice, 'side' => 'purchase'])

<div class="erp-doc-actions">
    <button class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}" type="submit"><i class="fe fe-save" aria-hidden="true"></i> {{ $simple ? 'Enregistrer la facture' : 'Enregistrer' }}</button>
    <a href="{{ route('erp.supplier-invoices.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>

