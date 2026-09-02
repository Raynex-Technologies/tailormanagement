<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect([
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
        ])->mapWithKeys(fn (string $name) => [
            $name => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]),
        ]);

        // Preserve only capabilities that existed before Customer permissions
        // were separated from User administration. No other roles are changed.
        Permission::query()
            ->where('name', 'users.view')
            ->where('guard_name', 'web')
            ->first()
            ?->roles
            ->each(fn ($role) => $role->givePermissionTo($permissions['customers.view']));

        Permission::query()
            ->where('name', 'users.manage')
            ->where('guard_name', 'web')
            ->first()
            ?->roles
            ->each(fn ($role) => $role->givePermissionTo([
                $permissions['customers.create'],
                $permissions['customers.update'],
                $permissions['customers.delete'],
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally non-destructive: these permissions may be assigned by admins.
    }
};