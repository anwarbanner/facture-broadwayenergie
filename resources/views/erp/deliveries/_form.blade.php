@csrf

<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">Livraison</span>
    </div>
    <div class="erp-doc-body">
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Numero de livraison</label>
        <input type="text" name="delivery_number" class="form-control" value="{{ old('delivery_number', $delivery->delivery_number ?? '') }}" readonly placeholder="Genere automatiquement">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Commande client</label>
        <select name="sales_order_id" class="form-control searchable-select" required>
            <option value="">-- Selectionner --</option>
            @foreach($orders as $order)
                <option value="{{ $order->id }}" {{ (string) old('sales_order_id', $delivery->sales_order_id ?? '') === (string) $order->id ? 'selected' : '' }}>{{ $order->order_number }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-control searchable-select" required>
            <option value="">-- Selectionner --</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ (string) old('client_id', $delivery->client_id ?? '') === (string) $client->id ? 'selected' : '' }}>{{ $client->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Date de livraison</label>
        <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', isset($delivery) && $delivery->delivery_date ? \Illuminate\Support\Carbon::parse($delivery->delivery_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control" required>
            @foreach(['pending' => 'En attente', 'shipped' => 'Expediee', 'delivered' => 'Livree', 'returned' => 'Retournee'] as $status => $label)
                <option value="{{ $status }}" {{ old('status', $delivery->status ?? 'pending') === $status ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Numero de suivi</label>
        <input type="text" name="tracking_number" class="form-control" value="{{ old('tracking_number', $delivery->tracking_number ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Adresse de livraison</label>
        <input type="text" name="shipping_address" class="form-control" value="{{ old('shipping_address', $delivery->shipping_address ?? '') }}">
    </div>
</div>

@include('erp.partials.document_lines', ['document' => $delivery, 'side' => 'sales', 'quantities' => ['field' => 'item_shipped_qty', 'attribute' => 'shipped_qty', 'label' => 'Qt expediee', 'source' => 'sales_order_item_id', 'min' => 0.001]])

<div class="erp-doc-actions">
    <button class="btn btn-primary" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
    <a href="{{ route('erp.deliveries.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>


