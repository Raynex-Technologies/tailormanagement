<?php

namespace App\Services\WhatsApp\Templates;

use App\Data\WhatsApp\TemplateDefinition;

class MetaWhatsappTemplateValidator
{
    public function validate(TemplateDefinition $d): TemplateValidationResult
    {
        $e = [];
        $w = [];
        $add = function (&$bag, $field, $rule, $message) {
            $bag[] = ['field' => $field, 'rule' => $rule, 'message' => $message];
        };
        if (! preg_match('/^[a-z0-9_]+$/', $d->name) || strlen($d->name) > config('meta-whatsapp-templates.name_max')) {
            $add($e, 'name', 'name_format', 'Use lowercase letters, numbers, and underscores only.');
        } if (! in_array($d->category, config('meta-whatsapp-templates.categories'), true)) {
            $add($e, 'category', 'category', 'Choose Utility, Marketing, or Authentication.');
        } if (! in_array($d->language, config('meta-whatsapp-templates.languages'), true)) {
            $add($e, 'language', 'language', 'Choose a supported language code.');
        } $types = array_column($d->components, 'type');
        if (count(array_filter($types, fn ($v) => $v === 'BODY')) !== 1) {
            $add($e, 'components', 'body_required', 'Exactly one body component is required.');
        } foreach ($d->components as $i => $c) {
            $type = strtoupper((string) ($c['type'] ?? ''));
            $format = strtoupper((string) ($c['format'] ?? ''));
            $text = (string) ($c['text'] ?? '');
            if ($type === 'HEADER' && ! in_array($format, array_diff(config('meta-whatsapp-templates.header_formats'), ['NONE']), true)) {
                $add($e, "components.$i.format", 'header_format', 'Unsupported header format.');
            } if ($type === 'HEADER' && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && blank($c['example_handle'] ?? null)) {
                $add($e, "components.$i.example_handle", 'media_example', 'Upload a representative media example.');
            } if ($type === 'BUTTONS') {
                $buttons = $c['buttons'] ?? [];
                if (count($buttons) > config('meta-whatsapp-templates.button_max')) {
                    $add($e, "components.$i.buttons", 'button_count', 'Too many buttons.');
                } foreach ($buttons as $j => $b) {
                    if (! in_array(strtoupper((string) ($b['type'] ?? '')), config('meta-whatsapp-templates.button_types'), true)) {
                        $add($e, "components.$i.buttons.$j", 'button_type', 'Unsupported button type.');
                    }
                }
            } if (in_array($type, ['HEADER', 'BODY'], true)) {
                $this->variables($text, $c['examples'] ?? [], $d->variableMappings, $e, $i, $add);
            }
        } $body = collect($d->components)->firstWhere('type', 'BODY')['text'] ?? '';
        if ($d->category === 'UTILITY' && preg_match('/\b(offer|discount|sale|promotion|buy now)\b/i', $body)) {
            $add($w, 'category', 'category_risk', 'This wording appears promotional and may be recategorized or rejected by Meta.');
        } if (preg_match('/^(\s*\{\{\d+\}\}\s*)+$/', $body)) {
            $add($w, 'body', 'placeholder_only', 'Add meaningful fixed text around variables.');
        }

        return new TemplateValidationResult($e, $w);
    }

    protected function variables(string $text, array $examples, array $mappings, array &$errors, int $component, callable $add): void
    {
        preg_match_all('/\{\{(\d+)\}\}/', $text, $m);
        if (substr_count($text, '{{') !== count($m[0]) || substr_count($text, '}}') !== count($m[0])) {
            $add($errors, "components.$component.text", 'braces', 'Fix unmatched or malformed variable braces.');
        }$numbers = array_map('intval', $m[1]);
        $unique = array_values(array_unique($numbers));
        if ($unique && $unique !== range(1, max($unique))) {
            $add($errors, "components.$component.text", 'variable_sequence', 'Variables must be sequential starting at {{1}}.');
        }foreach ($unique as $n) {
            if (blank($examples[(string) $n] ?? $examples[$n] ?? null)) {
                $add($errors, "components.$component.examples.$n", 'example_required', "Add a representative example for {{$n}}.");
            }if (blank($mappings[(string) $n] ?? $mappings[$n] ?? null)) {
                $add($errors, "variable_mappings.$n", 'semantic_required', "Choose an internal meaning for {{$n}}.");
            }
        }
    }
}
