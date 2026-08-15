<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->foreignId('whatsapp_message_id')->nullable()->after('provider_message_id')->constrained('whatsapp_messages')->nullOnDelete();
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->string('whatsapp_phone', 32)->nullable()->after('phone');
            $table->timestamp('whatsapp_opted_in_at')->nullable()->after('whatsapp_phone');
            $table->timestamp('whatsapp_marketing_opted_in_at')->nullable()->after('whatsapp_opted_in_at');
            $table->index(['branch_id', 'whatsapp_phone'], 'customers_branch_whatsapp_phone_idx');
        });

        DB::table('customers')->select(['id', 'phone'])->orderBy('id')->chunkById(500, function ($customers) {
            foreach ($customers as $customer) {
                $normalized = \App\Support\Phone::toE164Tz($customer->phone);
                if ($normalized) {
                    DB::table('customers')->where('id', $customer->id)->update(['whatsapp_phone' => $normalized]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_logs', fn (Blueprint $table) => $table->dropConstrainedForeignId('whatsapp_message_id'));
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_branch_whatsapp_phone_idx');
            $table->dropColumn(['whatsapp_phone', 'whatsapp_opted_in_at', 'whatsapp_marketing_opted_in_at']);
        });
    }
};
