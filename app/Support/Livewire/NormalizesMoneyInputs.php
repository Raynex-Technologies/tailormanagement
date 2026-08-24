<?php

namespace App\Support\Livewire;

use App\Support\MoneyInputNormalizer;
use Illuminate\Support\Str;

trait NormalizesMoneyInputs
{
    public function updated(string $property): void
    {
        $this->normalizeMoneyInputProperty($property);
    }

    protected function normalizeMoneyInputProperty(string $property): void
    {
        if (! collect(MoneyInputRegistry::fieldsFor(static::class))->contains(
            fn (string $pattern): bool => Str::is($pattern, $property),
        )) {
            return;
        }

        $value = data_get($this, $property);
        $normalized = MoneyInputNormalizer::normalize($value);

        if ($normalized !== $value) {
            data_set($this, $property, $normalized);
        }
    }

    protected function normalizeMoneyInputs(): void
    {
        foreach (MoneyInputRegistry::fieldsFor(static::class) as $pattern) {
            if (! str_contains($pattern, '.*.')) {
                $this->normalizeMoneyInputProperty($pattern);

                continue;
            }

            [$collection, $child] = explode('.*.', $pattern, 2);

            foreach (array_keys((array) data_get($this, $collection, [])) as $key) {
                $this->normalizeMoneyInputProperty($collection.'.'.$key.'.'.$child);
            }
        }
    }
}
