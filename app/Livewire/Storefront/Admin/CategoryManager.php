<?php

namespace App\Livewire\Storefront\Admin;

use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Services\Media\ImageUploadService;
use App\Support\BranchContext;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('Storefront Product Categories')]
class CategoryManager extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Url]
    public string $search = '';

    public int $perPage = 15;

    public bool $showFormModal = false;

    public bool $showDeleteModal = false;

    public bool $isEditing = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public bool $slugManuallyEdited = false;

    public string $description = '';

    public bool $storefrontVisible = true;

    public bool $storefrontFeatured = false;

    public $imageUpload = null;

    public ?string $existingImageUrl = null;

    public ?int $branchId = null;

    public bool $showBranchSelector = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('storefront.catalog.manage'), 403);

        $user = auth()->user();
        $this->showBranchSelector = $user->isGlobalAdmin();
        $this->branchId = $this->showBranchSelector ? BranchContext::id() : $user->branch_id;
    }

    protected function rules(): array
    {
        $effectiveBranchId = $this->branchId ?? BranchContext::id() ?? auth()->user()->branch_id;

        $nameRule = Rule::unique('inventory_categories', 'name')
            ->where(fn ($query) => $query->where('branch_id', $effectiveBranchId));

        $slugRule = Rule::unique('inventory_categories', 'slug')
            ->where(fn ($query) => $query->where('branch_id', $effectiveBranchId));

        if ($this->isEditing && $this->editingId) {
            $nameRule = $nameRule->ignore($this->editingId);
            $slugRule = $slugRule->ignore($this->editingId);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255', $nameRule],
            'slug' => ['nullable', 'string', 'max:255', $slugRule],
            'description' => ['nullable', 'string', 'max:2000'],
            'storefrontVisible' => ['boolean'],
            'storefrontFeatured' => ['boolean'],
            'imageUpload' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ];

        if ($this->showBranchSelector && ! $this->isEditing) {
            $rules['branchId'] = ['required', 'integer', Rule::exists('branches', 'id')];
        }

        return $rules;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(): void
    {
        if (! $this->slugManuallyEdited) {
            $this->slug = Str::slug($this->name);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugManuallyEdited = trim($value) !== '';
    }

    public function openCreateModal(): void
    {
        $this->authorize('storefront.catalog.manage');

        $this->resetFormState();
        $this->isEditing = false;
        $this->showFormModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('storefront.catalog.manage');

        $category = InventoryCategory::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->isEditing = true;
        $this->showFormModal = true;

        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->slugManuallyEdited = true;
        $this->description = (string) ($category->description ?? '');
        $this->storefrontVisible = (bool) $category->storefront_is_visible;
        $this->storefrontFeatured = (bool) $category->storefront_featured;
        $this->existingImageUrl = $category->image_url;
        $this->imageUpload = null;
        $this->branchId = $category->branch_id;
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->resetFormState();
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('storefront.catalog.manage');

        $this->name = trim(strip_tags($this->name));
        $this->description = trim(strip_tags($this->description));
        $this->slug = trim(Str::slug($this->slug));

        $this->validate();

        $effectiveBranchId = $this->showBranchSelector
            ? (int) ($this->branchId ?: BranchContext::id() ?: 0)
            : (int) auth()->user()->branch_id;

        if ($effectiveBranchId <= 0) {
            $this->addError('branchId', 'Please select a branch.');

            return;
        }

        $payload = [
            'name' => $this->name,
            'slug' => $this->slug === '' ? null : $this->slug,
            'description' => $this->description === '' ? null : $this->description,
            'storefront_is_visible' => (bool) $this->storefrontVisible,
            'storefront_featured' => (bool) $this->storefrontFeatured,
        ];

        if ($this->isEditing && $this->editingId) {
            $category = InventoryCategory::query()->findOrFail($this->editingId);

            if ($this->imageUpload) {
                $stored = app(ImageUploadService::class)->replacePublic(
                    upload: $this->imageUpload,
                    existingPath: $category->storefront_image_path,
                    directory: 'categories'
                );

                $payload['storefront_image_path'] = $stored->path;
            }

            $category->update($payload);
            session()->flash('success', 'Storefront category updated.');
        } else {
            $payload['branch_id'] = $effectiveBranchId;
            if ($this->imageUpload) {
                $payload['storefront_image_path'] = app(ImageUploadService::class)
                    ->storePublic($this->imageUpload, 'categories')
                    ->path;
            }
            InventoryCategory::query()->create($payload);
            session()->flash('success', 'Storefront category created.');
        }

        $this->closeFormModal();
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('storefront.catalog.manage');

        $category = InventoryCategory::query()->withCount('items')->findOrFail($id);

        if ($category->items_count > 0) {
            session()->flash('error', "Cannot delete '{$category->name}'. It has {$category->items_count} item(s) assigned.");

            return;
        }

        $this->deletingId = $category->id;
        $this->deletingName = $category->name;
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function delete(): void
    {
        $this->authorize('storefront.catalog.manage');

        $category = InventoryCategory::query()->withCount('items')->findOrFail((int) $this->deletingId);
        if ($category->items_count > 0) {
            session()->flash('error', "Cannot delete '{$category->name}'. It has {$category->items_count} item(s) assigned.");
            $this->closeDeleteModal();

            return;
        }

        $category->delete();
        session()->flash('success', 'Storefront category deleted.');
        $this->closeDeleteModal();
    }

    protected function resetFormState(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->slugManuallyEdited = false;
        $this->description = '';
        $this->storefrontVisible = true;
        $this->storefrontFeatured = false;
        $this->imageUpload = null;
        $this->existingImageUrl = null;
        if ($this->showBranchSelector) {
            $this->branchId = BranchContext::id();
        }
    }

    public function render()
    {
        $categories = InventoryCategory::query()
            ->withCount('items')
            ->with('branch')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.storefront.admin.category-manager', [
            'categories' => $categories,
            'branches' => $this->showBranchSelector
                ? Branch::query()->active()->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }
}
