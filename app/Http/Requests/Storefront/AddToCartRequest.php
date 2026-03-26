<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id', 'required_without:combo_id'],
            'combo_id' => ['nullable', 'integer', 'exists:storefront_product_combos,id', 'required_without:inventory_item_id'],
            'inventory_item_variant_id' => ['nullable', 'integer', 'exists:inventory_item_variants,id'],
            'quantity' => ['required', 'numeric', 'min:1', 'max:9999'],
        ];
    }
}
