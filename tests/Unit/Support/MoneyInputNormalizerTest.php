<?php

namespace Tests\Unit\Support;

use App\Support\MoneyInputNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyInputNormalizerTest extends TestCase
{
    #[DataProvider('formattedValues')]
    public function test_it_removes_grouping_without_changing_decimal_semantics(string $input, string $expected): void
    {
        $this->assertSame($expected, MoneyInputNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function formattedValues(): array
    {
        return [
            'integer' => ['1,250,000', '1250000'],
            'decimal precision' => ['1,250,000.50', '1250000.50'],
            'regular spaces' => ['1 250 000.50', '1250000.50'],
            'non-breaking spaces' => ["1\u{00A0}250\u{202F}000.50", '1250000.50'],
        ];
    }

    public function test_it_leaves_non_string_values_untouched(): void
    {
        $this->assertSame(1250000, MoneyInputNormalizer::normalize(1250000));
        $this->assertNull(MoneyInputNormalizer::normalize(null));
    }
}
