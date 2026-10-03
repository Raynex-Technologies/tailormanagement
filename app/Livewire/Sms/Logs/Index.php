<?php

namespace App\Livewire\Sms\Logs;

use App\Enums\SmsStatus;
use App\Models\SmsLog;
use App\Models\SmsRetry;
use App\Services\Sms\SmsRetryQueue;
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
        SmsRetry::query()->whereIn('status', ['pending', 'paused'])->update(['status' => 'cancelled']);

        $this->closeDetails();
        $this->showClearLogsModal = false;
        $this->showRetryModal = false;
        $this->clearFilters();

        session()->flash('success', __('All SMS logs in your current branch scope have been cleared.'));
    }

    public function retryFailedMessages(SmsRetryQueue $queue): void
    {
        $this->authorize('sms.logs.view');
        $this->authorize('sms.send');

        $this->validate([
            'retryDateFrom' => ['required', 'date'],
            'retryDateTo' => ['required', 'date', 'after_or_equal:retryDateFrom'],
        ]);

        $from = Carbon::parse($this->retryDateFrom)->startOfDay();
        $to = Carbon::parse($this->retryDateTo)->endOfDay();

        $retried = $queue->enqueue($from, $to, auth()->user());

        $this->showRetryModal = false;
        $this->resetPage();

        session()->flash(
            $retried > 0 ? 'success' : 'error',
            $retried > 0
                ? __(':count SMS retries queued. You can close this page; sending starts on the next scheduled run.', ['count' => $retried])
                : __('No new eligible SMS retries. Messages may already be queued, require review, have invalid numbers, or belong to WhatsApp.')
        );
    }

    public function controlRetries(string $action): void
    {
        $this->authorize('sms.logs.view');
        $this->authorize('sms.send');
        abort_unless(in_array($action, ['pause', 'resume', 'cancel']), 422);
        $states = match ($action) {
            'pause' => ['pending'],
            'resume' => ['paused'],
            'cancel' => ['pending', 'paused'],
        };
        SmsRetry::query()->whereIn('status', $states)->update([
            'status' => match ($action) {
                'pause' => 'paused', 'resume' => 'pending', 'cancel' => 'cancelled'
            },
        ]);
    }

    public function retryEntry(int $id): void
    {
        $this->authorize('sms.logs.view');
        $this->authorize('sms.send');
        SmsRetry::query()->whereKey($id)->whereIn('status', ['failed', 'cancelled'])
            ->where('attempts', '<', 3)->update(['status' => 'pending', 'reason' => null, 'available_at' => now()]);
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
            'retryStats' => SmsRetry::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'recentRetries' => SmsRetry::query()->whereIn('status', ['failed', 'unknown', 'cancelled'])->latest('updated_at')->limit(10)->get(),
        ])->layout('layouts.app', ['title' => __('SMS Logs')]);
    }
}
