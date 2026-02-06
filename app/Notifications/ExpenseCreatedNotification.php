<?php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpenseCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Expense $expense
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $creatorName = $this->expense->creator?->name ?? 'Unknown';
        $amount = money_tzs($this->expense->amount);
        $vendor = $this->expense->vendor ?? 'N/A';
        $category = $this->expense->category?->name ?? 'Uncategorized';

        return [
            'title' => 'New Expense Recorded',
            'body' => "Expense of {$amount} recorded by {$creatorName}. Vendor: {$vendor}, Category: {$category}.",
            'link' => route('expenses.show', $this->expense),
            'icon' => 'receipt-percent',
            'color' => 'amber',
            'expense_id' => $this->expense->id,
            'amount' => $this->expense->amount,
        ];
    }
}
