<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

class EnvironmentWriter
{
    public function path(): string
    {
        return (string) config('install.env_file', base_path('.env'));
    }

    public function write(array $values): void
    {
        $content = File::exists($this->path()) ? File::get($this->path()) : '';

        foreach ($values as $key => $value) {
            $content = $this->setValue($content, $key, $value);
        }

        File::put($this->path(), rtrim($content).PHP_EOL);
    }

    public function get(string $key): ?string
    {
        if (! File::exists($this->path())) {
            return null;
        }

        preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*(.*)$/m', File::get($this->path()), $matches);

        if (! isset($matches[1])) {
            return null;
        }

        return trim($matches[1], " \t\n\r\0\x0B\"'");
    }

    public function assertRootEnvironmentFile(): void
    {
        if (dirname($this->path()) !== base_path()) {
            throw new RuntimeException('The installation environment file must be stored in the project root.');
        }
    }

    protected function setValue(string $content, string $key, mixed $value): string
    {
        $line = $key.'='.$this->formatValue($value);
        $pattern = '/^\s*#?\s*'.preg_quote($key, '/').'\s*=.*$/m';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, $line, $content, 1);
        }

        return rtrim($content).PHP_EOL.$line.PHP_EOL;
    }

    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value === '') {
            return '""';
        }

        if (preg_match('/\s|#|=|"|\'/', $value)) {
            return '"'.addcslashes($value, "\\\"").'"';
        }

        return $value;
    }
}
