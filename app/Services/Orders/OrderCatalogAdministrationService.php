<?php

namespace App\Services\Orders;

use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class OrderCatalogAdministrationService
{
    /** @return Collection<int, Branch> */
    public function permittedBranches(User $user): Collection
    {
        return Branch::query()
            ->active()
            ->when(! $user->isGlobalAdmin(), fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /** @param array<int, int|string> $branchIds @return array<int, int> */
    public function normalizeAvailability(User $user, bool $allBranches, array $branchIds): array
    {
        if (! $user->isGlobalAdmin()) {
            if (! $user->branch_id) {
                throw new DomainException('Your account must be assigned to a branch to manage catalog availability.');
            }

            if ($allBranches) {
                throw new DomainException('Only a global administrator can make catalog content available at all branches.');
            }

            return [(int) $user->branch_id];
        }

        if ($allBranches) {
            return [];
        }

        $ids = collect($branchIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $validIds = Branch::query()->active()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id);

        if ($ids->isEmpty() || $validIds->count() !== $ids->count()) {
            throw new DomainException('Select at least one active branch that you are permitted to manage.');
        }

        return $validIds->all();
    }

    public function assertManageable(User $user, OrderCatalogItem|OrderPackageTemplate $record): void
    {
        if ($user->isGlobalAdmin()) {
            return;
        }

        $branchIds = $record->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
        if ($record->available_all_branches || $branchIds !== [(int) $user->branch_id]) {
            throw new DomainException('You may only modify catalog content assigned exclusively to your branch.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, int>  $branchIds
     */
    public function assertPackageSourcesValid(bool $allBranches, array $branchIds, array $components): void
    {
        $catalogIds = collect($components)->where('source_type', 'catalog_item')->pluck('source_id')->map(fn ($id) => (int) $id)->unique();
        $inventoryIds = collect($components)->where('source_type', 'inventory_item')->pluck('source_id')->map(fn ($id) => (int) $id)->unique();

        $catalogItems = OrderCatalogItem::query()->whereKey($catalogIds)->get()->keyBy('id');
        foreach ($catalogIds as $catalogId) {
            $item = $catalogItems->get($catalogId);
            if (! $item) {
                throw new DomainException('A selected garment or service is no longer available.');
            }

            foreach ($branchIds as $branchId) {
                if (! $item->isAvailableForBranch($branchId)) {
                    throw new DomainException("{$item->name} is not available at every selected package branch.");
                }
            }

            if ($allBranches && ! $item->available_all_branches) {
                throw new DomainException("{$item->name} is not available at all branches.");
            }
        }

        if ($inventoryIds->isEmpty()) {
            return;
        }

        if ($allBranches) {
            throw new DomainException('A package containing inventory products cannot be available at all branches because inventory is branch-owned.');
        }

        $inventoryItems = InventoryItem::withoutBranchScope()->whereKey($inventoryIds)->get(['id', 'name', 'branch_id']);
        if ($inventoryItems->count() !== $inventoryIds->count()) {
            throw new DomainException('A selected inventory product is no longer available.');
        }

        if (count($branchIds) !== 1 || $inventoryItems->contains(fn ($item) => (int) $item->branch_id !== (int) $branchIds[0])) {
            throw new DomainException('Inventory products may only be used in a package assigned exclusively to their owning branch.');
        }
    }

    public function commercialSignature(OrderPackageTemplate $template): string
    {
        $template->loadMissing(['branches:id', 'items.catalogItem', 'items.inventoryItem']);

        return hash('sha256', json_encode([
            'name' => $template->name,
            'description' => $template->description,
            'cover_image_path' => $template->cover_image_path,
            'available_all_branches' => $template->available_all_branches,
            'branches' => $template->branches->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'components' => $template->items->map(fn ($item) => [
                'catalog' => $item->order_catalog_item_id,
                'inventory' => $item->inventory_item_id,
                'minimum' => (string) $item->minimum_quantity,
                'default' => (string) $item->default_quantity,
                'maximum' => $item->maximum_quantity === null ? null : (string) $item->maximum_quantity,
                'price' => (string) $item->package_unit_price,
                'sort' => (int) $item->sort_order,
            ])->values()->all(),
        ], JSON_THROW_ON_ERROR));
    }
}
