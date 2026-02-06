<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'code' => 'BR-DSM-01',
                'name' => 'Dar es Salaam Main',
                'phone' => '+255222100100',
                'address' => 'Plot 123, Samora Avenue, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => 'BR-ARU-01',
                'name' => 'Arusha Branch',
                'phone' => '+255272500100',
                'address' => 'Plot 45, Sokoine Road, Arusha',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(
                ['code' => $branchData['code']],
                $branchData
            );
        }

        $this->command->info('Created ' . count($branches) . ' branches.');
    }
}
