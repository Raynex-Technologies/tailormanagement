<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    protected array $branches = [];

    /**
     * Run the database seeds.
     * Creates demo data for local development/testing.
     */
    public function run(): void
    {
        // Load canonical branches only.
        $canonicalBranchNames = collect(BranchSeeder::sampleBranches())
            ->pluck('name')
            ->all();

        $this->branches = Branch::query()
            ->whereIn('name', $canonicalBranchNames)
            ->get()
            ->keyBy('name')
            ->toArray();

        if (count($this->branches) < 2) {
            $this->command->warn('Canonical branches are missing. Running BranchSeeder first...');
            $this->call(BranchSeeder::class);

            $this->branches = Branch::query()
                ->whereIn('name', $canonicalBranchNames)
                ->get()
                ->keyBy('name')
                ->toArray();
        }

        if (count($this->branches) < 2) {
            $this->command->error('Please run BranchSeeder first. Two canonical branches are required.');

            return;
        }

        $this->command->info('Creating demo users...');
        $this->createDemoUsers();

        // Create data for each branch
        foreach ($this->branches as $branchName => $branch) {
            $this->command->info("Creating demo data for branch: {$branch['name']}...");

            // Set branch context for auto-filling branch_id
            BranchContext::set($branch['id']);

            $this->createInventoryCategories($branch['id']);
            $this->createExpenseCategories($branch['id']);
            $this->createSuppliers($branch['id']);
            $this->createCustomers($branch['id']);
            $this->createInventoryItems($branch['id']);
            $this->createOrders($branch['id']);
        }

        // Clear branch context
        BranchContext::clear();

        $this->command->info('Demo data created successfully!');
    }

    /**
     * Create demo users for each role, distributed across branches.
     */
    protected function createDemoUsers(): void
    {
        $branch1 = Branch::query()->where('name', BranchSeeder::MAIN_BRANCH_NAME)->first();
        $branch2 = Branch::query()->where('name', BranchSeeder::OTHER_BRANCH_NAME)->first();

        // Global users (admin level)
        $globalUsers = [
            ['name' => 'Admin User', 'email' => 'admin@demo.test', 'role' => 'admin', 'branch_id' => null],
        ];

        // Branch managers - one per branch
        $branchManagers = [
            ['name' => 'Manager Main', 'email' => 'manager.main@demo.test', 'role' => 'branch_manager', 'branch_id' => $branch1?->id],
            ['name' => 'Manager Other', 'email' => 'manager.other@demo.test', 'role' => 'branch_manager', 'branch_id' => $branch2?->id],
        ];

        // Branch staff - distributed across branches
        $branchStaff = [
            // Main Branch staff
            ['name' => 'Accountant Main', 'email' => 'accountant.main@demo.test', 'role' => 'accountant', 'branch_id' => $branch1?->id],
            ['name' => 'Storekeeper Main', 'email' => 'storekeeper.main@demo.test', 'role' => 'storekeeper', 'branch_id' => $branch1?->id],
            ['name' => 'Tailor One Main', 'email' => 'tailor1.main@demo.test', 'role' => 'tailor', 'branch_id' => $branch1?->id],
            ['name' => 'Tailor Two Main', 'email' => 'tailor2.main@demo.test', 'role' => 'tailor', 'branch_id' => $branch1?->id],
            ['name' => 'Sales Main', 'email' => 'sales.main@demo.test', 'role' => 'sales', 'branch_id' => $branch1?->id],

            // Other Branch staff
            ['name' => 'Accountant Other', 'email' => 'accountant.other@demo.test', 'role' => 'accountant', 'branch_id' => $branch2?->id],
            ['name' => 'Storekeeper Other', 'email' => 'storekeeper.other@demo.test', 'role' => 'storekeeper', 'branch_id' => $branch2?->id],
            ['name' => 'Tailor One Other', 'email' => 'tailor1.other@demo.test', 'role' => 'tailor', 'branch_id' => $branch2?->id],
            ['name' => 'Sales Other', 'email' => 'sales.other@demo.test', 'role' => 'sales', 'branch_id' => $branch2?->id],
        ];

        $allUsers = array_merge($globalUsers, $branchManagers, $branchStaff);

        foreach ($allUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'branch_id' => $userData['branch_id'],
                ]
            );

            if (! $user->hasRole($userData['role'])) {
                $user->assignRole($userData['role']);
            }

            // Update branch_id if it changed (for existing users)
            if ($user->branch_id !== $userData['branch_id']) {
                $user->update(['branch_id' => $userData['branch_id']]);
            }
        }

        $this->command->info('Created ' . count($allUsers) . ' demo users.');
    }

    /**
     * Create inventory categories for a branch.
     */
    protected function createInventoryCategories(int $branchId): void
    {
        $categories = [
            ['name' => 'Fabrics', 'slug' => 'fabrics'],
            ['name' => 'Threads', 'slug' => 'threads'],
            ['name' => 'Buttons & Fasteners', 'slug' => 'buttons-fasteners'],
            ['name' => 'Zippers', 'slug' => 'zippers'],
            ['name' => 'Lining Materials', 'slug' => 'lining-materials'],
            ['name' => 'Interfacing', 'slug' => 'interfacing'],
            ['name' => 'Trims & Decorations', 'slug' => 'trims-decorations'],
            ['name' => 'Needles & Pins', 'slug' => 'needles-pins'],
            ['name' => 'Elastic & Bands', 'slug' => 'elastic-bands'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];

        foreach ($categories as $category) {
            InventoryCategory::withoutBranchScope()->firstOrCreate(
                ['slug' => $category['slug'], 'branch_id' => $branchId],
                array_merge($category, ['branch_id' => $branchId])
            );
        }
    }

    /**
     * Create expense categories for a branch.
     */
    protected function createExpenseCategories(int $branchId): void
    {
        $categories = [
            'Rent',
            'Utilities',
            'Salaries',
            'Transport',
            'Equipment Maintenance',
            'Office Supplies',
            'Marketing',
            'Miscellaneous',
        ];

        foreach ($categories as $name) {
            ExpenseCategory::withoutBranchScope()->firstOrCreate(
                ['name' => $name, 'branch_id' => $branchId],
                ['name' => $name, 'branch_id' => $branchId]
            );
        }
    }

    /**
     * Create suppliers for a branch.
     */
    protected function createSuppliers(int $branchId): void
    {
        Supplier::factory()->count(3)->create([
            'branch_id' => $branchId,
        ]);
    }

    /**
     * Create customers for a branch.
     */
    protected function createCustomers(int $branchId): void
    {
        Customer::factory()->count(10)->create([
            'branch_id' => $branchId,
        ]);
    }

    /**
     * Create inventory items for a branch.
     */
    protected function createInventoryItems(int $branchId): void
    {
        // Get categories for this branch
        $categories = InventoryCategory::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->toArray();

        if (empty($categories)) {
            return;
        }

        // Create items
        InventoryItem::factory()->count(15)->create([
            'branch_id' => $branchId,
            'inventory_category_id' => fn () => $categories[array_rand($categories)],
        ]);

        // Create stock for each item
        $items = InventoryItem::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->get();

        foreach ($items as $item) {
            InventoryStock::withoutBranchScope()->firstOrCreate(
                ['inventory_item_id' => $item->id, 'branch_id' => $branchId],
                [
                    'branch_id' => $branchId,
                    'inventory_item_id' => $item->id,
                    'qty_on_hand' => rand(10, 100),
                    'qty_reserved' => 0,
                ]
            );
        }
    }

    /**
     * Create orders for a branch.
     */
    protected function createOrders(int $branchId): void
    {
        // Get customers and tailors for this branch
        $customers = Customer::withoutBranchScope()
            ->where('branch_id', $branchId)
            ->pluck('id')
            ->toArray();

        $tailors = User::where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'tailor'))
            ->pluck('id')
            ->toArray();

        $salesUsers = User::where('branch_id', $branchId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'sales'))
            ->pluck('id')
            ->toArray();

        if (empty($customers)) {
            return;
        }

        Order::factory()->count(5)->create([
            'branch_id' => $branchId,
            'customer_id' => fn () => $customers[array_rand($customers)],
            'assigned_tailor_id' => fn () => ! empty($tailors) ? $tailors[array_rand($tailors)] : null,
            'created_by' => fn () => ! empty($salesUsers) ? $salesUsers[array_rand($salesUsers)] : null,
        ]);
    }
}
