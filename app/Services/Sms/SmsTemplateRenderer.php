<?php

namespace App\Services\Sms;

class SmsTemplateRenderer
{
    public function render(string $template, array $data): string
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            $safeValue = (string) $value;
            $replacements['{'.$key.'}'] = $safeValue;
            $replacements['{{'.$key.'}}'] = $safeValue;
            $replacements['{{ '.$key.' }}'] = $safeValue;
        }

        return strtr($template, $replacements);
    }
}
