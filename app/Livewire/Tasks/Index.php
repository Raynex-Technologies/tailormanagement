<?php

namespace App\Livewire\Tasks;

use App\Enums\Priority;
use App\Models\TaskCategory;
use App\Models\Todo;
use App\Models\User;
use App\Support\BranchContext;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app.sidebar')]
#[Title('My Tasks')]
class Index extends Component
{
    use WithPagination;

    private const NON_STAFF_ROLES = [
        'superadmin',
        'admin',
        'branch_manager',
        'customer',
    ];

    // Modal state
    public bool $showModal = false;
    public bool $showCategoryModal = false;

    // Task form fields
    public string $title = '';
    public ?int $assigneeId = null;
    public ?int $categoryId = null;
    public string $priority = 'normal';
    public string $note = '';
    public ?string $dueDate = null;
    public ?string $dueTime = null;

    // Category form fields
    public string $categoryName = '';
    public string $categoryColor = 'blue';

    // Filters
    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'created'; // created, priority, time

    #[Url]
    public string $filterStatus = 'pending'; // all, pending, completed

    #[Url]
    public ?int $filterCategory = null;

    #[Url]
    public ?string $filterPriority = null;

    // Edit mode
    public ?int $editingTaskId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('todos.use'), 403);
        $this->assigneeId = null;
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:140',
            'assigneeId' => 'nullable|integer|exists:users,id',
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

    public function updatedAssigneeId($value): void
    {
        if (blank($value)) {
            $this->assigneeId = null;

            return;
        }

        if ($this->canAssignTasks && (int) $value !== (int) auth()->id()) {
            $this->categoryId = null;
        }
    }

    protected function resetForm(): void
    {
        $this->title = '';
        $this->assigneeId = null;
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
        $assigneeId = $this->resolveAssigneeId($user);
        if (! $assigneeId) {
            return;
        }

        if ($assigneeId !== (int) $user->id) {
            $categoryId = null;
        } else {
            $categoryId = $this->categoryId ? (int) $this->categoryId : null;
            if ($categoryId && ! TaskCategory::where('user_id', $user->id)->whereKey($categoryId)->exists()) {
                $this->addError('categoryId', __('Selected category is invalid.'));

                return;
            }
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

        $branchId = $this->resolveBranchId($user);

        $data = [
            'branch_id' => $branchId,
            'user_id' => $assigneeId,
            'assigned_by' => $assigneeId !== (int) $user->id ? $user->id : null,
            'category_id' => $categoryId,
            'title' => $this->title,
            'priority' => $this->priority,
            'note' => $this->note ?: null,
            'due_at' => $dueAt,
        ];

        if ($this->editingTaskId) {
            $todo = Todo::where('user_id', $user->id)->find($this->editingTaskId);
            if ($todo) {
                if ($assigneeId === (int) $todo->user_id && $todo->assigned_by && (int) $todo->assigned_by !== (int) $user->id) {
                    $data['assigned_by'] = $todo->assigned_by;
                }

                $todo->update($data);
            }
        } else {
            $data['is_done'] = false;
            Todo::create($data);
        }

        $this->closeModal();
        $this->dispatch('refresh-todos');
    }

    public function editTask(int $todoId): void
    {
        $todo = Todo::where('user_id', auth()->id())->find($todoId);

        if (! $todo) {
            return;
        }

        $this->editingTaskId = $todoId;
        $this->title = $todo->title;
        $this->assigneeId = $todo->user_id;
        $this->categoryId = TaskCategory::where('user_id', auth()->id())->whereKey($todo->category_id)->exists()
            ? $todo->category_id
            : null;
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

    public function clearCompleted(): void
    {
        Todo::where('user_id', auth()->id())
            ->where('is_done', true)
            ->delete();
    }

    #[Computed]
    public function categories()
    {
        return TaskCategory::where('user_id', auth()->id())
            ->withCount('todos')
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

    #[Computed]
    public function canAssignTasks(): bool
    {
        return auth()->user()?->can('todos.assign') ?? false;
    }

    #[Computed]
    public function assignees()
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        if (! $this->canAssignTasks) {
            return collect([$user]);
        }

        $branchId = $this->resolveBranchId($user);
        if (! $branchId) {
            return collect([$user]);
        }

        return User::query()
            ->where('branch_id', $branchId)
            ->where(function ($query) use ($user) {
                $query->whereKey($user->id)
                    ->orWhere(function ($staffQuery) {
                        $staffQuery
                            ->whereHas('roles')
                            ->whereDoesntHave('roles', fn ($roleQuery) => $roleQuery->whereIn('name', self::NON_STAFF_ROLES));
                    });
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$user->id])
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function resolveAssigneeId(User $user): ?int
    {
        $assigneeId = (int) ($this->assigneeId ?: $user->id);
        if ($assigneeId <= 0) {
            $assigneeId = (int) $user->id;
        }

        if ($assigneeId === (int) $user->id) {
            return $assigneeId;
        }

        if (! $this->canAssignTasks) {
            $this->addError('assigneeId', __('You do not have permission to assign tasks.'));

            return null;
        }

        $allowedAssigneeIds = $this->assignees
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! in_array($assigneeId, $allowedAssigneeIds, true)) {
            $this->addError('assigneeId', __('Selected staff member is not assignable.'));

            return null;
        }

        return $assigneeId;
    }

    protected function resolveBranchId(User $user): ?int
    {
        if ($user->isGlobalAdmin()) {
            return BranchContext::id() ?? $user->branch_id;
        }

        return $user->branch_id;
    }

    #[Computed]
    public function stats()
    {
        $base = Todo::where('user_id', auth()->id());

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('is_done', false)->count(),
            'completed' => (clone $base)->where('is_done', true)->count(),
            'overdue' => (clone $base)->where('is_done', false)
                ->where(function ($q) {
                    $q->where('due_at', '<', now())
                        ->orWhere(function ($q2) {
                            $q2->whereNull('due_at')
                                ->where('due_on', '<', now()->startOfDay());
                        });
                })->count(),
        ];
    }

    #[Computed]
    public function assignedTaskStats(): array
    {
        if (! $this->canAssignTasks) {
            return [
                'total' => 0,
                'pending' => 0,
                'completed' => 0,
            ];
        }

        $base = Todo::query()
            ->where('assigned_by', auth()->id())
            ->where('user_id', '!=', auth()->id());

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('is_done', false)->count(),
            'completed' => (clone $base)->where('is_done', true)->count(),
        ];
    }

    #[Computed]
    public function trackedAssignments()
    {
        if (! $this->canAssignTasks) {
            return collect();
        }

        return Todo::query()
            ->where('assigned_by', auth()->id())
            ->where('user_id', '!=', auth()->id())
            ->with('user:id,name')
            ->orderBy('updated_at', 'desc')
            ->limit(12)
            ->get();
    }

    public function render()
    {
        $query = Todo::where('user_id', auth()->id())
            ->with(['category', 'assignedBy:id,name']);

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%'.$this->search.'%')
                    ->orWhere('note', 'like', '%'.$this->search.'%');
            });
        }

        // Filter by status
        if ($this->filterStatus === 'pending') {
            $query->where('is_done', false);
        } elseif ($this->filterStatus === 'completed') {
            $query->where('is_done', true);
        }

        // Filter by category
        if ($this->filterCategory) {
            $query->where('category_id', $this->filterCategory);
        }

        // Filter by priority
        if ($this->filterPriority) {
            $query->where('priority', $this->filterPriority);
        }

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

        $todos = $query->paginate(20);

        return view('livewire.tasks.index', [
            'todos' => $todos,
        ]);
    }
}
