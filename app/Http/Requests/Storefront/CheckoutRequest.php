<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? trim((string) $this->input('email')) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:50', 'required_without:email'],
            'shipping_method_code' => ['required', 'string', 'max:100'],
            'payment_method_code' => ['required', 'string', Rule::exists('payment_methods', 'code')->where('is_enabled', true)],
            'coupon_code' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_address.country' => ['required', 'string', 'min:2', 'max:3'],
            'shipping_address.state' => ['nullable', 'string', 'max:191'],
            'shipping_address.city' => ['required', 'string', 'max:191'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:50'],
            'billing_same_as_shipping' => ['nullable', 'boolean'],
            'billing_address.country' => ['required_without:billing_same_as_shipping', 'string', 'min:2', 'max:3'],
            'billing_address.state' => ['nullable', 'string', 'max:191'],
            'billing_address.city' => ['required_without:billing_same_as_shipping', 'string', 'max:191'],
            'billing_address.address_line1' => ['required_without:billing_same_as_shipping', 'string', 'max:255'],
            'billing_address.address_line2' => ['nullable', 'string', 'max:255'],
            'billing_address.postal_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => __('Provide an email address or phone number.'),
            'phone.required_without' => __('Provide a phone number or email address.'),
        ];
    }
}
