<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    /**
     * Available colors for categories.
     */
    public static array $colors = [
        'zinc' => 'Gray',
        'red' => 'Red',
        'orange' => 'Orange',
        'amber' => 'Amber',
        'yellow' => 'Yellow',
        'lime' => 'Lime',
        'green' => 'Green',
        'emerald' => 'Emerald',
        'teal' => 'Teal',
        'cyan' => 'Cyan',
        'sky' => 'Sky',
        'blue' => 'Blue',
        'indigo' => 'Indigo',
        'violet' => 'Violet',
        'purple' => 'Purple',
        'fuchsia' => 'Fuchsia',
        'pink' => 'Pink',
        'rose' => 'Rose',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class, 'category_id');
    }

    /**
     * Get the CSS classes for this category's color.
     */
    public function getColorClasses(): array
    {
        return match ($this->color) {
            'red' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400', 'dot' => 'bg-red-500'],
            'orange' => ['bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600 dark:text-orange-400', 'dot' => 'bg-orange-500'],
            'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400', 'dot' => 'bg-amber-500'],
            'yellow' => ['bg' => 'bg-yellow-100 dark:bg-yellow-900/30', 'text' => 'text-yellow-600 dark:text-yellow-400', 'dot' => 'bg-yellow-500'],
            'lime' => ['bg' => 'bg-lime-100 dark:bg-lime-900/30', 'text' => 'text-lime-600 dark:text-lime-400', 'dot' => 'bg-lime-500'],
            'green' => ['bg' => 'bg-green-100 dark:bg-green-900/30', 'text' => 'text-green-600 dark:text-green-400', 'dot' => 'bg-green-500'],
            'emerald' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400', 'dot' => 'bg-emerald-500'],
            'teal' => ['bg' => 'bg-teal-100 dark:bg-teal-900/30', 'text' => 'text-teal-600 dark:text-teal-400', 'dot' => 'bg-teal-500'],
            'cyan' => ['bg' => 'bg-cyan-100 dark:bg-cyan-900/30', 'text' => 'text-cyan-600 dark:text-cyan-400', 'dot' => 'bg-cyan-500'],
            'sky' => ['bg' => 'bg-sky-100 dark:bg-sky-900/30', 'text' => 'text-sky-600 dark:text-sky-400', 'dot' => 'bg-sky-500'],
            'blue' => ['bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-600 dark:text-blue-400', 'dot' => 'bg-blue-500'],
            'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/30', 'text' => 'text-indigo-600 dark:text-indigo-400', 'dot' => 'bg-indigo-500'],
            'violet' => ['bg' => 'bg-violet-100 dark:bg-violet-900/30', 'text' => 'text-violet-600 dark:text-violet-400', 'dot' => 'bg-violet-500'],
            'purple' => ['bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-600 dark:text-purple-400', 'dot' => 'bg-purple-500'],
            'fuchsia' => ['bg' => 'bg-fuchsia-100 dark:bg-fuchsia-900/30', 'text' => 'text-fuchsia-600 dark:text-fuchsia-400', 'dot' => 'bg-fuchsia-500'],
            'pink' => ['bg' => 'bg-pink-100 dark:bg-pink-900/30', 'text' => 'text-pink-600 dark:text-pink-400', 'dot' => 'bg-pink-500'],
            'rose' => ['bg' => 'bg-rose-100 dark:bg-rose-900/30', 'text' => 'text-rose-600 dark:text-rose-400', 'dot' => 'bg-rose-500'],
            default => ['bg' => 'bg-zinc-100 dark:bg-zinc-800', 'text' => 'text-zinc-600 dark:text-zinc-400', 'dot' => 'bg-zinc-500'],
        };
    }
}
