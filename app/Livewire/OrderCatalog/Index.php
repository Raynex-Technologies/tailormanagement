<?php

namespace App\Livewire\OrderCatalog;

use App\Enums\OrderCatalogItemType;
use App\Models\GarmentCategory;
use App\Models\MeasurementField;
use App\Models\OrderCatalogItem;
use App\Models\OrderPackageTemplate;
use App\Services\Orders\OrderCatalogAdministrationService;
use App\Services\Orders\OrderPackagePricingService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Order Catalog')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'items';

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $statusFilter = 'active';

    #[Url]
    public string $branchFilter = '';

    #[Url]
    public string $unitFilter = '';

    #[Url]
    public string $categoryFilter = '';

    public function mount(): void
    {
        $this->authorize('order_catalog.view');
        $this->tab = in_array($this->tab, ['items', 'packages', 'measurements'], true) ? $this->tab : 'items';
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['items', 'packages', 'measurements'], true), 404);
        $this->tab = $tab;
        $this->search = '';
        $this->statusFilter = 'active';
        $this->typeFilter = '';
        $this->unitFilter = '';
        $this->categoryFilter = '';
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'statusFilter', 'branchFilter', 'unitFilter', 'categoryFilter'], true)) {
            $this->resetPage();
        }
    }

    public function archiveItem(int $itemId): void
    {
        $this->authorize('order_catalog.items.manage');
        $item = OrderCatalogItem::query()->findOrFail($itemId);
        $this->assertManageable($item);
        $item->archive();
        session()->flash('success', __('Catalog item archived.'));
    }

    public function reactivateItem(int $itemId): void
    {
        $this->authorize('order_catalog.items.manage');
        $item = OrderCatalogItem::query()->findOrFail($itemId);
        $this->assertManageable($item);
        $item->restoreFromArchive();
        session()->flash('success', __('Catalog item reactivated.'));
    }

    public function archivePackage(int $templateId): void
    {
        $this->authorize('order_catalog.packages.manage');
        $template = OrderPackageTemplate::query()->findOrFail($templateId);
        $this->assertManageable($template);
        $template->archive();
        session()->flash('success', __('Package archived.'));
    }

    public function reactivatePackage(int $templateId): void
    {
        $this->authorize('order_catalog.packages.manage');
        $template = OrderPackageTemplate::query()->findOrFail($templateId);
        $this->assertManageable($template);
        $template->restoreFromArchive();
        session()->flash('success', __('Package reactivated.'));
    }

    public function archiveMeasurement(int $measurementId): void
    {
        $this->authorize('measurement_fields.manage');
        MeasurementField::query()->findOrFail($measurementId)->update(['is_active' => false]);
        session()->flash('success', __('Measurement definition archived.'));
    }

    public function reactivateMeasurement(int $measurementId): void
    {
        $this->authorize('measurement_fields.manage');
        MeasurementField::query()->findOrFail($measurementId)->update(['is_active' => true]);
        session()->flash('success', __('Measurement definition reactivated.'));
    }

    public function render()
    {
        $user = auth()->user();
        $branches = app(OrderCatalogAdministrationService::class)->permittedBranches($user);
        $allowedBranchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $selectedBranch = $this->branchFilter !== '' && in_array((int) $this->branchFilter, $allowedBranchIds, true)
            ? (int) $this->branchFilter
            : null;

        $items = null;
        $packages = null;
        $measurements = null;

        if ($this->tab === 'items') {
            $items = OrderCatalogItem::query()
                ->with(['branches:id,name', 'garmentCategory:id,name'])
                ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')))
                ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
                ->when($this->statusFilter === 'archived', fn (Builder $query) => $query->archived(), fn (Builder $query) => $query->active())
                ->when($selectedBranch, fn (Builder $query) => $query->availableForBranch($selectedBranch))
                ->when(! $user->isGlobalAdmin(), fn (Builder $query) => $query->availableForBranch((int) $user->branch_id))
                ->orderBy('name')
                ->paginate(12, pageName: 'itemsPage');
        } elseif ($this->tab === 'packages') {
            $packages = OrderPackageTemplate::query()
                ->with(['branches:id,name', 'items.catalogItem', 'items.inventoryItem'])
                ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')))
                ->when($this->statusFilter === 'archived', fn (Builder $query) => $query->archived(), fn (Builder $query) => $query->active())
                ->when($selectedBranch, fn (Builder $query) => $query->availableForBranch($selectedBranch))
                ->when(! $user->isGlobalAdmin(), fn (Builder $query) => $query->availableForBranch((int) $user->branch_id))
                ->orderBy('name')
                ->paginate(12, pageName: 'packagesPage');

            $pricing = app(OrderPackagePricingService::class);
            $packages->getCollection()->each(function (OrderPackageTemplate $template) use ($pricing): void {
                $template->setAttribute('pricing_summary', $pricing->summary($template));
            });
        } else {
            $measurements = MeasurementField::query()
                ->with('garmentCategories:id,name')
                ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $search) => $search
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')))
                ->when($this->statusFilter === 'archived', fn (Builder $query) => $query->where('is_active', false), fn (Builder $query) => $query->where('is_active', true))
                ->when($this->unitFilter !== '', fn (Builder $query) => $query->where('default_unit', $this->unitFilter))
                ->when($this->categoryFilter !== '', fn (Builder $query) => $query->whereHas('garmentCategories', fn (Builder $categories) => $categories->whereKey((int) $this->categoryFilter)))
                ->orderBy('name')
                ->paginate(12, pageName: 'measurementsPage');
        }

        return view('livewire.order-catalog.index', [
            'items' => $items,
            'packages' => $packages,
            'measurements' => $measurements,
            'branches' => $branches,
            'garmentCategories' => GarmentCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'measurementUnits' => ['cm', 'in', 'kg'],
            'types' => OrderCatalogItemType::cases(),
            'canManageItems' => $user->can('order_catalog.items.manage'),
            'canManagePackages' => $user->can('order_catalog.packages.manage'),
            'canManageMeasurements' => $user->can('measurement_fields.manage'),
        ]);
    }

    private function assertManageable(OrderCatalogItem|OrderPackageTemplate $record): void
    {
        try {
            app(OrderCatalogAdministrationService::class)->assertManageable(auth()->user(), $record);
        } catch (DomainException $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
