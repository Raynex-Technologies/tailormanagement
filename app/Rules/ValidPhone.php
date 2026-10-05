<?php

namespace App\Rules;

use App\Support\InternationalPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! InternationalPhone::parts($value)) {
            $fail(__('Enter a valid phone number for the selected country code.'));
        }
    }
}
