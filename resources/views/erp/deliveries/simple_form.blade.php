@extends('layouts.master')

@section('title', $delivery->exists ? 'Modifier le bon de livraison' : 'Bon de livraison sans devis')

{{-- Simple mode BL form (SimpleDeliveryController): client, date, articles and quantities. No prices, no stock movement. --}}
@section('content')
<div class="container-fluid erp-pro-page">
    <h4 class="mb-3">{{ $delivery->exists ? 'Modifier le bon de livraison ' . $delivery->delivery_number : 'Nouveau bon de livraison (sans devis)' }}</h4>

    @if($delivery->exists && $delivery->isFromQuote() && $delivery->follows_quote)
        <div class="alert alert-info">
            Ce BL suit le devis {{ $delivery->quote->quote_number ?? '' }}. Si vous l'enregistrez ici, il gardera vos changements
            et ne sera plus mis a jour quand vous modifiez le devis.
        </div>
    @endif

    <form method="POST" action="{{ $delivery->exists ? route('erp.deliveries.simple-update', $delivery) : route('erp.deliveries.simple-store') }}">
        @csrf
        @if($delivery->exists) @method('PUT') @endif

        <div class="erp-doc-sheet">
            <div class="erp-doc-header">
                <h6>Bon de livraison</h6>
                <span class="erp-doc-badge">Le stock ne change pas</span>
            </div>
            <div class="erp-doc-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        @include('erp.partials.contact_select', ['name' => 'client_id', 'type' => 'client', 'contacts' => $clients, 'selected' => $delivery->client_id ?? ''])
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="delivery_date">Date</label>
                        <input type="date" id="delivery_date" name="delivery_date" class="form-control"
                               value="{{ old('delivery_date', optional($delivery->delivery_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="shipping_address">Adresse de livraison (facultatif)</label>
                        <input type="text" id="shipping_address" name="shipping_address" class="form-control" maxlength="1000"
                               value="{{ old('shipping_address', $delivery->shipping_address) }}">
                    </div>
                </div>

                @include('erp.partials.document_lines', [
                    'document' => $delivery,
                    'side' => 'sales',
                    'quantities' => ['field' => 'item_shipped_qty', 'attribute' => 'shipped_qty', 'label' => 'Quantite',
                                     'source' => 'sales_order_item_id', 'single' => true],
                ])

                <div class="erp-doc-actions">
                    <button class="btn btn-primary btn-lg" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer le bon de livraison</button>
                    <a href="{{ $delivery->exists ? route('erp.deliveries.show', $delivery) : route('erp.deliveries.index') }}" class="btn btn-light btn-lg"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
