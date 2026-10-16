<?php

namespace App\Http\Requests;

use App\Support\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpsertSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'order_number' => ['nullable', 'string', 'max:255'],
            'client_id' => DocumentRules::contact(),
            'quote_id' => ['nullable', 'integer', 'exists:sales_quotes,id'],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,confirmed,partial,delivered,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
        ], DocumentRules::pricedLines());
    }
}
