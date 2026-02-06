<?php

namespace App\Livewire\Dashboard;

use App\Enums\Priority;
use App\Models\TaskCategory;
use App\Models\Todo;
use App\Support\BranchContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class TodoCard extends Component
{
    // Modal state
    public bool $showModal = false;
    public bool $showCategoryModal = false;

    // Task form fields
    public string $title = '';
    public ?int $categoryId = null;
    public string $priority = 'normal';
    public string $note = '';
    public ?string $dueDate = null;
    public ?string $dueTime = null;

    // Category form fields
    public string $categoryName = '';
    public string $categoryColor = 'blue';

    // Sorting
    public string $sortBy = 'created'; // created, priority, time

    // Edit mode
    public ?int $editingTaskId = null;

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:140',
            'categoryId' => 'nullable|exists:task_categories,id',
            'priority' => 'required|in:low,normal,high,urgent',
            'note' => 'nullable|string|max:500',
            'dueDate' => 'nullable|date',
            'dueTime' => 'nullable|date_format:H:i',
        ];
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function openCategoryModal(): void
    {
        $this->categoryName = '';
        $this->categoryColor = 'blue';
        $this->showCategoryModal = true;
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
    }

    protected function resetForm(): void
    {
        $this->title = '';
        $this->categoryId = null;
        $this->priority = 'normal';
        $this->note = '';
        $this->dueDate = null;
        $this->dueTime = null;
        $this->editingTaskId = null;
    }

    public function saveTask(): void
    {
        $this->validate();

        $user = auth()->user();

        // Determine branch_id - make it optional for global admins
        $branchId = null;
        try {
            $branchId = BranchContext::id();
        } catch (\Throwable $e) {
            // If no branch context, use user's branch (nullable is fine)
            $branchId = $user->branch_id;
        }

        // Build due_at from date and time
        $dueAt = null;
        if ($this->dueDate) {
            $dueAt = $this->dueDate;
            if ($this->dueTime) {
                $dueAt .= ' '.$this->dueTime.':00';
            } else {
                $dueAt .= ' 23:59:59';
            }
        }

        $data = [
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'priority' => $this->priority,
            'note' => $this->note ?: null,
            'due_at' => $dueAt,
            'is_done' => false,
        ];

        if ($this->editingTaskId) {
            $todo = Todo::where('user_id', $user->id)->find($this->editingTaskId);
            if ($todo) {
                $todo->update($data);
            }
        } else {
            Todo::create($data);
        }

        $this->closeModal();
    }

    public function editTask(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if (! $todo) {
            return;
        }

        $this->editingTaskId = $todoId;
        $this->title = $todo->title;
        $this->categoryId = $todo->category_id;
        $this->priority = $todo->priority->value;
        $this->note = $todo->note ?? '';
        $this->dueDate = $todo->due_at?->format('Y-m-d');
        $this->dueTime = $todo->due_at?->format('H:i');
        $this->showModal = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'categoryName' => 'required|string|max:50',
            'categoryColor' => 'required|string|max:20',
        ]);

        TaskCategory::create([
            'user_id' => auth()->id(),
            'name' => $this->categoryName,
            'color' => $this->categoryColor,
        ]);

        $this->closeCategoryModal();
    }

    public function deleteCategory(int $categoryId): void
    {
        TaskCategory::where('user_id', auth()->id())
            ->where('id', $categoryId)
            ->delete();
    }

    public function toggleDone(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if (! $todo) {
            return;
        }

        if ($todo->is_done) {
            $todo->markAsNotDone();
        } else {
            $todo->markAsDone();
        }
    }

    public function deleteTask(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if ($todo) {
            $todo->delete();
        }
    }

    public function setSortBy(string $sort): void
    {
        $this->sortBy = $sort;
    }

    #[Computed]
    public function categories()
    {
        return TaskCategory::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function priorities()
    {
        return collect(Priority::cases())->map(fn ($p) => [
            'value' => $p->value,
            'label' => $p->label(),
            'color' => $p->color(),
        ]);
    }

    #[Computed]
    public function categoryColors()
    {
        return TaskCategory::$colors;
    }

    #[On('refresh-todos')]
    public function render()
    {
        $query = Todo::where('user_id', auth()->id())
            ->with('category');

        // Apply sorting
        switch ($this->sortBy) {
            case 'priority':
                $query->orderBy('is_done', 'asc')
                    ->orderByPriority('desc')
                    ->orderBy('created_at', 'desc');
                break;
            case 'time':
                $query->orderBy('is_done', 'asc')
                    ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('due_at', 'asc')
                    ->orderBy('created_at', 'desc');
                break;
            default: // created
                $query->orderBy('is_done', 'asc')
                    ->orderBy('created_at', 'desc');
        }

        $todos = $query->limit(10)->get();

        return view('livewire.dashboard.todo-card', [
            'todos' => $todos,
        ]);
    }
}
