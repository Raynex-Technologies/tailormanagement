<?php

namespace App\Livewire\Concerns;

use App\Rules\ValidPhone;
use App\Support\Phone;
use Illuminate\Support\Facades\Validator;

trait ValidatesPhoneNumbers
{
    protected function getDataForValidation($rules)
    {
        $attributes = parent::getDataForValidation($rules);
        foreach (['phone', 'alternate_phone', 'storefront_contact_phone', 'newCustomerPhone', 'customer_phone', 'customer_whatsapp', 'receivedByPhone', 'twilio_from'] as $field) {
            if (array_key_exists($field, $rules) && array_key_exists($field, $attributes) && filled($attributes[$field])) {
                Validator::make([$field => $attributes[$field]], [$field => ['string', new ValidPhone]])->validate();
                $attributes[$field] = Phone::toE164Tz($attributes[$field]);
                $this->{$field} = $attributes[$field];
            }
        }

        return $attributes;
    }
}
