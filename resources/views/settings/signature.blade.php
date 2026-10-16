@extends('layouts.master')

@section('title', 'Signature et cachet')

@section('content')
<div class="container-fluid erp-pro-page">
    <div class="erp-page-head">
        <div>
            <h4>Signature et cachet</h4>
            <small class="text-muted">Image imprimee en bas des devis, commandes, factures, avoirs, bons de livraison et de reception (PDF et Excel). Jamais sur un brouillon de facture.</small>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <h6 class="mb-3">{{ $image ? 'Remplacer la signature' : 'Ajouter la signature' }}</h6>
                <form method="POST" action="{{ route('settings.signature.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="signature-file">Image de la signature ou du cachet</label>
                        <input type="file" id="signature-file" name="signature" accept="image/png,image/jpeg" class="form-control @error('signature') is-invalid @enderror" data-signature-input>
                        <small class="form-text text-muted">PNG (fond transparent de preference) ou JPG, 2 Mo maximum. Astuce : signez sur une feuille blanche, photographiez-la bien a plat et recadrez au plus pres.</small>
                        @error('signature')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="signatory">Signataire (facultatif)</label>
                        <input type="text" id="signatory" name="signatory" maxlength="120" class="form-control" value="{{ old('signatory', $signatory) }}" placeholder="Ex. : Le gerant, Karim Alami" data-signatory-input>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fe fe-save" aria-hidden="true"></i> Enregistrer</button>
                </form>
                @if($image)
                    <form method="POST" action="{{ route('settings.signature.destroy') }}" class="mt-3" onsubmit="return confirm('Retirer la signature de tous les documents ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fe fe-trash-2" aria-hidden="true"></i> Supprimer la signature</button>
                    </form>
                @endif
            </div></div>
        </div>

        <div class="col-lg-6">
            <div class="card"><div class="card-body">
                <h6 class="mb-3">Apercu en bas d'un document</h6>
                <div class="erp-signature-preview">
                    <div class="erp-signature-box">
                        <div class="erp-signature-label">Cachet et signature</div>
                        <div class="erp-signature-name" data-signatory-preview>{{ $signatory }}</div>
                        <img src="{{ $image }}" alt="Signature" data-signature-preview @unless($image) hidden @endunless>
                        <div class="erp-signature-empty" data-signature-empty @if($image) hidden @endif>Aucune signature pour l'instant</div>
                    </div>
                </div>
            </div></div>
        </div>
    </div>
</div>

<script>
(function () {
    var input = document.querySelector('[data-signature-input]');
    var img = document.querySelector('[data-signature-preview]');
    var empty = document.querySelector('[data-signature-empty]');
    var name = document.querySelector('[data-signatory-input]');
    var namePreview = document.querySelector('[data-signatory-preview]');
    if (input) input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; img.hidden = false; empty.hidden = true; };
        reader.readAsDataURL(file);
    });
    if (name) name.addEventListener('input', function () { namePreview.textContent = name.value; });
})();
</script>
@endsection
