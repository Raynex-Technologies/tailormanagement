<?php

namespace Tests\Unit;

use App\Support\InternationalPhone;
use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InternationalPhoneTest extends TestCase
{
    public static function invalidNumbers(): array
    {
        return array_map(fn ($value) => [$value], ['+255', '071', 'abc0712345678', '0712345678ext9', '+255++712345678', '12345', '+999712345678', '+255712345678999', '1e10', '255.712345678']);
    }

    #[DataProvider('invalidNumbers')]
    public function test_invalid_numbers_are_not_normalized(string $value): void
    {
        $this->assertNull(Phone::toE164Tz($value));
    }

    public function test_local_and_international_numbers_have_canonical_parts(): void
    {
        foreach (['0712345678', '712345678', '255712345678', '+255 712 345 678', '+2550712345678'] as $input) {
            $this->assertSame(['e164' => '+255712345678', 'country_code' => '+255', 'national_number' => '712345678'], InternationalPhone::parts($input));
        }
        $this->assertSame(['e164' => '+447911123456', 'country_code' => '+44', 'national_number' => '7911123456'], InternationalPhone::parts('+447911123456'));
        $this->assertSame('+254712345678', Phone::toE164Tz('+254712345678'));
        $this->assertSame('TZ', InternationalPhone::countries()[0]['region']);
        $this->assertSame('+255', InternationalPhone::countries()[0]['code']);
        $this->assertGreaterThan(200, count(InternationalPhone::countries()));
    }
}
