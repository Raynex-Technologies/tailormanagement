<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_package_instances', function (Blueprint $table): void {
            $table->json('original_component_snapshot')->nullable()->after('component_snapshot');
            $table->json('configured_component_snapshot')->nullable()->after('original_component_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_package_instances', function (Blueprint $table): void {
            $table->dropColumn(['original_component_snapshot', 'configured_component_snapshot']);
        });
    }
};
