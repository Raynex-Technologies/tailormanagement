<?php

namespace App\Models\Concerns;

use App\Support\InternationalPhone;
use Illuminate\Validation\ValidationException;

trait StoresPhoneNumbers
{
    public static function bootStoresPhoneNumbers(): void
    {
        static::saving(function ($model) {
            foreach (InternationalPhone::storageFields()[$model->getTable()] ?? [] as $field) {
                if ($model->exists && ! $model->isDirty($field)) {
                    continue;
                }
                $value = $model->getAttribute($field);
                $parts = is_string($value) && filled($value) ? InternationalPhone::parts($value) : null;
                if (filled($value) && ! $parts) {
                    throw ValidationException::withMessages([$field => __('Enter a valid phone number for the selected country code.')]);
                }
                if ($parts && $model->getTable() === 'customers' && $field === 'phone') {
                    $branchId = $model->exists ? $model->branch_id : static::determineBranchIdForCreate($model->branch_id);
                    $duplicate = static::withoutGlobalScopes()->where('branch_id', $branchId)
                        ->when($model->exists, fn ($query) => $query->where('id', '!=', $model->id))
                        ->where('phone', '!=', $parts['e164'])
                        ->where('phone_country_code', $parts['country_code'])->where('phone_national_number', $parts['national_number'])
                        ->exists();
                    if ($duplicate) {
                        throw ValidationException::withMessages(['phone' => __('This phone number already belongs to a customer in this branch.')]);
                    }
                }
                $model->setAttribute($field, $parts['e164'] ?? null);
                $model->setAttribute($field.'_country_code', $parts['country_code'] ?? null);
                $model->setAttribute($field.'_national_number', $parts['national_number'] ?? null);
            }
        });
    }
}
