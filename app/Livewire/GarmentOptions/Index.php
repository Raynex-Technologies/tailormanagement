<?php

namespace App\Livewire\GarmentOptions;

use App\Models\GarmentCategory;
use App\Models\GarmentOptionGroup;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Index extends Component
{
    use AuthorizesRequests;

    public ?int $categoryId = null;
    public ?int $groupId = null;
    public string $groupName = '';
    public string $inputType = 'select';
    public bool $isRequired = false;
    public bool $isActive = true;
    public int $sortOrder = 0;

    public string $optionLabel = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GarmentCategory::class);
        $this->categoryId = GarmentCategory::query()->orderBy('sort_order')->value('id');
    }

    public function saveGroup(): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->validate([
            'categoryId' => ['required', 'exists:garment_categories,id'],
            'groupName' => ['required', 'string', 'max:191'],
            'inputType' => ['required', Rule::in(['select', 'multi_select', 'radio', 'checkbox', 'text', 'textarea', 'color', 'number'])],
            'sortOrder' => ['required', 'integer', 'min:0'],
        ]);

        GarmentOptionGroup::query()->updateOrCreate(
            ['id' => $this->groupId],
            [
                'garment_category_id' => $this->categoryId,
                'name' => $this->groupName,
                'slug' => Str::slug($this->groupName),
                'input_type' => $this->inputType,
                'is_required' => $this->isRequired,
                'is_active' => $this->isActive,
                'sort_order' => $this->sortOrder,
            ]
        );

        session()->flash('success', 'Garment option group saved.');
        $this->resetGroup();
    }

    public function editGroup(int $id): void
    {
        $group = GarmentOptionGroup::query()->findOrFail($id);
        $this->authorize('manage', GarmentCategory::class);

        $this->groupId = $group->id;
        $this->categoryId = $group->garment_category_id;
        $this->groupName = $group->name;
        $this->inputType = $group->input_type;
        $this->isRequired = $group->is_required;
        $this->isActive = $group->is_active;
        $this->sortOrder = $group->sort_order;
    }

    public function addOption(int $groupId): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $this->validate(['optionLabel' => ['required', 'string', 'max:191']]);

        $group = GarmentOptionGroup::query()->findOrFail($groupId);
        $group->options()->updateOrCreate(
            ['value' => Str::slug($this->optionLabel, '_')],
            [
                'label' => $this->optionLabel,
                'is_active' => true,
                'sort_order' => $group->options()->max('sort_order') + 1,
            ]
        );

        $this->optionLabel = '';
        session()->flash('success', 'Option added.');
    }

    public function toggleOption(int $optionId): void
    {
        $this->authorize('manage', GarmentCategory::class);
        $option = \App\Models\GarmentOption::query()->findOrFail($optionId);
        $option->forceFill(['is_active' => ! $option->is_active])->save();
    }

    public function resetGroup(): void
    {
        $this->reset(['groupId', 'groupName', 'isRequired']);
        $this->inputType = 'select';
        $this->isActive = true;
        $this->sortOrder = 0;
    }

    public function render()
    {
        return view('livewire.garment-options.index', [
            'categories' => GarmentCategory::query()->withCount('optionGroups')->orderBy('sort_order')->get(),
            'groups' => GarmentOptionGroup::query()
                ->with(['options' => fn ($query) => $query->orderBy('sort_order')])
                ->when($this->categoryId, fn ($query) => $query->where('garment_category_id', $this->categoryId))
                ->orderBy('sort_order')
                ->get(),
        ])->title(__('Garment Options'));
    }
}
