<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('appointment_types')) {
            return;
        }

        $now = now();

        foreach ($this->defaults() as $index => $type) {
            if (DB::table('appointment_types')->where('code', $type['code'])->exists()) {
                continue;
            }

            DB::table('appointment_types')->insert([
                'code' => $type['code'],
                'name' => $type['name'],
                'description' => $type['description'],
                'default_duration_minutes' => $type['default_duration_minutes'],
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'requires_approval' => $type['requires_approval'],
                'is_public' => true,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive data migration. Leave appointment types in place.
    }

    protected function defaults(): array
    {
        return [
            ['code' => 'measurement', 'name' => 'Measurement Appointment', 'description' => 'Measurement Appointment booking slot.', 'default_duration_minutes' => 30, 'requires_approval' => true],
            ['code' => 'fitting', 'name' => 'Fitting Appointment', 'description' => 'Fitting Appointment booking slot.', 'default_duration_minutes' => 30, 'requires_approval' => true],
            ['code' => 'consultation', 'name' => 'Style Consultation', 'description' => 'Style Consultation booking slot.', 'default_duration_minutes' => 45, 'requires_approval' => true],
            ['code' => 'alteration_dropoff', 'name' => 'Alteration Drop-off', 'description' => 'Alteration Drop-off booking slot.', 'default_duration_minutes' => 20, 'requires_approval' => true],
            ['code' => 'pickup', 'name' => 'Pickup Appointment', 'description' => 'Pickup Appointment booking slot.', 'default_duration_minutes' => 15, 'requires_approval' => false],
            ['code' => 'bulk_consultation', 'name' => 'Bulk/Uniform Consultation', 'description' => 'Bulk/Uniform Consultation booking slot.', 'default_duration_minutes' => 60, 'requires_approval' => true],
        ];
    }
};
