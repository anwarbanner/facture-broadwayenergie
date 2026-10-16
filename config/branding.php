<?php

/*
|--------------------------------------------------------------------------
| Company identity and local defaults
|--------------------------------------------------------------------------
|
| Everything that identifies the company using the ERP comes from .env, so the same code can be deployed
| for any business. Nothing here is specific to one company: empty values are simply not displayed.
| See docs/PERSONNALISATION.md.
|
*/

return [
    // Simple mode: short menu (quotes, invoices, clients, products), one-click cash-in on quotes, print in the browser.
    // false = full ERP menu (purchases, stock, accounting...). Every screen stays reachable either way.
    'simple_mode' => (bool) env('SIMPLE_MODE', true),

    'company_name' => env('COMPANY_NAME', env('APP_NAME', 'Mon Entreprise')),

    // Path under public/ (e.g. "assets/img/brand/logo.png"). Empty = no image, the initials of the name are shown.
    'logo_path' => env('COMPANY_LOGO_PATH', ''),

    'tagline' => env('COMPANY_TAGLINE', 'Gestion commerciale'),

    // Legal form printed next to the logo on documents (e.g. "SARL"). Empty = nothing.
    'legal_form' => env('COMPANY_LEGAL_FORM', ''),

    // Business lines printed top-right of documents; "|" separates the lines.
    'slogan' => env('COMPANY_SLOGAN', ''),

    // Document letterhead colours
    'color_primary' => env('COMPANY_COLOR_PRIMARY', '#0061A3'),
    'color_accent' => env('COMPANY_COLOR_ACCENT', '#E91E25'),

    // Optional images under public/: pale watermark behind documents, square browser-tab icon.
    'watermark_path' => env('COMPANY_WATERMARK_PATH', ''),
    'favicon_path' => env('COMPANY_FAVICON_PATH', ''),

    'phone' => env('COMPANY_PHONE', ''),
    'email' => env('COMPANY_EMAIL', ''),
    'address' => env('COMPANY_ADDRESS', ''),
    'city' => env('COMPANY_CITY', ''),
    'country' => env('COMPANY_COUNTRY', ''),

    // Legal identifiers, free text so any country fits (e.g. "SIRET 123 456 789 00012 | TVA FR12345678901").
    'legal_ids' => env('COMPANY_LEGAL_IDS', ''),

    // Optional Moroccan identifiers, printed only when filled.
    'ice' => env('COMPANY_ICE', ''),
    'rc' => env('COMPANY_RC', ''),
    'if' => env('COMPANY_IF', ''),
    'patente' => env('COMPANY_PATENTE', ''),

    // Defaults proposed on new documents (still editable per document).
    'default_currency' => env('DEFAULT_CURRENCY', 'MAD'),
    'default_vat_rate' => (float) env('DEFAULT_VAT_RATE', 20),
];
