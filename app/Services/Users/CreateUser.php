<?php

namespace App\Services\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    /**
     * Create a new user with the given data.
     *
     * @param  array  $data  [name, email, password, phone?, branch_id?, role]
     * @param  User  $actor  The user performing the action
     * @return User
     *
     * @throws ValidationException
     */
    public function execute(array $data, User $actor): User
    {
        // Authorization check
        if (! $actor->can('users.manage')) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to create users.',
            ]);
        }

        // Validate role assignment
        $role = $data['role'] ?? null;
        if (! $role) {
            throw ValidationException::withMessages([
                'role' => 'A role is required.',
            ]);
        }

        $assignableRoles = UserPolicy::assignableRoles($actor);
        if (! in_array($role, $assignableRoles)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot assign this role.',
            ]);
        }

        // Determine branch_id
        $branchId = $this->determineBranchId($data, $role, $actor);

        return DB::transaction(function () use ($data, $role, $branchId, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'branch_id' => $branchId,
                'email_verified_at' => now(), // Auto-verify for admin-created users
            ]);

            // Assign role
            $user->assignRole($role);

            return $user->fresh(['roles', 'branch']);
        });
    }

    /**
     * Determine the branch_id based on actor permissions and role.
     */
    protected function determineBranchId(array $data, string $role, User $actor): ?int
    {
        $isGlobalRole = in_array($role, ['admin', 'superadmin']);

        // Global roles can have null branch_id (created by global admin only)
        if ($isGlobalRole && $actor->isGlobalAdmin()) {
            return $data['branch_id'] ?? null;
        }

        // Branch manager: force their branch_id
        if ($actor->isBranchManager()) {
            return $actor->branch_id;
        }

        // Global admin creating non-global role: branch_id required
        if ($actor->isGlobalAdmin()) {
            $branchId = $data['branch_id'] ?? BranchContext::id();

            if (! $branchId && ! $isGlobalRole) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Branch is required for this role.',
                ]);
            }

            return $branchId;
        }

        throw ValidationException::withMessages([
            'authorization' => 'You do not have permission to create users.',
        ]);
    }
}
