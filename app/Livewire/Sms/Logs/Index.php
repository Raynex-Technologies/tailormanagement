<?php

namespace App\Livewire\Sms\Logs;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public ?string $statusFilter = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $includeResolvedFailures = false;

    // Detail modal
    public bool $showDetailModal = false;

    public ?SmsLog $selectedLog = null;

    public bool $showRetryModal = false;

    public bool $showClearLogsModal = false;

    public ?string $retryDateFrom = null;

    public ?string $retryDateTo = null;

    protected string $paginationTheme = 'tailwind';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => null],
        'dateFrom' => ['except' => null],
        'dateTo' => ['except' => null],
        'includeResolvedFailures' => ['except' => false],
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

    public function updatingIncludeResolvedFailures(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
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
        $this->includeResolvedFailures = false;
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

    public function openRetryModal(): void
    {
        $this->authorize('sms.send');

        $this->retryDateFrom = $this->dateFrom ?: now()->toDateString();
        $this->retryDateTo = $this->dateTo ?: now()->toDateString();
        $this->showRetryModal = true;
    }

    public function openClearLogsModal(): void
    {
        $this->authorize('sms.logs.view');
        $this->authorize('sms.send');

        $this->showClearLogsModal = true;
    }

    public function clearLogs(): void
    {
        $this->authorize('sms.logs.view');
        $this->authorize('sms.send');

        SmsLog::query()->delete();

        $this->closeDetails();
        $this->showClearLogsModal = false;
        $this->showRetryModal = false;
        $this->clearFilters();

        session()->flash('success', __('All SMS logs in your current branch scope have been cleared.'));
    }

    public function retryFailedMessages(SmsService $smsService): void
    {
        $this->authorize('sms.send');

        $this->validate([
            'retryDateFrom' => ['required', 'date'],
            'retryDateTo' => ['required', 'date', 'after_or_equal:retryDateFrom'],
        ]);

        $from = Carbon::parse($this->retryDateFrom)->startOfDay();
        $to = Carbon::parse($this->retryDateTo)->endOfDay();

        $failedLogs = SmsLog::query()
            ->with('reference')
            ->unresolvedFailedRetries()
            ->whereBetween('created_at', [$from, $to])
            ->oldest()
            ->get();

        $retried = 0;
        $resolved = 0;

        foreach ($failedLogs as $log) {
            $retryLog = $smsService->retryFailedLog($log, auth()->user());
            $retried++;

            if ($retryLog->status === SmsStatus::Sent) {
                $resolved++;
            }
        }

        $this->showRetryModal = false;
        $this->resetPage();

        session()->flash(
            $retried > 0 ? 'success' : 'error',
            $retried > 0
                ? trans_choice(
                    'Retried :count failed message; :resolved was sent successfully and is now hidden from unresolved failures.|Retried :count failed messages; :resolved were sent successfully and are now hidden from unresolved failures.',
                    $retried,
                    ['count' => $retried, 'resolved' => $resolved]
                )
                : __('No failed messages were found in the selected date range.')
        );
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

        if (! $this->includeResolvedFailures) {
            $query->withoutResolvedFailedRetries();
        }

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
        $totalQuery = SmsLog::query();
        if (! $this->includeResolvedFailures) {
            $totalQuery->withoutResolvedFailedRetries();
        }

        $stats = [
            'total' => $totalQuery->count(),
            'sent' => SmsLog::where('status', SmsStatus::Sent)->count(),
            'failed' => SmsLog::query()->unresolvedFailedRetries()->count(),
            'queued' => SmsLog::where('status', SmsStatus::Queued)->count(),
        ];

        return view('livewire.sms.logs.index', [
            'logs' => $logs,
            'stats' => $stats,
            'hasLogsToClear' => SmsLog::query()->exists(),
            'smsStatuses' => $this->smsStatuses,
        ])->layout('layouts.app', ['title' => __('SMS Logs')]);
    }
}
