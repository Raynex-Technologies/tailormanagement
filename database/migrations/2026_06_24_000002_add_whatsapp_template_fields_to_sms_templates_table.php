<?php

use App\Models\SmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sms_templates')) {
            return;
        }

        if (! Schema::hasColumn('sms_templates', 'whatsapp_templates')) {
            Schema::table('sms_templates', function ($table) {
                $table->json('whatsapp_templates')->nullable()->after('templates');
            });
        }

        if (! Schema::hasColumn('sms_templates', 'template_settings')) {
            Schema::table('sms_templates', function ($table) {
                $table->json('template_settings')->nullable()->after('whatsapp_templates');
            });
        }

        $row = DB::table('sms_templates')->orderBy('id')->first();

        if (! $row) {
            DB::table('sms_templates')->insert([
                'templates' => json_encode(SmsTemplate::defaultTemplates()),
                'whatsapp_templates' => json_encode(SmsTemplate::defaultTemplates()),
                'template_settings' => json_encode(SmsTemplate::defaultTemplateSettings()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $templates = json_decode((string) ($row->templates ?? ''), true);
        $whatsappTemplates = json_decode((string) ($row->whatsapp_templates ?? ''), true);
        $settings = json_decode((string) ($row->template_settings ?? ''), true);

        DB::table('sms_templates')
            ->where('id', $row->id)
            ->update([
                'templates' => json_encode(SmsTemplate::normalizeTemplates(is_array($templates) ? $templates : [])),
                'whatsapp_templates' => json_encode(SmsTemplate::normalizeTemplates(is_array($whatsappTemplates) ? $whatsappTemplates : (is_array($templates) ? $templates : []))),
                'template_settings' => json_encode(SmsTemplate::normalizeTemplateSettings(is_array($settings) ? $settings : [])),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no-op: database safety rules prohibit dropping stored template data.
    }
};
