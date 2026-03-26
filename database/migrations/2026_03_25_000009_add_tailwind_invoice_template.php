<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_templates')) {
            return;
        }

        $now = now();

        DB::table('invoice_templates')
            ->where('slug', '!=', 'tailwind')
            ->update([
                'is_default' => false,
                'updated_at' => $now,
            ]);

        DB::table('invoice_templates')->upsert(
            [[
                'slug' => 'tailwind',
                'name' => 'Tailwind Basic',
                'description' => 'Clean Tailwind-inspired invoice layout with simple sections and neutral styling.',
                'blade_view' => 'invoices.templates.tailwind',
                'thumbnail_path' => null,
                'is_active' => true,
                'sort_order' => 5,
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['slug'],
            [
                'name',
                'description',
                'blade_view',
                'thumbnail_path',
                'is_active',
                'sort_order',
                'is_default',
                'updated_at',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_templates')) {
            return;
        }

        $now = now();

        DB::table('invoice_templates')
            ->where('slug', 'tailwind')
            ->delete();

        DB::table('invoice_templates')
            ->where('slug', '!=', 'classic')
            ->update([
                'is_default' => false,
                'updated_at' => $now,
            ]);

        DB::table('invoice_templates')
            ->where('slug', 'classic')
            ->update([
                'is_default' => true,
                'updated_at' => $now,
            ]);
    }
};
