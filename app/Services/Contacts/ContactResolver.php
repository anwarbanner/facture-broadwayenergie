<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use Illuminate\Support\Facades\Auth;

/**
 * Document forms let the user pick an existing client / supplier or type a new name.
 * A typed name arrives as "__new__:<name>" (see partials/contact_select) and is added to the contacts.
 */
class ContactResolver
{
    public const NEW_PREFIX = '__new__:';

    public static function isNew(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::NEW_PREFIX);
    }

    public static function newName(mixed $value): string
    {
        return trim(mb_substr((string) $value, mb_strlen(self::NEW_PREFIX)));
    }

    /** @param string $type 'client' or 'supplier' */
    public function resolve(mixed $value, string $type): int
    {
        if (!self::isNew($value)) {
            return (int) $value;
        }

        $name = self::newName($value);

        // Same name typed twice (or by two users) -> same contact, not a duplicate.
        $existing = Contact::where('type', $type)
            ->whereRaw('LOWER(company_name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return Contact::create([
            'type' => $type,
            'company_name' => $name,
            'status' => 'active',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ])->id;
    }
}
