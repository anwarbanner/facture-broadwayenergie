{{-- Shared by users.create / users.edit. @param User $user, array $roles, array $statuses, string $currentRole --}}
@csrf
@php($editing = $user->exists)
<div class="erp-doc-sheet">
    <div class="erp-doc-header">
        <h6>{{ $editing ? 'Modifier le compte' : 'Nouveau compte' }}</h6>
        <span class="erp-doc-badge">Utilisateur</span>
    </div>
    <div class="erp-doc-body">
        <div class="erp-form-section">
            <h6 class="erp-form-section-title"><i class="fe fe-user" aria-hidden="true"></i> Identite</h6>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="name">Nom complet <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name">
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="email">E-mail de connexion <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email">
                    @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="erp-form-section">
            <h6 class="erp-form-section-title"><i class="fe fe-shield" aria-hidden="true"></i> Role et acces</h6>
            <div class="erp-role-choices mb-3" role="radiogroup" aria-label="Role">
                @foreach($roles as $role => $description)
                    <label class="erp-role-choice">
                        <input type="radio" name="role_name" value="{{ $role }}" @checked(old('role_name', $currentRole) === $role) required>
                        <span class="erp-role-choice-body">
                            <strong><i class="fe {{ $role === 'Admin' ? 'fe-shield' : 'fe-briefcase' }}" aria-hidden="true"></i> {{ $role === 'Admin' ? 'Administrateur' : $role }}</strong>
                            <small>{{ \Illuminate\Support\Str::after($description, ': ') }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('role_name')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="status">Statut du compte</label>
                    <select id="status" name="status" class="form-control no-search" data-no-search>
                        @foreach($statuses as $code => $label)
                            <option value="{{ $code }}" @selected(old('status', $user->status ?? 'active') === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Un compte inactif ne peut plus se connecter.</small>
                </div>
            </div>
        </div>

        <div class="erp-form-section">
            <h6 class="erp-form-section-title"><i class="fe fe-lock" aria-hidden="true"></i> Mot de passe</h6>
            @if($editing)
                <p class="text-muted small mb-2">Laissez vide pour garder le mot de passe actuel.</p>
            @endif
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="password">{{ $editing ? 'Nouveau mot de passe' : 'Mot de passe' }} @unless($editing)<span class="text-danger">*</span>@endunless</label>
                    <div class="erp-password">
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8" autocomplete="new-password" @unless($editing) required @endunless>
                        <button type="button" class="btn btn-light" data-toggle-password="password" aria-label="Afficher le mot de passe"><i class="fe fe-eye" aria-hidden="true"></i></button>
                    </div>
                    <small class="form-text text-muted">8 caracteres minimum.</small>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="confirm-password">Confirmer le mot de passe</label>
                    <div class="erp-password">
                        <input type="password" id="confirm-password" name="confirm-password" class="form-control" autocomplete="new-password">
                        <button type="button" class="btn btn-light" data-toggle-password="confirm-password" aria-label="Afficher la confirmation"><i class="fe fe-eye" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="erp-doc-actions">
            <button type="submit" class="btn btn-primary"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
            <a href="{{ $editing ? route('users.show', $user) : route('users.index') }}" class="btn btn-light"><i class="fe fe-x" aria-hidden="true"></i> Annuler</a>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
    button.addEventListener('click', function () {
        var input = document.getElementById(button.getAttribute('data-toggle-password'));
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.querySelector('i').className = 'fe ' + (show ? 'fe-eye-off' : 'fe-eye');
        button.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
});
</script>
