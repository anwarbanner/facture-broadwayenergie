@csrf

<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">Bon de reception</span>
    </div>
    <div class="erp-doc-body">
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Numero bon reception</label>
        <input type="text" name="receipt_number" class="form-control" value="{{ old('receipt_number', $note->receipt_number ?? '') }}" readonly placeholder="Genere automatiquement">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Commande achat</label>
        <select name="purchase_order_id" class="form-control searchable-select" required>
            <option value="">-- Selectionner --</option>
            @foreach($orders as $order)
                <option value="{{ $order->id }}" {{ (string) old('purchase_order_id', $note->purchase_order_id ?? request('purchase_order_id', '')) === (string) $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Fournisseur</label>
        <select name="supplier_id" class="form-control searchable-select" required>
            <option value="">-- Selectionner --</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', $note->supplier_id ?? '') === (string) $supplier->id ? 'selected' : '' }}>{{ $supplier->company_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Date de reception</label>
        <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', isset($note) && $note->delivery_date ? \Illuminate\Support\Carbon::parse($note->delivery_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control" required>
            @foreach(['partial','complete'] as $status)
                <option value="{{ $status }}" {{ old('status', $note->status ?? 'partial') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Notes</label>
        <input type="text" name="notes" class="form-control" value="{{ old('notes', $note->notes ?? '') }}">
    </div>
</div>

@include('erp.partials.document_lines', ['document' => $note, 'side' => 'purchase', 'quantities' => ['field' => 'item_received_qty', 'attribute' => 'received_qty', 'label' => 'Qt recue', 'source' => 'purchase_order_item_id', 'min' => 0]])

<div class="erp-doc-actions">
    <button class="btn btn-primary" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
    <a href="{{ route('erp.delivery-notes.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>

