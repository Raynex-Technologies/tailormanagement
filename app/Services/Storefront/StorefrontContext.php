<?php

namespace App\Services\Storefront;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\User;
use RuntimeException;

class StorefrontContext
{
    public function settings(): BusinessSetting
    {
        return BusinessSetting::instance();
    }

    public function isStorefrontEnabled(): bool
    {
        return (bool) $this->settings()->storefront_enabled;
    }

    public function isCatalogMode(): bool
    {
        return (bool) $this->settings()->storefront_catalog_mode;
    }

    public function isCustomPortalEnabled(): bool
    {
        return (bool) $this->settings()->custom_order_portal_enabled;
    }

    public function isGuestCheckoutEnabled(): bool
    {
        return (bool) $this->settings()->guest_checkout_enabled;
    }

    public function allowsCashOnDelivery(): bool
    {
        return (bool) $this->settings()->allow_cash_on_delivery;
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->settings()->storefront_currency ?: 'TZS'));
    }

    public function defaultBranchId(): int
    {
        $configured = (int) ($this->settings()->storefront_default_branch_id ?? 0);

        if ($configured > 0) {
            return $configured;
        }

        $branchId = Branch::query()->active()->orderBy('id')->value('id');

        if ($branchId) {
            return (int) $branchId;
        }

        throw new RuntimeException('No active branch is configured for storefront operations.');
    }

    public function resolveCustomerForUser(User $user): Customer
    {
        $customer = Customer::query()->where('user_id', $user->id)->first();

        if ($customer) {
            return $customer;
        }

        if (! $user->branch_id) {
            $user->forceFill([
                'branch_id' => $this->defaultBranchId(),
            ])->save();
            $user->refresh();
        }

        return Customer::create([
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => null,
        ]);
    }
}
