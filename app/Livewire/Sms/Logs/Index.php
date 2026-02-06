<?php

namespace App\Livewire\Sms\Logs;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public ?string $statusFilter = null;
    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    // Detail modal
    public bool $showDetailModal = false;
    public ?SmsLog $selectedLog = null;

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => null],
        'dateFrom' => ['except' => null],
        'dateTo' => ['except' => null],
    ];

    public function mount(): void
    {
        $this->authorize('sms.logs.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function setStatusFilter(?string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->resetPage();
    }

    public function showDetails(int $logId): void
    {
        $this->selectedLog = SmsLog::with('reference', 'creator')->find($logId);
        $this->showDetailModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailModal = false;
        $this->selectedLog = null;
    }

    public function getSmsStatusesProperty(): array
    {
        return SmsStatus::cases();
    }

    public function render()
    {
        $query = SmsLog::query()
            ->with(['reference', 'creator'])
            ->latest();

        // Search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('to', 'like', "%{$this->search}%")
                    ->orWhere('message', 'like', "%{$this->search}%")
                    ->orWhere('provider_message_id', 'like', "%{$this->search}%")
                    ->orWhereHasMorph('reference', [\App\Models\Order::class], function ($orderQuery) {
                        $orderQuery->where('order_no', 'like', "%{$this->search}%");
                    });
            });
        }

        // Status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Date range filter
        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $logs = $query->paginate(20);

        // Aggregate stats
        $stats = [
            'total' => SmsLog::count(),
            'sent' => SmsLog::where('status', SmsStatus::Sent)->count(),
            'failed' => SmsLog::where('status', SmsStatus::Failed)->count(),
            'queued' => SmsLog::where('status', SmsStatus::Queued)->count(),
        ];

        return view('livewire.sms.logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'smsStatuses' => $this->smsStatuses,
        ])->layout('layouts.app', ['title' => __('SMS Logs')]);
    }
}
