@csrf
@php($simple = \App\Support\Branding::simpleMode())

@if($simple)
{{-- Simple mode: name and price only. Stock, cost and category keep their value (stock is not managed here). --}}
<input type="hidden" name="type" value="product">
@foreach(['reference' => $product->reference ?? '', 'designation' => $product->designation ?? '', 'category_id' => $product->category_id ?? '',
          'cost_price_ht' => $product->cost_price_ht ?? 0, 'stock_quantity' => $product->stock_quantity ?? 0,
          'min_stock_alert_level' => $product->min_stock_alert_level ?? 0, 'status' => $product->status ?? 'active'] as $field => $value)
    <input type="hidden" name="{{ $field }}" value="{{ old($field, $value) }}">
@endforeach
<div class="row erp-simple-form">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="product_name">Nom du produit</label>
        <input type="text" id="product_name" name="product_name" value="{{ old('product_name', $product->product_name ?? '') }}" class="form-control form-control-lg @error('product_name') is-invalid @enderror" required autofocus>
        @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label" for="sale_price_ht">Prix de vente HT</label>
        <input type="number" id="sale_price_ht" step="0.01" min="0" name="sale_price_ht" value="{{ old('sale_price_ht', $product->sale_price_ht ?? 0) }}" class="form-control form-control-lg @error('sale_price_ht') is-invalid @enderror" required>
        @error('sale_price_ht')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label" for="unit_type">Unite</label>
        <input type="text" id="unit_type" name="unit_type" list="unit-types" value="{{ old('unit_type', $product->unit_type ?? 'piece') }}" class="form-control form-control-lg @error('unit_type') is-invalid @enderror" required>
        <datalist id="unit-types"><option value="piece"><option value="m"><option value="m2"><option value="kg"><option value="heure"><option value="forfait"></datalist>
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label" for="description">Description (facultatif)</label>
        <textarea id="description" name="description" rows="2" class="form-control form-control-lg @error('description') is-invalid @enderror">{{ old('description', $product->description ?? '') }}</textarea>
    </div>
</div>
<button type="submit" class="btn btn-primary btn-lg"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer le produit</button>
<a href="{{ route('erp.products.index') }}" class="btn btn-light btn-lg"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
@else
<div class="row">
    <input type="hidden" name="type" value="product">
    <div class="col-md-4 mb-3">
        <label class="form-label">Reference</label>
        <input type="text" name="reference" value="{{ old('reference', $product->reference ?? '') }}" class="form-control @error('reference') is-invalid @enderror">
        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Nom</label>
        <input type="text" name="product_name" value="{{ old('product_name', $product->product_name ?? '') }}" class="form-control @error('product_name') is-invalid @enderror" required>
        @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Designation</label>
        <input type="text" name="designation" value="{{ old('designation', $product->designation ?? '') }}" class="form-control @error('designation') is-invalid @enderror">
        @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Categorie</label>
        <select name="category_id" class="form-control @error('category_id') is-invalid @enderror">
            <option value="">-- Selectionner --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ (string) old('category_id', $product->category_id ?? '') === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Unite</label>
        <input type="text" name="unit_type" value="{{ old('unit_type', $product->unit_type ?? 'piece') }}" class="form-control @error('unit_type') is-invalid @enderror" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Prix de vente HT</label>
        <input type="number" step="0.01" min="0" name="sale_price_ht" value="{{ old('sale_price_ht', $product->sale_price_ht ?? 0) }}" class="form-control @error('sale_price_ht') is-invalid @enderror" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Prix d'achat HT</label>
        <input type="number" step="0.01" min="0" name="cost_price_ht" value="{{ old('cost_price_ht', $product->cost_price_ht ?? 0) }}" class="form-control @error('cost_price_ht') is-invalid @enderror" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Quantite en stock</label>
        <input type="number" step="0.001" min="0" name="stock_quantity" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" class="form-control @error('stock_quantity') is-invalid @enderror" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Alerte stock minimum</label>
        <input type="number" step="0.001" min="0" name="min_stock_alert_level" value="{{ old('min_stock_alert_level', $product->min_stock_alert_level ?? 0) }}" class="form-control @error('min_stock_alert_level') is-invalid @enderror" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control @error('status') is-invalid @enderror" required>
            @foreach(['active' => 'Actif', 'inactive' => 'Inactif'] as $k => $v)
                <option value="{{ $k }}" {{ old('status', $product->status ?? 'active') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description ?? '') }}</textarea>
    </div>
</div>

<button type="submit" class="btn btn-primary"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
<a href="{{ route('erp.products.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
@endif

