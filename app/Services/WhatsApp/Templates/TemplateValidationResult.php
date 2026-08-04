<?php

namespace App\Services\WhatsApp\Templates;

final readonly class TemplateValidationResult
{
    public function __construct(public array $errors = [], public array $warnings = []) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }

    public function toArray(): array
    {
        return ['valid' => $this->valid(), 'errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
