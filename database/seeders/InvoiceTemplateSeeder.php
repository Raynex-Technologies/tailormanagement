<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InvoiceTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('invoice_templates')) {
            return;
        }

        $now = now();

        DB::table('invoice_templates')->upsert(
            [
                [
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
                ],
                [
                    'slug' => 'classic',
                    'name' => 'Classic',
                    'description' => 'Traditional invoice with structured sections and clear totals.',
                    'blade_view' => 'invoices.templates.classic',
                    'thumbnail_path' => null,
                    'is_active' => true,
                    'sort_order' => 10,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'modern',
                    'name' => 'Modern',
                    'description' => 'Contemporary card layout with gradient accents and compact summaries.',
                    'blade_view' => 'invoices.templates.modern',
                    'thumbnail_path' => null,
                    'is_active' => true,
                    'sort_order' => 20,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'minimal',
                    'name' => 'Minimal',
                    'description' => 'Lightweight design focused on whitespace and essential information.',
                    'blade_view' => 'invoices.templates.minimal',
                    'thumbnail_path' => null,
                    'is_active' => true,
                    'sort_order' => 30,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'bold',
                    'name' => 'Bold',
                    'description' => 'Strong typographic hierarchy with high-contrast totals and highlights.',
                    'blade_view' => 'invoices.templates.bold',
                    'thumbnail_path' => null,
                    'is_active' => true,
                    'sort_order' => 40,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'slug' => 'elegant',
                    'name' => 'Elegant',
                    'description' => 'Refined invoice style with balanced spacing and subtle framing.',
                    'blade_view' => 'invoices.templates.elegant',
                    'thumbnail_path' => null,
                    'is_active' => true,
                    'sort_order' => 50,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['slug'],
            ['name', 'description', 'blade_view', 'thumbnail_path', 'is_active', 'sort_order', 'is_default', 'updated_at']
        );
    }
}
