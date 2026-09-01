<?php

namespace App\Livewire\GarmentOptions;

use App\Models\GarmentCategory;
use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class OptionsIndex extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    public ?int $categoryId = null;

    public ?int $groupFilter = null;

    public string $status = 'active';

    public ?int $optionId = null;

    public ?int $groupId = null;

    public string $label = '';

    public string $description = '';

    public string $priceAdjustment = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    public bool $panelOpen = false;

    public bool $readOnly = false;

    public function mount(): void
    {
        $this->authorize('viewAny', GarmentCategory::class);
    }

    public function updatedCategoryId(): void
    {
        $this->groupFilter = null;
    }

    public function create(): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->resetForm();
        $this->groupId = $this->groupFilter
            ?? GarmentOptionGroup::query()
                ->when($this->categoryId, fn ($query) => $query->where('garment_category_id', $this->categoryId))
                ->orderBy('sort_order')
                ->value('id');
        $this->panelOpen = true;
    }

    public function view(int $id): void
    {
        $this->loadOption($id, true);
    }

    public function edit(int $id): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->loadOption($id, false);
    }

    public function save(): void
    {
        $this->authorize('manage', GarmentCategory::class);
        abort_if($this->readOnly, 403);

        $validated = $this->validate([
            'groupId' => ['required', 'exists:garment_option_groups,id'],
            'label' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priceAdjustment' => ['nullable', 'numeric', 'min:0'],
            'sortOrder' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $option = $this->optionId
            ? GarmentOption::query()->findOrFail($this->optionId)
            : new GarmentOption;

        $option->fill([
            'garment_option_group_id' => $validated['groupId'],
            'label' => trim($validated['label']),
            'value' => Str::slug($validated['label'], '_'),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'price_adjustment' => filled($validated['priceAdjustment']) ? $validated['priceAdjustment'] : null,
            'sort_order' => $validated['sortOrder'],
            'is_active' => $validated['isActive'],
        ])->save();

        session()->flash('success', $this->optionId ? __('Garment option updated.') : __('Garment option created.'));
        $this->closePanel();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $option = GarmentOption::query()->findOrFail($id);
        $option->update(['is_active' => ! $option->is_active]);
        session()->flash('success', $option->is_active ? __('Garment option activated.') : __('Garment option archived.'));
    }

    public function closePanel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $groups = GarmentOptionGroup::query()
            ->with('garmentCategory:id,name')
            ->when($this->categoryId, fn ($query) => $query->where('garment_category_id', $this->categoryId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.garment-options.options-index', [
            'categories' => GarmentCategory::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'groups' => $groups,
            'options' => GarmentOption::query()
                ->with('group.garmentCategory:id,name')
                ->when($this->categoryId, fn ($query) => $query->whereHas('group', fn ($group) => $group->where('garment_category_id', $this->categoryId)))
                ->when($this->groupFilter, fn ($query) => $query->where('garment_option_group_id', $this->groupFilter))
                ->when($this->search !== '', fn ($query) => $query->where(function ($search) {
                    $search->where('label', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%');
                }))
                ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
                ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('garment_option_group_id')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
        ])->title(__('Garment Customizations'));
    }

    private function loadOption(int $id, bool $readOnly): void
    {
        $option = GarmentOption::query()->findOrFail($id);
        $this->optionId = $option->id;
        $this->groupId = $option->garment_option_group_id;
        $this->label = $option->label;
        $this->description = (string) $option->description;
        $this->priceAdjustment = $option->price_adjustment !== null ? (string) $option->price_adjustment : '';
        $this->sortOrder = (int) $option->sort_order;
        $this->isActive = (bool) $option->is_active;
        $this->readOnly = $readOnly;
        $this->panelOpen = true;
    }

    private function resetForm(): void
    {
        $this->reset(['optionId', 'groupId', 'label', 'description', 'priceAdjustment', 'sortOrder', 'panelOpen', 'readOnly']);
        $this->isActive = true;
    }
}
