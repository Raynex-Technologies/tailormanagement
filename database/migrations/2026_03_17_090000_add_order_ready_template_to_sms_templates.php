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

        $row = DB::table('sms_templates')->orderBy('id')->first();

        if (! $row) {
            DB::table('sms_templates')->insert([
                'templates' => json_encode(SmsTemplate::defaultTemplates()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $existing = json_decode((string) $row->templates, true);
        $templates = array_replace(SmsTemplate::defaultTemplates(), is_array($existing) ? $existing : []);

        DB::table('sms_templates')
            ->where('id', $row->id)
            ->update([
                'templates' => json_encode($templates),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No-op: template options are additive.
    }
};
