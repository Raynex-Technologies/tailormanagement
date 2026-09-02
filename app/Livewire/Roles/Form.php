<?php

namespace App\Livewire\Roles;

use App\Support\PermissionGroups;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    use AuthorizesRequests;

    /** @var Role|int|string|null From route: id or resolved Role */
    public Role|int|string|null $role = null;

    public bool $isEdit = false;

    public string $name = '';

    /** @var array<int, bool> permission id => selected */
    public array $permissions = [];

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'alpha_dash',
                Rule::unique(config('permission.table_names.roles'), 'name')
                    ->where('guard_name', config('auth.defaults.guard'))
                    ->ignore($this->role?->id),
            ],
        ];
    }

    public function mount(Role|int|string|null $role = null): void
    {
        $this->authorize('roles.manage');

        // Resolve role from route (segment may be id)
        if ($role !== null && $role !== '' && is_numeric($role)) {
            $role = Role::findOrFail((int) $role);
        }
        if ($role instanceof Role && $role->exists) {
            $this->role = $role;
            $this->isEdit = true;
            $this->name = $role->name;
            $this->permissions = $role->permissions->pluck('id')->mapWithKeys(fn ($id) => [$id => true])->all();
        }
    }

    public function save(): void
    {
        $this->validate();

        $guardName = config('auth.defaults.guard');
        $dashboardViewId = Permission::query()->where('name', 'dashboard.view')->value('id');
        if (! $dashboardViewId || empty($this->permissions[$dashboardViewId])) {
            $dashboardPermissionIds = Permission::query()
                ->where('name', 'like', 'dashboard.%')
                ->pluck('id');

            foreach ($dashboardPermissionIds as $permissionId) {
                $this->permissions[$permissionId] = false;
            }
        }

        $permissionIds = array_keys(array_filter($this->permissions));

        if ($this->isEdit) {
            $this->role->update(['name' => $this->name]);
            $this->role->syncPermissions($permissionIds);
            session()->flash('success', __('Role updated successfully.'));
        } else {
            $role = Role::create(['name' => $this->name, 'guard_name' => $guardName]);
            $role->syncPermissions($permissionIds);
            session()->flash('success', __('Role created successfully.'));
        }

        $this->redirect(route('access-control.roles.index'), navigate: true);
    }

    public function selectModule(string $moduleLabel): void
    {
        $this->authorize('roles.manage');

        foreach ($this->permissionIdsForModule($moduleLabel) as $id) {
            $this->permissions[$id] = true;
        }
    }

    public function clearModule(string $moduleLabel): void
    {
        $this->authorize('roles.manage');

        foreach ($this->permissionIdsForModule($moduleLabel) as $id) {
            $this->permissions[$id] = false;
        }
    }

    public function getSelectedPermissionsCountProperty(): int
    {
        return count(array_filter($this->permissions));
    }

    /** @return array<int, int> */
    private function permissionIdsForModule(string $moduleLabel): array
    {
        $grouped = PermissionGroups::groupPermissions(Permission::query()->orderBy('name')->get());

        return ($grouped[$moduleLabel] ?? collect())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
    public function render()
    {
        $permissions = Permission::orderBy('name')->get();
        $grouped = PermissionGroups::groupPermissions($permissions);

        return view('livewire.roles.form', [
            'groupedPermissions' => $grouped,
            'dashboardViewPermissionId' => $permissions->firstWhere('name', 'dashboard.view')?->id,
        ])->title($this->isEdit ? __('Edit Role') : __('New Role'));
    }
}
