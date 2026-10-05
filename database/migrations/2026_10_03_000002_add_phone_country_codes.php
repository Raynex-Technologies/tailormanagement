<?php

use App\Support\InternationalPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (InternationalPhone::storageFields() as $table => $fields) {
            foreach ($fields as $field) {
                foreach (['_country_code' => 5, '_national_number' => 15] as $suffix => $length) {
                    if (! Schema::hasColumn($table, $field.$suffix)) {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->string($field.$suffix, $length)->nullable());
                    }
                }
            }
            DB::table($table)->select(['id', ...$fields])->orderBy('id')->chunkById(200, function ($rows) use ($table, $fields) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($fields as $field) {
                        if ($parts = InternationalPhone::parts($row->{$field})) {
                            $updates[$field.'_country_code'] = $parts['country_code'];
                            $updates[$field.'_national_number'] = $parts['national_number'];
                        }
                    }
                    if ($updates !== []) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
        }
        if (! Schema::hasIndex('customers', 'customers_branch_phone_parts_index')) {
            Schema::table('customers', fn (Blueprint $table) => $table->index(['branch_id', 'phone_country_code', 'phone_national_number'], 'customers_branch_phone_parts_index'));
        }
    }

    public function down(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->dropIndex('customers_branch_phone_parts_index'));
        foreach (InternationalPhone::storageFields() as $table => $fields) {
            foreach ($fields as $field) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn([$field.'_country_code', $field.'_national_number']));
            }
        }
    }
};
