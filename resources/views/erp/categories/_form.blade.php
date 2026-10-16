@csrf

<div class="mb-3">
    <label class="form-label">Nom</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $category->name ?? '') }}" required>
</div>
<div class="mb-3">
    <label class="form-label">Categorie parente</label>
    <select name="parent_category_id" class="form-control">
        <option value="">Aucun</option>
        @foreach($parents as $parent)
            <option value="{{ $parent->id }}" {{ (string) old('parent_category_id', $category->parent_category_id ?? '') === (string) $parent->id ? 'selected' : '' }}>{{ $parent->name }}</option>
        @endforeach
    </select>
</div>
<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control">{{ old('description', $category->description ?? '') }}</textarea>
</div>
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
    <label class="form-check-label" for="is_active">Actif</label>
</div>
<button class="btn btn-primary" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
<a href="{{ route('erp.categories.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>

