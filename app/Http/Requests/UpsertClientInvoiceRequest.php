<?php

namespace App\Http\Requests;

use App\Support\DocumentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpsertClientInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge([
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'client_id' => DocumentRules::contact(),
            'sales_order_id' => ['nullable', 'integer', 'exists:sales_orders,id'],
            'delivery_id' => ['nullable', 'integer', 'exists:deliveries,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable'], // ignored: payment status is computed
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_paid' => ['nullable'], // ignored: payments are recorded after validation
        ], DocumentRules::pricedLines());
    }
}
