<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemUiSettings
{
    public const DEFAULT_PRIMARY = '#111827';

    public const DEFAULT_SECONDARY_1 = '#2563EB';

    public const DEFAULT_SECONDARY_2 = '#F59E0B';

    public const CACHE_KEY = 'system-ui-settings:colors';

    public static function defaults(): array
    {
        return [
            'primary' => self::DEFAULT_PRIMARY,
            'secondary_1' => self::DEFAULT_SECONDARY_1,
            'secondary_2' => self::DEFAULT_SECONDARY_2,
        ];
    }

    public static function colors(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): array {
            $defaults = self::defaults();

            try {
                if (! Schema::hasTable('business_settings') || ! Schema::hasColumn('business_settings', 'ui_primary_color')) {
                    return $defaults;
                }

                $settings = BusinessSetting::query()->find(1);

                return [
                    'primary' => self::normalize($settings?->ui_primary_color) ?? $defaults['primary'],
                    'secondary_1' => self::normalize($settings?->ui_secondary_color_1) ?? $defaults['secondary_1'],
                    'secondary_2' => self::normalize($settings?->ui_secondary_color_2) ?? $defaults['secondary_2'],
                ];
            } catch (Throwable $exception) {
                report($exception);

                return $defaults;
            }
        });
    }

    public static function color(string $key): string
    {
        return self::colors()[$key] ?? self::defaults()[$key] ?? self::DEFAULT_PRIMARY;
    }

    public static function foreground(?string $color): string
    {
        $hex = self::normalize($color) ?? self::DEFAULT_PRIMARY;
        $red = hexdec(substr($hex, 1, 2));
        $green = hexdec(substr($hex, 3, 2));
        $blue = hexdec(substr($hex, 5, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance >= 150 ? '#111827' : '#FFFFFF';
    }

    public static function normalize(?string $color): ?string
    {
        $color = trim((string) $color);

        if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return null;
        }

        $hex = strtoupper(ltrim($color, '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.$hex;
    }

    public static function variables(): array
    {
        $colors = self::colors();

        return [
            '--tailorpro-primary' => $colors['primary'],
            '--tailorpro-secondary' => $colors['secondary_1'],
            '--tailorpro-secondary-2' => $colors['secondary_2'],
            '--tailorpro-primary-foreground' => self::foreground($colors['primary']),
            '--tailorpro-secondary-foreground' => self::foreground($colors['secondary_1']),
            '--tailorpro-secondary-2-foreground' => self::foreground($colors['secondary_2']),
        ];
    }

    public static function cssVariables(): string
    {
        return collect(self::variables())
            ->map(fn (string $value, string $name): string => "{$name}: {$value};")
            ->implode("\n            ");
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
