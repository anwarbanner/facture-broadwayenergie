@csrf

<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">Commande achat</span>
    </div>
    <div class="erp-doc-body">
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Numero commande achat</label>
        <input type="text" name="order_number" class="form-control" value="{{ old('order_number', $order->order_number ?? '') }}" readonly placeholder="Genere automatiquement">
    </div>
    <div class="col-md-4 mb-3">
        @include('erp.partials.contact_select', ['name' => 'supplier_id', 'type' => 'supplier', 'contacts' => $suppliers, 'selected' => $order->supplier_id ?? ''])
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Demande d'achat source</label>
        <select name="quote_id" class="form-control searchable-select">
            <option value="">-- Aucun --</option>
            @foreach($quotes as $quote)
                <option value="{{ $quote->id }}" {{ (string) old('quote_id', $order->quote_id ?? request('quote_id', '')) === (string) $quote->id ? 'selected' : '' }}>{{ $quote->quote_number }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3 mb-3">
        <label class="form-label">Date commande</label>
        <input type="date" name="order_date" class="form-control" value="{{ old('order_date', isset($order) && $order->order_date ? \Illuminate\Support\Carbon::parse($order->order_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Date de reception prevue</label>
        <input type="date" name="expected_delivery_date" class="form-control" value="{{ old('expected_delivery_date', isset($order) && $order->expected_delivery_date ? \Illuminate\Support\Carbon::parse($order->expected_delivery_date)->format('Y-m-d') : '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control" required>
            @foreach(['draft','confirmed','partial','delivered','cancelled'] as $status)
                <option value="{{ $status }}" {{ old('status', $order->status ?? 'draft') === $status ? 'selected' : '' }}>{{ \App\Support\Format::label($status) }}</option>
            @endforeach
        </select>
    </div>
</div>

@include('erp.partials.document_lines', ['document' => $order, 'side' => 'purchase'])

<div class="erp-doc-actions">
    <button class="btn btn-primary" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
    <a href="{{ route('erp.purchase-orders.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>

