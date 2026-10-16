{{--
    Client / supplier picker of the document forms: search an existing contact, or type a new name
    ("Creer ...") which is added to the contacts when the document is saved (App\Services\Contacts\ContactResolver).

    @param string $name      field name (client_id / supplier_id)
    @param string $type      'client' | 'supplier'
    @param iterable $contacts active contacts of that type
    @param mixed $selected   current value of the document
--}}
@php
    $resolver = \App\Services\Contacts\ContactResolver::class;
    $current = (string) old($name, $selected ?? '');
    $isNew = $resolver::isNew($current);
    $options = collect($contacts);
    // A document keeps its contact even if that contact was deactivated since.
    if ($current !== '' && !$isNew && !$options->contains('id', (int) $current)) {
        $options->push(\App\Models\Contact::find((int) $current));
    }
    $noun = $type === 'supplier' ? 'fournisseur' : 'client';
@endphp
<label class="form-label" for="{{ $name }}-picker">{{ ucfirst($noun) }} <span class="text-danger">*</span></label>
<select name="{{ $name }}" id="{{ $name }}-picker" class="form-control @error($name) is-invalid @enderror"
        data-contact-picker data-noun="{{ $noun }}" required>
    <option value="">Rechercher ou taper un nouveau {{ $noun }}...</option>
    @foreach($options->filter() as $contact)
        <option value="{{ $contact->id }}" @selected($current === (string) $contact->id)>{{ $contact->company_name }}{{ $contact->city ? ' - ' . $contact->city : '' }}</option>
    @endforeach
    @if($isNew)
        <option value="{{ $current }}" selected>{{ $resolver::newName($current) }} (nouveau)</option>
    @endif
</select>
<small class="form-text text-muted">Nouveau {{ $noun }} ? Tapez son nom : il sera ajoute aux contacts a l'enregistrement.</small>
@error($name)
    <div class="invalid-feedback d-block">{{ $message }}</div>
@enderror
