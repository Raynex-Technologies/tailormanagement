<?php

namespace App\Livewire\Roles;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app.sidebar')]
#[Title('Roles & Permissions')]
class Index extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('roles.manage');
    }

    public function deleteRole(int $id): void
    {
        $this->authorize('roles.manage');

        $role = Role::withCount('users')->findOrFail($id);

        if ($role->users_count > 0) {
            session()->flash('error', "Cannot delete the role \"{$role->name}\" because it has {$role->users_count} user(s) assigned. Reassign or remove users from this role first.");

            return;
        }

        $name = $role->name;
        $role->delete();

        session()->flash('success', "Role \"{$name}\" has been deleted.");
    }

    public function render()
    {
        $roles = Role::withCount('users', 'permissions')
            ->orderBy('name')
            ->get();

        return view('livewire.roles.index', [
            'roles' => $roles,
        ]);
    }
}
