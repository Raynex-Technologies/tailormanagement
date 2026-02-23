<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\BeemConfig;
use App\Models\Order;
use App\Services\Sms\SmsService;
use App\Services\Sms\Templates\OrderSmsTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendOrderDueDateReminderSms extends Command
{
    protected $signature = 'orders:send-due-date-reminders
                            {--days=1 : Send reminders for orders due in the next N days (default: today and tomorrow)}';

    protected $description = 'Send SMS reminders for orders due soon (when SMS is enabled)';

    public function handle(SmsService $smsService): int
    {
        // Run synchronously so reminders are sent immediately (no queue jobs)
        $previousConnection = config('queue.default');
        config(['queue.default' => 'sync']);

        $beemConfig = BeemConfig::instance();
        if (! $beemConfig->sms_enabled) {
            Log::info('Order due date reminders skipped - SMS disabled');
            $this->info('SMS is disabled. Skipping due date reminders.');
            config(['queue.default' => $previousConnection]);

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $start = now()->startOfDay();
        $end = now()->addDays($days)->startOfDay();

        Log::debug('Order due date reminders running', ['days' => $days, 'start' => $start->toDateString(), 'end' => $end->toDateString()]);

        $orders = Order::query()
            ->whereDate('due_date', '>=', $start)
            ->whereDate('due_date', '<=', $end)
            ->whereIn('status', [
                OrderStatus::New,
                OrderStatus::InProgress,
                OrderStatus::Ready,
            ])
            ->with('customer')
            ->get();

        $sent = 0;
        $skippedNoPhone = 0;
        foreach ($orders as $order) {
            $phone = $order->customer?->phone;
            if (empty($phone)) {
                Log::debug('Due date reminder skipped - no phone', ['order_id' => $order->id, 'order_no' => $order->order_no]);
                $skippedNoPhone++;
                continue;
            }

            $message = OrderSmsTemplates::dueDateReminder($order);
            $smsService->sendIfPhonePresent($phone, $message, $order, null);
            $sent++;
        }

        Log::info('Order due date reminders completed', [
            'orders_found' => $orders->count(),
            'sent' => $sent,
            'skipped_no_phone' => $skippedNoPhone,
        ]);
        $this->info("Sent {$sent} due date reminder(s).");

        config(['queue.default' => $previousConnection]);

        return self::SUCCESS;
    }
}
