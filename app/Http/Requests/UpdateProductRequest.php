<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'reference' => ['nullable', 'string', 'max:100', 'unique:products,reference,' . $productId],
            'product_name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:product'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sale_price_ht' => ['required', 'numeric', 'min:0'],
            'cost_price_ht' => ['required', 'numeric', 'min:0'],
            'unit_type' => ['required', 'string', 'max:30'],
            'stock_quantity' => ['required', 'numeric', 'min:0'],
            'min_stock_alert_level' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
