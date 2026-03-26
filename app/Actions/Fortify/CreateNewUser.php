<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $settings = BusinessSetting::instance();
            $defaultBranchId = $settings->storefront_default_branch_id
                ?: Branch::query()->active()->orderBy('id')->value('id');

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'branch_id' => $defaultBranchId,
            ]);

            $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
            $user->assignRole($customerRole);

            if ($defaultBranchId) {
                Customer::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'branch_id' => $defaultBranchId,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => null,
                    ]
                );
            }

            return $user;
        });
    }
}
