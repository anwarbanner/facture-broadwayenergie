@csrf
@php($simple = \App\Support\Branding::simpleMode())
@php($noun = ($contact->type ?? 'client') === 'supplier' ? 'fournisseur' : 'client')

@if($simple)
{{-- Simple mode: only what is printed on a quote. The other fields keep their value. --}}
@foreach(['type' => $contact->type ?? 'client', 'status' => $contact->status ?? 'active', 'contact_person_name' => $contact->contact_person_name ?? '',
          'mobile' => $contact->mobile ?? '', 'postal_code' => $contact->postal_code ?? '', 'country' => $contact->country ?? '',
          'payment_terms' => $contact->payment_terms ?? '', 'credit_limit' => $contact->credit_limit ?? 0] as $field => $value)
    <input type="hidden" name="{{ $field }}" value="{{ old($field, $value) }}">
@endforeach
<div class="row erp-simple-form">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="company_name">Nom du {{ $noun }}</label>
        <input type="text" id="company_name" name="company_name" class="form-control form-control-lg" value="{{ old('company_name', $contact->company_name ?? '') }}" required autofocus>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="phone">Telephone</label>
        <input type="text" id="phone" name="phone" class="form-control form-control-lg" value="{{ old('phone', $contact->phone ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="address">Adresse</label>
        <input type="text" id="address" name="address" class="form-control form-control-lg" value="{{ old('address', $contact->address ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="city">Ville</label>
        <input type="text" id="city" name="city" class="form-control form-control-lg" value="{{ old('city', $contact->city ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="tax_id">ICE / Identifiant fiscal (facultatif)</label>
        <input type="text" id="tax_id" name="tax_id" class="form-control form-control-lg" value="{{ old('tax_id', $contact->tax_id ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="email">Email (facultatif)</label>
        <input type="email" id="email" name="email" class="form-control form-control-lg" value="{{ old('email', $contact->email ?? '') }}">
    </div>
</div>
<button class="btn btn-primary btn-lg" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer le {{ $noun }}</button>
<a href="{{ route('erp.contacts.index', ['type' => $contact->type ?? 'client']) }}" class="btn btn-light btn-lg"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
@else
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-control" required>
            @foreach(['client' => 'Client', 'supplier' => 'Fournisseur'] as $k => $v)
                <option value="{{ $k }}" {{ old('type', $contact->type ?? 'client') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Nom de societe</label>
        <input type="text" name="company_name" class="form-control" value="{{ old('company_name', $contact->company_name ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Personne de contact</label>
        <input type="text" name="contact_person_name" class="form-control" value="{{ old('contact_person_name', $contact->contact_person_name ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $contact->email ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Telephone</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone', $contact->phone ?? '') }}">
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Mobile</label>
        <input type="text" name="mobile" class="form-control" value="{{ old('mobile', $contact->mobile ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Adresse</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $contact->address ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Ville</label>
        <input type="text" name="city" class="form-control" value="{{ old('city', $contact->city ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Code postal</label>
        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $contact->postal_code ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Pays</label>
        <input type="text" name="country" class="form-control" value="{{ old('country', $contact->country ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Identifiant fiscal</label>
        <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $contact->tax_id ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Conditions de paiement</label>
        <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms', $contact->payment_terms ?? '') }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Plafond de credit</label>
        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control" value="{{ old('credit_limit', $contact->credit_limit ?? 0) }}">
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Statut</label>
        <select name="status" class="form-control" required>
            @foreach(['active' => 'Actif', 'inactive' => 'Inactif'] as $k => $v)
                <option value="{{ $k }}" {{ old('status', $contact->status ?? 'active') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
</div>
<button class="btn btn-primary" type="submit"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
<a href="{{ route('erp.contacts.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
@endif

