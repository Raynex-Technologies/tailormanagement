<?php

namespace App\Rules;

use App\Models\Customer;
use App\Support\InternationalPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AvailableCustomerPhone implements ValidationRule
{
    public function __construct(private int $branchId, private ?int $ignoreId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? InternationalPhone::parts($value) : null;
        if (! $parts) {
            return;
        }
        if (Customer::withoutGlobalScopes()->where('branch_id', $this->branchId)
            ->when($this->ignoreId, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->where('phone', '!=', $parts['e164'])
            ->where('phone_country_code', $parts['country_code'])->where('phone_national_number', $parts['national_number'])->exists()) {
            $fail(__('This phone number already belongs to a customer in this branch.'));
        }
    }
}
