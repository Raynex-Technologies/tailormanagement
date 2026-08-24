<?php

namespace App\Services\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CustomerCreator
{
    /** @return array<string, array<int, mixed>> */
    public function rules(int $branchId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')
                    ->where(fn ($query) => $query->where('branch_id', $branchId)),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(User $actor, int $branchId, array $attributes): Customer
    {
        Gate::forUser($actor)->authorize('users.manage');
        $branch = Branch::query()->active()->findOrFail($branchId);

        if (! $actor->isGlobalAdmin() && (int) $actor->branch_id !== $branch->id) {
            throw new AuthorizationException(__('You cannot create customers for this branch.'));
        }

        $validated = Validator::make($attributes, $this->rules($branch->id))->validate();

        return Customer::query()->create([
            'branch_id' => $branch->id,
            'name' => trim((string) $validated['name']),
            'phone' => filled($validated['phone'] ?? null) ? trim((string) $validated['phone']) : null,
            'email' => filled($validated['email'] ?? null) ? trim((string) $validated['email']) : null,
            'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
        ]);
    }
}
