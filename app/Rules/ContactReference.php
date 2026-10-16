<?php

namespace App\Rules;

use App\Models\Contact;
use App\Services\Contacts\ContactResolver;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** An existing contact id, or a new name typed in the contact picker ("__new__:<name>"). */
class ContactReference implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (ContactResolver::isNew($value)) {
            $name = ContactResolver::newName($value);
            if ($name === '' || mb_strlen($name) > 255) {
                $fail('Le nom du nouveau contact doit contenir entre 1 et 255 caracteres.');
            }

            return;
        }

        if (!ctype_digit((string) $value) || !Contact::whereKey((int) $value)->exists()) {
            $fail('Le contact selectionne est introuvable.');
        }
    }
}
