<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sale_items', function (Blueprint $t) {
            $t->foreignId('inventory_item_variant_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('variation_description', 1000)->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('POS identity snapshots require a reviewed rollback.');
    }
};
