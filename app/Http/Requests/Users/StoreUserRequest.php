<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        // Global admins can always create users
        if ($user->isGlobalAdmin()) {
            return true;
        }

        // Branch managers can create users for their branch
        if ($user->isBranchManager()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->user();
        $isGlobalAdmin = $user?->isGlobalAdmin() ?? false;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name'),
                // Branch managers cannot create admin/superadmin
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->isBranchManager() && in_array($value, ['admin', 'superadmin'])) {
                        $fail('You cannot create admin or superadmin users.');
                    }
                },
            ],
        ];

        // Branch_id handling based on actor
        if ($isGlobalAdmin) {
            // Global admins can choose branch (required for non-admin roles)
            $rules['branch_id'] = [
                'nullable',
                'integer',
                Rule::exists('branches', 'id'),
                function ($attribute, $value, $fail) {
                    $role = $this->input('role');
                    if (! in_array($role, ['admin', 'superadmin']) && $value === null) {
                        $fail('Branch is required for non-admin users.');
                    }
                },
            ];
        } else {
            // Branch managers: branch_id is ignored (will be overridden to their branch)
            $rules['branch_id'] = ['nullable', 'integer'];
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'role.exists' => 'The selected role is invalid.',
            'branch_id.exists' => 'The selected branch does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        // If user is a branch manager, force their branch
        if ($user && $user->isBranchManager()) {
            $this->merge([
                'branch_id' => $user->branch_id,
            ]);
        }
    }
}
