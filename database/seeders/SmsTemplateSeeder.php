<?php

namespace Database\Seeders;

use App\Models\SmsTemplate;
use Illuminate\Database\Seeder;

class SmsTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $existing = SmsTemplate::query()->orderBy('id')->first();

        SmsTemplate::query()->updateOrCreate(
            ['id' => $existing?->id ?? 1],
            [
                'templates' => SmsTemplate::normalizeTemplates($existing?->templates ?? []),
                'template_settings' => SmsTemplate::normalizeTemplateSettings($existing?->template_settings ?? []),
            ]
        );
    }
}
