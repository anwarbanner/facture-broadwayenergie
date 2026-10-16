@csrf

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nom du service</label>
        <input type="text" name="service_name" value="{{ old('service_name', $service->service_name ?? '') }}" class="form-control @error('service_name') is-invalid @enderror" required>
        @error('service_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Nombre d'heures</label>
        <input type="number" step="0.01" min="0.01" name="hours_count" value="{{ old('hours_count', $service->hours_count ?? 1) }}" class="form-control @error('hours_count') is-invalid @enderror" required>
        @error('hours_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Prix du service (HT)</label>
        <input type="number" step="0.01" min="0" name="service_price" value="{{ old('service_price', $service->service_price ?? 0) }}" class="form-control @error('service_price') is-invalid @enderror" required>
        @error('service_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control @error('status') is-invalid @enderror" required>
            @foreach(['active' => 'Actif', 'inactive' => 'Inactif'] as $k => $v)
                <option value="{{ $k }}" {{ old('status', $service->status ?? 'active') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $service->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<button type="submit" class="btn btn-primary"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
<a href="{{ route('erp.services.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
