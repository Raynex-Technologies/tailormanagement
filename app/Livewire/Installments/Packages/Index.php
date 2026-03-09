<?php

namespace App\Livewire\Installments\Packages;

use App\Models\Branch;
use App\Models\Package;
use App\Models\PackageItem;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Installment Packages')]
class Index extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public int $perPage = 15;

    public bool $showPackageModal = false;

    public bool $isEditing = false;

    public ?int $editingId = null;

    public ?int $branch_id = null;

    public bool $showBranchSelector = false;

    public string $name = '';

    public ?float $price = null;

    public int $duration_value = 1;

    public string $duration_unit = 'months';

    public string $description = '';

    public bool $is_active = true;

    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    public ?int $itemPackageId = null;

    public string $itemName = '';

    public string $itemDescription = '';

    public int $itemSortOrder = 0;

    public $itemImageUpload = null;

    public ?string $existingItemImagePath = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Package::class);
    }

    protected function packageRules(): array
    {
        $branchId = $this->branchIdForForm();

        $nameRule = Rule::unique('packages', 'name')
            ->where(fn ($query) => $query->where('branch_id', $branchId));

        if ($this->editingId !== null) {
            $nameRule = $nameRule->ignore($this->editingId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'price' => ['required', 'numeric', 'min:0.01'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:120'],
            'duration_unit' => ['required', 'in:days,weeks,months,years'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];

        if (auth()->user()->isGlobalAdmin() && ! $this->isEditing) {
            $rules['branch_id'] = ['required', 'exists:branches,id'];
        }

        return $rules;
    }

    protected function itemRules(): array
    {
        return [
            'itemName' => ['required', 'string', 'max:255'],
            'itemDescription' => ['nullable', 'string', 'max:2000'],
            'itemSortOrder' => ['required', 'integer', 'min:0'],
            'itemImageUpload' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Package::class);

        $this->resetPackageForm();
        $this->showBranchSelector = auth()->user()->isGlobalAdmin();
        $this->branch_id = auth()->user()->isGlobalAdmin()
            ? (BranchContext::id() ?? auth()->user()->branch_id)
            : auth()->user()->branch_id;
        $this->showPackageModal = true;
    }

    public function openEditModal(int $packageId): void
    {
        $package = Package::findOrFail($packageId);
        $this->authorize('update', $package);

        $this->editingId = $package->id;
        $this->branch_id = $package->branch_id;
        $this->name = $package->name;
        $this->price = (float) $package->price;
        $this->duration_value = $package->duration_value;
        $this->duration_unit = $package->duration_unit;
        $this->description = (string) ($package->description ?? '');
        $this->is_active = $package->is_active;
        $this->isEditing = true;
        $this->showPackageModal = true;
    }

    public function savePackage(): void
    {
        $this->validate($this->packageRules());

        $payload = [
            'name' => trim($this->name),
            'price' => $this->price,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit,
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditing) {
            $package = Package::findOrFail($this->editingId);
            $this->authorize('update', $package);
            $package->update($payload);
            session()->flash('success', 'Package updated successfully.');
        } else {
            $this->authorize('create', Package::class);
            $payload['branch_id'] = $this->branch_id;
            Package::create($payload);
            session()->flash('success', 'Package created successfully.');
        }

        $this->closePackageModal();
    }

    public function closePackageModal(): void
    {
        $this->showPackageModal = false;
        $this->resetPackageForm();
    }

    public function toggleActive(int $packageId): void
    {
        $package = Package::findOrFail($packageId);
        $this->authorize('update', $package);

        $package->update(['is_active' => ! $package->is_active]);

        session()->flash('success', $package->is_active ? 'Package activated.' : 'Package deactivated.');
    }

    public function openCreateItemModal(int $packageId): void
    {
        $package = Package::findOrFail($packageId);
        $this->authorize('update', $package);

        $this->resetItemForm();
        $this->itemPackageId = $package->id;
        $this->itemSortOrder = $package->items()->count();
        $this->showItemModal = true;
    }

    public function openEditItemModal(int $itemId): void
    {
        $item = PackageItem::with('package')->findOrFail($itemId);
        $this->authorize('update', $item->package);

        $this->editingItemId = $item->id;
        $this->itemPackageId = $item->package_id;
        $this->itemName = $item->name;
        $this->itemDescription = (string) ($item->description ?? '');
        $this->itemSortOrder = $item->sort_order;
        $this->existingItemImagePath = $item->image_path;
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->validate($this->itemRules());

        $package = Package::findOrFail($this->itemPackageId);
        $this->authorize('update', $package);

        $item = $this->editingItemId
            ? PackageItem::findOrFail($this->editingItemId)
            : new PackageItem(['package_id' => $package->id]);

        $data = [
            'package_id' => $package->id,
            'name' => trim($this->itemName),
            'description' => trim($this->itemDescription) !== '' ? trim($this->itemDescription) : null,
            'sort_order' => $this->itemSortOrder,
        ];

        if ($this->itemImageUpload) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }

            $data['image_path'] = $this->itemImageUpload->store('installment-package-items', 'public');
        }

        $item->fill($data);
        $item->save();

        $this->closeItemModal();
        session()->flash('success', 'Package item saved successfully.');
    }

    public function deleteItem(int $itemId): void
    {
        $item = PackageItem::with('package')->findOrFail($itemId);
        $this->authorize('update', $item->package);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->delete();
        session()->flash('success', 'Package item deleted.');
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }

    public function render()
    {
        $packages = Package::query()
            ->with('items')
            ->when($this->search !== '', function ($builder) {
                $term = '%' . $this->search . '%';
                $builder->where(function ($query) use ($term) {
                    $query->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($this->statusFilter === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.installments.packages.index', [
            'packages' => $packages,
            'branches' => Branch::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    protected function resetPackageForm(): void
    {
        $this->reset([
            'editingId',
            'name',
            'price',
            'description',
        ]);

        $this->duration_value = 1;
        $this->duration_unit = 'months';
        $this->is_active = true;
        $this->isEditing = false;
        $this->showBranchSelector = false;
        $this->resetValidation();
    }

    protected function resetItemForm(): void
    {
        $this->reset([
            'editingItemId',
            'itemPackageId',
            'itemName',
            'itemDescription',
            'itemSortOrder',
            'itemImageUpload',
            'existingItemImagePath',
        ]);

        $this->itemSortOrder = 0;
        $this->resetValidation();
    }

    protected function branchIdForForm(): ?int
    {
        return auth()->user()->isGlobalAdmin() ? $this->branch_id : auth()->user()->branch_id;
    }
}
