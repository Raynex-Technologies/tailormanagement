<?php

namespace App\Data\WhatsApp;

final readonly class TemplateDefinition
{
    public function __construct(public string $name, public string $language, public string $category, public array $components, public array $variableMappings = []) {}

    public static function fromArray(array $data): self
    {
        return new self((string) ($data['name'] ?? ''), (string) ($data['language'] ?? ''), strtoupper((string) ($data['category'] ?? '')), array_values($data['components'] ?? []), $data['variable_mappings'] ?? []);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'language' => $this->language, 'category' => $this->category, 'components' => $this->components, 'variable_mappings' => $this->variableMappings];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
