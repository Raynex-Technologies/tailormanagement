<?php

namespace App\Livewire\GarmentOptions;

use App\Models\GarmentCategory;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
class CategoriesIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public string $search = '';

    public string $status = 'active';

    public ?int $categoryId = null;

    public string $name = '';

    public string $description = '';

    public string $genderScope = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public mixed $imageUpload = null;

    public ?string $existingImagePath = null;

    public bool $removeImage = false;

    public bool $panelOpen = false;

    public bool $readOnly = false;

    public function mount(): void
    {
        $this->authorize('viewAny', GarmentCategory::class);
    }

    public function create(): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->resetForm();
        $this->panelOpen = true;
    }

    public function view(int $id): void
    {
        $this->loadCategory($id, true);
    }

    public function edit(int $id): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->loadCategory($id, false);
    }

    public function save(): void
    {
        $this->authorize('manage', GarmentCategory::class);
        abort_if($this->readOnly, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'genderScope' => ['nullable', 'in:male,female,unisex,children'],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
            'imageUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
            'removeImage' => ['boolean'],
        ]);

        $category = $this->categoryId
            ? GarmentCategory::query()->findOrFail($this->categoryId)
            : new GarmentCategory;
        $imagePath = $category->image_path;
        $images = app(ImageUploadService::class);

        if ($this->imageUpload) {
            $imagePath = $images->replacePublic($this->imageUpload, $imagePath, 'order-catalog/garment-categories')->path;
        } elseif ($this->removeImage && $imagePath) {
            $images->deletePublic($imagePath);
            $imagePath = null;
        }

        $category->fill([
            'name' => trim($validated['name']),
            'slug' => Str::slug($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'gender_scope' => filled($validated['genderScope']) ? $validated['genderScope'] : null,
            'image_path' => $imagePath,
            'image_alt' => trim($validated['name']),
            'sort_order' => $validated['sortOrder'],
            'is_active' => $validated['isActive'],
        ])->save();

        session()->flash('success', $this->categoryId ? __('Garment category updated.') : __('Garment category created.'));
        $this->closePanel();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $category = GarmentCategory::query()->findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);
        session()->flash('success', $category->is_active ? __('Garment category activated.') : __('Garment category archived.'));
    }

    public function closePanel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.garment-options.categories-index', [
            'categories' => GarmentCategory::query()
                ->withCount(['optionGroups', 'fabrics'])
                ->when($this->search !== '', fn ($query) => $query->where(function ($search) {
                    $search->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                }))
                ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ])->title(__('Garment Categories'));
    }

    private function loadCategory(int $id, bool $readOnly): void
    {
        $category = GarmentCategory::query()->findOrFail($id);
        $this->categoryId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;
        $this->genderScope = (string) $category->gender_scope;
        $this->sortOrder = (int) $category->sort_order;
        $this->isActive = (bool) $category->is_active;
        $this->existingImagePath = $category->image_path;
        $this->readOnly = $readOnly;
        $this->panelOpen = true;
    }

    private function resetForm(): void
    {
        $this->reset(['categoryId', 'name', 'description', 'genderScope', 'sortOrder', 'imageUpload', 'existingImagePath', 'removeImage', 'panelOpen', 'readOnly']);
        $this->isActive = true;
    }
}
