<?php

namespace App\Livewire\Users;

use App\Models\Branch;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Users\CreateUser;
use App\Services\Users\UpdateUser;
use App\Support\BranchContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?User $user = null;
    public bool $isEdit = false;

    // Form fields
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public ?int $branch_id = null;
    public string $role = '';
    public bool $resetPassword = false;

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::in($this->getAssignableRoles())],
        ];

        // Password rules
        if (! $this->isEdit || $this->resetPassword) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'role.in' => 'You cannot assign this role.',
        ];
    }

    public function mount(?User $user = null): void
    {
        $actor = auth()->user();

        if ($user && $user->exists) {
            $this->authorize('update', $user);
            $this->user = $user;
            $this->isEdit = true;

            // Populate form
            $this->name = $user->name;
            $this->email = $user->email;
            $this->branch_id = $user->branch_id;
            $this->role = $user->roles->first()?->name ?? '';
        } else {
            $this->authorize('create', User::class);

            // Default branch for non-global admins
            if (! $actor->isGlobalAdmin()) {
                $this->branch_id = $actor->branch_id;
            } else {
                $this->branch_id = BranchContext::id();
            }
        }
    }

    public function updatedRole(): void
    {
        // If role is admin/superadmin and user is global admin, branch can be null
        $globalRoles = ['admin', 'superadmin'];
        if (in_array($this->role, $globalRoles) && auth()->user()->isGlobalAdmin()) {
            // Allow null branch for global roles
        }
    }

    public function save(): void
    {
        $this->validate();

        $actor = auth()->user();

        try {
            if ($this->isEdit) {
                $data = [
                    'name' => $this->name,
                    'email' => $this->email,
                    'role' => $this->role,
                ];

                if ($actor->isGlobalAdmin()) {
                    $data['branch_id'] = $this->branch_id;
                }

                if ($this->resetPassword && ! empty($this->password)) {
                    $data['password'] = $this->password;
                }

                $user = app(UpdateUser::class)->execute($this->user, $data, $actor);
                session()->flash('success', 'User updated successfully.');
            } else {
                $data = [
                    'name' => $this->name,
                    'email' => $this->email,
                    'password' => $this->password,
                    'branch_id' => $this->branch_id,
                    'role' => $this->role,
                ];

                $user = app(CreateUser::class)->execute($data, $actor);
                session()->flash('success', 'User created successfully.');
            }

            $this->redirect(route('users.show', $user), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save user: '.$e->getMessage());
        }
    }

    public function getAssignableRoles(): array
    {
        return UserPolicy::assignableRoles(auth()->user());
    }

    public function render()
    {
        $actor = auth()->user();
        $branches = $actor->isGlobalAdmin()
            ? Branch::active()->orderBy('name')->get(['id', 'name'])
            : collect();

        $assignableRoles = $this->getAssignableRoles();

        // Check if branch selector should be shown
        $showBranchSelector = $actor->isGlobalAdmin();

        // Check if selected role requires a branch
        $globalRoles = ['admin', 'superadmin'];
        $roleRequiresBranch = ! in_array($this->role, $globalRoles);

        return view('livewire.users.form', [
            'branches' => $branches,
            'assignableRoles' => $assignableRoles,
            'showBranchSelector' => $showBranchSelector,
            'roleRequiresBranch' => $roleRequiresBranch,
        ])->title($this->isEdit ? __('Edit User') : __('New User'));
    }
}
