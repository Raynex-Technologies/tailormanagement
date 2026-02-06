<?php

namespace App\Livewire\Reports;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
#[Title('Reports')]
class Index extends Component
{
    public function mount(): void
    {
        if (! auth()->user()->can('reports.view')) {
            abort(403);
        }
    }

    public function render()
    {
        $reports = [
            [
                'name' => 'Sales Report',
                'description' => 'Payment transactions, totals, and methods analysis.',
                'route' => 'reports.sales',
                'icon' => 'banknotes',
                'color' => 'emerald',
            ],
            [
                'name' => 'Orders Report',
                'description' => 'Order status, turnaround time, and value analysis.',
                'route' => 'reports.orders',
                'icon' => 'document-text',
                'color' => 'blue',
            ],
            [
                'name' => 'Expenses Report',
                'description' => 'Expense tracking by category and capital allocation.',
                'route' => 'reports.expenses',
                'icon' => 'receipt-percent',
                'color' => 'red',
            ],
            [
                'name' => 'Inventory Report',
                'description' => 'Stock levels, movements, and low stock alerts.',
                'route' => 'reports.inventory',
                'icon' => 'archive-box',
                'color' => 'amber',
            ],
            [
                'name' => 'Capital Audit Report',
                'description' => 'Capital allocations, spending, and transactions.',
                'route' => 'reports.capital',
                'icon' => 'building-library',
                'color' => 'purple',
            ],
        ];

        return view('livewire.reports.index', compact('reports'));
    }
}
