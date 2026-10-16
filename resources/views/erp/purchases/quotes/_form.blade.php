@csrf

@php($simple = \App\Support\Branding::simpleMode())
<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>En-tete du document</h6>
        <span class="erp-doc-badge">{{ $simple ? 'Devis fournisseur' : 'Demande d\'achat' }}</span>
    </div>
    <div class="erp-doc-body">
<div class="row">
    @if($simple)
        {{-- Simple mode: the number is automatic and the status is not asked. --}}
        <input type="hidden" name="status" value="{{ old('status', $quote->status ?? 'draft') }}">
        <div class="col-md-6 mb-3">
            @include('erp.partials.contact_select', ['name' => 'supplier_id', 'type' => 'supplier', 'contacts' => $suppliers, 'selected' => $quote->supplier_id ?? ''])
        </div>
    @else
    <div class="col-md-4 mb-3">
        <label class="form-label">Numero demande d'achat</label>
        <input type="text" name="quote_number" class="form-control" value="{{ old('quote_number', $quote->quote_number ?? '') }}" readonly placeholder="Genere automatiquement">
    </div>
    <div class="col-md-4 mb-3">
        @include('erp.partials.contact_select', ['name' => 'supplier_id', 'type' => 'supplier', 'contacts' => $suppliers, 'selected' => $quote->supplier_id ?? ''])
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control" required>
            @foreach(['draft','sent','accepted','rejected'] as $status)
                <option value="{{ $status }}" {{ old('status', $quote->status ?? 'draft') === $status ? 'selected' : '' }}>{{ \App\Support\Format::label($status) }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-3 mb-3">
        <label class="form-label">Date</label>
        <input type="date" name="date" class="form-control" value="{{ old('date', isset($quote) && $quote->date ? \Illuminate\Support\Carbon::parse($quote->date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">{{ $simple ? 'Valable jusqu\'au (facultatif)' : 'Date de validite' }}</label>
        <input type="date" name="validity_date" class="form-control" value="{{ old('validity_date', isset($quote) && $quote->validity_date ? \Illuminate\Support\Carbon::parse($quote->validity_date)->format('Y-m-d') : '') }}">
    </div>
</div>

@include('erp.partials.document_lines', ['document' => $quote, 'side' => 'purchase'])

<div class="erp-doc-actions">
    <button class="btn btn-primary {{ $simple ? 'btn-lg' : '' }}" type="submit"><i class="fe fe-save" aria-hidden="true"></i> {{ $simple ? 'Enregistrer le devis fournisseur' : 'Enregistrer' }}</button>
    <a href="{{ route('erp.purchase-quotes.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
</div>
    </div>
</div>

