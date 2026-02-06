<?php

namespace App\Models;

use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Todo extends Model
{
    use HasFactory;

    // Remove BranchScoped trait - todos are personal and branch_id is optional

    protected $fillable = [
        'branch_id',
        'user_id',
        'category_id',
        'title',
        'priority',
        'note',
        'is_done',
        'done_at',
        'due_on',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'done_at' => 'datetime',
            'due_on' => 'date',
            'due_at' => 'datetime',
            'priority' => Priority::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Mark the todo as done.
     */
    public function markAsDone(): void
    {
        $this->update([
            'is_done' => true,
            'done_at' => now(),
        ]);
    }

    /**
     * Mark the todo as not done.
     */
    public function markAsNotDone(): void
    {
        $this->update([
            'is_done' => false,
            'done_at' => null,
        ]);
    }

    /**
     * Check if the todo is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->is_done) {
            return false;
        }

        if ($this->due_at) {
            return $this->due_at->isPast();
        }

        if ($this->due_on) {
            return $this->due_on->endOfDay()->isPast();
        }

        return false;
    }

    /**
     * Get the effective due datetime.
     */
    public function getEffectiveDueAt(): ?\Carbon\CarbonInterface
    {
        if ($this->due_at) {
            return $this->due_at;
        }

        if ($this->due_on) {
            return $this->due_on->endOfDay();
        }

        return null;
    }

    /**
     * Get remaining time as human readable string.
     */
    public function getRemainingTime(): ?string
    {
        $dueAt = $this->getEffectiveDueAt();

        if (! $dueAt || $this->is_done) {
            return null;
        }

        $now = now();

        if ($dueAt->isPast()) {
            return 'Overdue';
        }

        $diff = $now->diff($dueAt);

        if ($diff->days > 0) {
            return $diff->days.'d '.$diff->h.'h';
        }

        if ($diff->h > 0) {
            return $diff->h.'h '.$diff->i.'m';
        }

        if ($diff->i > 0) {
            return $diff->i.'m '.$diff->s.'s';
        }

        return $diff->s.'s';
    }

    /**
     * Get remaining seconds for countdown.
     */
    public function getRemainingSeconds(): ?int
    {
        $dueAt = $this->getEffectiveDueAt();

        if (! $dueAt || $this->is_done) {
            return null;
        }

        $remaining = $dueAt->diffInSeconds(now(), false);

        return $remaining < 0 ? abs($remaining) : 0;
    }

    /**
     * Get priority color classes.
     */
    public function getPriorityColors(): array
    {
        return match ($this->priority) {
            Priority::Low => [
                'bg' => 'bg-zinc-100 dark:bg-zinc-800',
                'text' => 'text-zinc-600 dark:text-zinc-400',
                'dot' => 'bg-zinc-400',
                'border' => 'border-zinc-300 dark:border-zinc-600',
            ],
            Priority::Normal => [
                'bg' => 'bg-blue-50 dark:bg-blue-900/30',
                'text' => 'text-blue-600 dark:text-blue-400',
                'dot' => 'bg-blue-500',
                'border' => 'border-blue-300 dark:border-blue-700',
            ],
            Priority::High => [
                'bg' => 'bg-amber-50 dark:bg-amber-900/30',
                'text' => 'text-amber-600 dark:text-amber-400',
                'dot' => 'bg-amber-500',
                'border' => 'border-amber-300 dark:border-amber-700',
            ],
            Priority::Urgent => [
                'bg' => 'bg-red-50 dark:bg-red-900/30',
                'text' => 'text-red-600 dark:text-red-400',
                'dot' => 'bg-red-500',
                'border' => 'border-red-300 dark:border-red-700',
            ],
        };
    }

    /**
     * Scope to order by priority (urgent first).
     */
    public function scopeOrderByPriority($query, string $direction = 'desc')
    {
        $order = $direction === 'desc'
            ? ['urgent', 'high', 'normal', 'low']
            : ['low', 'normal', 'high', 'urgent'];

        return $query->orderByRaw("FIELD(priority, '".implode("','", $order)."')");
    }

    /**
     * Scope to order by due time (soonest first).
     */
    public function scopeOrderByDue($query, string $direction = 'asc')
    {
        return $query->orderByRaw("COALESCE(due_at, due_on) $direction");
    }
}
