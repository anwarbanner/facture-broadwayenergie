<?php

/*
|--------------------------------------------------------------------------
| Accounting entries export (Comptabilite > Ecritures)
|--------------------------------------------------------------------------
| Account numbers used to turn invoices, credit notes and payments into journal entries for the
| accountant. Defaults follow the Moroccan chart of accounts (PCGM); change them in .env if the
| accountant uses other numbers (e.g. a sub-account per client).
*/

return [
    'journals' => [
        'sales' => env('ACCOUNTING_JOURNAL_SALES', 'VT'),
        'purchases' => env('ACCOUNTING_JOURNAL_PURCHASES', 'HA'),
        'bank' => env('ACCOUNTING_JOURNAL_BANK', 'BQ'),
        'cash' => env('ACCOUNTING_JOURNAL_CASH', 'CA'),
    ],

    'accounts' => [
        'clients' => env('ACCOUNTING_ACCOUNT_CLIENTS', '3421'),
        'suppliers' => env('ACCOUNTING_ACCOUNT_SUPPLIERS', '4411'),
        'sales' => env('ACCOUNTING_ACCOUNT_SALES', '7111'),
        'purchases' => env('ACCOUNTING_ACCOUNT_PURCHASES', '6111'),
        'vat_collected' => env('ACCOUNTING_ACCOUNT_VAT_COLLECTED', '4455'),
        'vat_deductible' => env('ACCOUNTING_ACCOUNT_VAT_DEDUCTIBLE', '3455'),
        'bank' => env('ACCOUNTING_ACCOUNT_BANK', '5141'),
        'cash' => env('ACCOUNTING_ACCOUNT_CASH', '5161'),
    ],
];
