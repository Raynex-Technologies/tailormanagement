<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->foreignId('invoice_template_id')
                ->nullable()
                ->after('logo_path')
                ->constrained('invoice_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_template_id');
        });
    }
};

