<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_number' => ['nullable', 'string', 'max:255'],
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'client_id' => ['required', 'integer', 'exists:contacts,id'],
            'delivery_date' => ['required', 'date'],
            'shipping_address' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,shipped,delivered,returned'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'item_source_id' => ['nullable', 'array'],
            'item_source_id.*' => ['nullable', 'integer'],
            'item_mode' => ['nullable', 'array'],
            'item_mode.*' => ['nullable', 'in:product,service,manual'],
            'item_name' => ['nullable', 'array'],
            'item_name.*' => ['nullable', 'string', 'max:255'],
            'item_product_id' => ['nullable', 'array'],
            'item_product_id.*' => ['nullable', 'integer', 'exists:products,id'],
            'item_service_id' => ['nullable', 'array'],
            'item_service_id.*' => ['nullable', 'integer', 'exists:services,id'],
            'item_quantity' => ['required', 'array', 'min:1'],
            'item_quantity.*' => ['nullable', 'numeric', 'min:0.001'],
            'item_shipped_qty' => ['nullable', 'array'],
            'item_shipped_qty.*' => ['nullable', 'numeric', 'min:0.001'],
        ];
    }
}
