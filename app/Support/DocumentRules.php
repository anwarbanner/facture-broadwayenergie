<?php

namespace App\Support;

use App\Rules\ContactReference;

/** Validation rules shared by the commercial document forms (sales and purchases). */
class DocumentRules
{
    /** Client / supplier picker: an existing contact or a new name typed in the form. */
    public static function contact(): array
    {
        return ['required', new ContactReference()];
    }

    /** One VAT rate for the whole document, and product / service / free-text lines. */
    public static function pricedLines(): array
    {
        return [
            'tva_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'item_mode' => ['nullable', 'array'],
            'item_mode.*' => ['nullable', 'in:product,service,manual'],
            'item_product_id' => ['nullable', 'array'],
            'item_product_id.*' => ['nullable', 'integer', 'exists:products,id'],
            'item_service_id' => ['nullable', 'array'],
            'item_service_id.*' => ['nullable', 'integer', 'exists:services,id'],
            'item_name' => ['nullable', 'array'],
            'item_name.*' => ['nullable', 'string', 'max:255'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.001'],
            'item_price_ht' => ['required', 'array', 'min:1'],
            'item_price_ht.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
