<?php

namespace App\Http\Requests;

use App\Support\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpsertSalesQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'quote_number' => ['nullable', 'string', 'max:255'],
            'client_id' => DocumentRules::contact(),
            'date' => ['required', 'date'],
            'validity_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,converted'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines());
    }
}
