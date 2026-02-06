<?php

namespace App\Livewire\Expenses;

use App\Models\CapitalTransaction;
use App\Models\Expense;
use App\Services\Expenses\ExpenseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use AuthorizesRequests;

    public Expense $expense;
    public ?CapitalTransaction $capitalTransaction = null;

    public function mount(Expense $expense): void
    {
        $this->authorize('view', $expense);
        $this->expense = $expense->load(['category', 'capitalAllocation', 'creator']);

        // Get linked capital transaction if any
        if ($expense->capital_allocation_id) {
            $this->capitalTransaction = app(ExpenseService::class)->getLinkedCapitalTransaction($expense);
        }
    }

    public function getCanEditProperty(): bool
    {
        return auth()->user()->can('update', $this->expense);
    }

    public function render()
    {
        return view('livewire.expenses.show')->title("Expense: {$this->expense->expense_date->format('M d, Y')}");
    }
}
