<?php

namespace App\Services\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    /**
     * Update an existing user with the given data.
     *
     * @param  User  $user  The user to update
     * @param  array  $data  [name?, email?, password?, phone?, branch_id?, role?]
     * @param  User  $actor  The user performing the action
     * @return User
     *
     * @throws ValidationException
     */
    public function execute(User $user, array $data, User $actor): User
    {
        // Authorization check
        if (! $actor->can('update', $user)) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to update this user.',
            ]);
        }

        // Validate role change if provided
        if (isset($data['role'])) {
            $this->validateRoleChange($user, $data['role'], $actor);
        }

        // Validate branch change if provided
        if (array_key_exists('branch_id', $data)) {
            $this->validateBranchChange($user, $data, $actor);
        }

        return DB::transaction(function () use ($user, $data, $actor) {
            $updateData = [];

            if (isset($data['name'])) {
                $updateData['name'] = $data['name'];
            }

            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // Branch changes - only global admin can change branches
            if (array_key_exists('branch_id', $data) && $actor->isGlobalAdmin()) {
                $updateData['branch_id'] = $data['branch_id'];
            }

            if (! empty($updateData)) {
                $user->update($updateData);
            }

            // Role change
            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            return $user->fresh(['roles', 'branch']);
        });
    }

    /**
     * Validate role change permissions.
     */
    protected function validateRoleChange(User $user, string $newRole, User $actor): void
    {
        $assignableRoles = UserPolicy::assignableRoles($actor);

        if (! in_array($newRole, $assignableRoles)) {
            throw ValidationException::withMessages([
                'role' => 'You cannot assign this role.',
            ]);
        }

        // Check if user is changing their own role to something that would lose permissions
        if ($user->id === $actor->id) {
            $currentRole = $user->roles->first()?->name;
            $globalRoles = ['admin', 'superadmin'];

            if (in_array($currentRole, $globalRoles) && ! in_array($newRole, $globalRoles)) {
                throw ValidationException::withMessages([
                    'role' => 'You cannot demote yourself from a global admin role.',
                ]);
            }
        }
    }

    /**
     * Validate branch change permissions.
     */
    protected function validateBranchChange(User $user, array $data, User $actor): void
    {
        // Only global admin can change branches
        if (! $actor->isGlobalAdmin()) {
            throw ValidationException::withMessages([
                'branch_id' => 'You cannot change user branches.',
            ]);
        }

        $newBranchId = $data['branch_id'];
        $role = $data['role'] ?? $user->roles->first()?->name;
        $isGlobalRole = in_array($role, ['admin', 'superadmin']);

        // Non-global roles require a branch
        if (! $isGlobalRole && empty($newBranchId)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Branch is required for this role.',
            ]);
        }
    }
}
