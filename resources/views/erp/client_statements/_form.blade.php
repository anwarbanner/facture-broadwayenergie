@csrf

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Client</label>
        <select name="client_id" class="form-control" required>
            <option value="">-- Selectionner --</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ (string) old('client_id', $statement->client_id ?? '') === (string) $client->id ? 'selected' : '' }}>{{ $client->company_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Debut de periode</label>
        <input type="date" name="period_start" class="form-control" value="{{ old('period_start', isset($statement) && $statement->period_start ? \Illuminate\Support\Carbon::parse($statement->period_start)->format('Y-m-d') : now()->startOfMonth()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Fin de periode</label>
        <input type="date" name="period_end" class="form-control" value="{{ old('period_end', isset($statement) && $statement->period_end ? \Illuminate\Support\Carbon::parse($statement->period_end)->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
</div>

<button class="btn btn-primary" type="submit"><i class="fe fe-plus" aria-hidden="true"></i> Generer</button>
<a href="{{ route('erp.client-statements.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>

