<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public const MAIN_BRANCH_NAME = 'Main Branch';
    public const OTHER_BRANCH_NAME = 'Other Branch';
    public const MAIN_BRANCH_CODE = 'BR-MAIN-01';
    public const OTHER_BRANCH_CODE = 'BR-OTHER-01';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = self::sampleBranches();

        $created = 0;

        foreach ($branches as $branchData) {
            $branch = Branch::firstOrCreate(
                ['name' => $branchData['name']],
                $branchData
            );

            if ($branch->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->command->info('Branch seeding complete. Created: '.$created.', ensured total canonical branches: '.count($branches).'.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function sampleBranches(): array
    {
        return [
            [
                'code' => self::MAIN_BRANCH_CODE,
                'name' => self::MAIN_BRANCH_NAME,
                'phone' => '+255222100100',
                'address' => 'Plot 123, Samora Avenue, Dar es Salaam',
                'is_active' => true,
            ],
            [
                'code' => self::OTHER_BRANCH_CODE,
                'name' => self::OTHER_BRANCH_NAME,
                'phone' => '+255272500100',
                'address' => 'Plot 45, Sokoine Road, Arusha',
                'is_active' => true,
            ],
        ];
    }
}
