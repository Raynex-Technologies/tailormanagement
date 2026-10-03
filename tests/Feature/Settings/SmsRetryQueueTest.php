<?php

namespace Tests\Feature\Settings;

use App\Livewire\Sms\Logs\Index;
use App\Models\BeemConfig;
use App\Models\SmsLog;
use App\Models\SmsRetry;
use App\Models\User;
use App\Services\Sms\SmsRetryQueue;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class SmsRetryQueueTest extends TestCase
{
    private function log(array $attributes = []): SmsLog
    {
        return SmsLog::create(array_merge([
            'branch_id' => $this->branch->id, 'provider' => 'beem',
            'to' => '+255712345678', 'message' => 'Your order is ready', 'status' => 'failed',
        ], $attributes));
    }

    private function operator(): User
    {
        $user = User::factory()->forBranch($this->branch)->create();
        $user->givePermissionTo(['sms.logs.view', 'sms.send']);
        $this->actingAs($user);
        $this->setBranchContext();

        return $user;
    }

    private function configureProvider(): void
    {
        BeemConfig::instance()->update(['sms_enabled' => true, 'api_key' => 'test-key', 'secret_key' => 'test-secret']);
        Http::preventStrayRequests();
        config(['sms-retries.max_messages' => 1]);
    }

    private function consoleContext(): void
    {
        auth()->forgetGuards();
        BranchContext::clear();
    }

    public function test_bulk_action_queues_once_without_http_and_preserves_branch_and_channel_boundaries(): void
    {
        Http::preventStrayRequests();
        $this->log();
        $this->log();
        $this->log(['branch_id' => $this->otherBranch->id]);
        $this->log(['provider' => 'twilio_whatsapp']);
        $this->log(['to' => 'missing']);
        $this->log(['message' => 'Unknown', 'provider_response' => json_encode(['outcome_unknown' => true])]);
        $this->operator();
        $page = Livewire::test(Index::class)->set('retryDateFrom', now()->toDateString())
            ->set('retryDateTo', now()->toDateString());
        $page->call('retryFailedMessages')->assertHasNoErrors()->call('retryFailedMessages')->assertHasNoErrors();
        $this->assertDatabaseCount('sms_retries', 1);
        $this->assertDatabaseHas('sms_retries', ['branch_id' => $this->branch->id, 'status' => 'pending']);
        Http::assertNothingSent();
    }

    public function test_worker_sends_one_job_and_duplicate_execution_does_not_send_again(): void
    {
        Log::spy();
        $this->log();
        $this->log(['message' => 'Second notification']);
        $user = $this->operator();
        $queue = app(SmsRetryQueue::class);
        $queue->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        Http::fake(['*' => Http::response(['successful' => true, 'request_id' => 'test-123'])]);
        $this->consoleContext();
        $this->artisan('sms:work-retries')->assertSuccessful();
        $this->assertDatabaseHas('sms_retries', ['id' => 1, 'status' => 'sent', 'attempts' => 1]);
        $this->assertDatabaseHas('sms_retries', ['id' => 2, 'status' => 'pending']);
        $queue->process(SmsRetry::findOrFail(1));
        Http::assertSentCount(1);
        $this->assertFalse(BranchContext::isInitialized());
        $this->actingAs($user);
        $this->setBranchContext();
        $this->assertSame(0, $queue->enqueue(now()->startOfDay(), now()->endOfDay(), $user));
        Log::shouldHaveReceived('log')->with('info', 'sms.retry.started', \Mockery::on(fn ($context) => $context['retry_id'] === 1 && $context['status'] === 'processing' && $context['attempt_number'] === 1
        ))->once();
        Log::shouldHaveReceived('log')->with('info', 'sms.retry.completed', \Mockery::on(fn ($context) => $context['retry_id'] === 1 && $context['status'] === 'sent' && $context['http_status'] === 200
            && $context['attempt_log_id'] !== null && $context['branch_id'] === $this->branch->id
            && isset($context['duration_ms']) && array_intersect(['to', 'message', 'api_key'], array_keys($context)) === []
        ))->once();
    }

    public function test_failure_pauses_backlog_and_http_post_is_not_retried(): void
    {
        Log::spy();
        $this->log();
        $this->log(['message' => 'Second']);
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        Http::fake(['*' => Http::response(['successful' => false, 'message' => 'Insufficient funds'], 400)]);
        $this->consoleContext();
        $this->artisan('sms:work-retries')->assertSuccessful();
        $this->assertDatabaseHas('sms_retries', ['id' => 1, 'status' => 'failed']);
        $this->assertDatabaseHas('sms_retries', ['id' => 2, 'status' => 'paused']);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('log')->with('warning', 'sms.retry.completed', \Mockery::on(fn ($context) => $context['retry_id'] === 1 && $context['status'] === 'failed' && $context['http_status'] === 400
        ))->once();
    }

    public function test_ambiguous_response_requires_review_and_cannot_be_requeued(): void
    {
        $this->log();
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        Http::fake(['*' => Http::response([], 503)]);
        $this->consoleContext();
        $this->artisan('sms:work-retries')->assertSuccessful();
        $this->actingAs($user);
        $this->setBranchContext();
        Livewire::test(Index::class)->call('retryEntry', 1)->assertHasNoErrors();
        $this->assertDatabaseHas('sms_retries', ['id' => 1, 'status' => 'unknown']);
        Http::assertSentCount(1);
    }

    public function test_rate_limit_rejection_is_delayed_and_capped_at_three_actual_attempts(): void
    {
        Log::spy();
        $this->log();
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        Http::fake(['*' => Http::response(['successful' => false], 429)]);
        $this->consoleContext();
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->artisan('sms:work-retries')->assertSuccessful();
            $this->assertDatabaseHas('sms_retries', ['id' => 1, 'attempts' => $attempt, 'status' => $attempt < 3 ? 'pending' : 'failed']);
            $this->travel(11)->minutes();
            Cache::forget('sms-retries:next-send');
        }
        Http::assertSentCount(3);
        Log::shouldHaveReceived('log')->with('info', 'sms.retry.completed', \Mockery::on(fn ($context) => $context['status'] === 'pending' && $context['http_status'] === 429 && $context['available_at'] !== null
        ))->twice();
    }

    public function test_stale_claim_is_not_resent_and_worker_lock_blocks_overlap(): void
    {
        Log::spy();
        Http::preventStrayRequests();
        $this->log();
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        SmsRetry::query()->update(['status' => 'processing', 'claimed_at' => now()->subMinutes(6)]);
        $lock = Cache::lock('sms-retries:worker', 300);
        $lock->get();
        $this->artisan('sms:work-retries')->assertSuccessful();
        $this->assertDatabaseHas('sms_retries', ['status' => 'processing']);
        $lock->release();
        $this->artisan('sms:work-retries')->assertSuccessful();
        $this->assertDatabaseHas('sms_retries', ['status' => 'unknown']);
        Http::assertNothingSent();
        Log::shouldHaveReceived('log')->with('warning', 'sms.retry.interrupted', \Mockery::on(fn ($context) => $context['retry_id'] === 1 && $context['status'] === 'unknown'
        ))->once();
    }

    public function test_controls_are_branch_scoped_and_require_send_permission(): void
    {
        $this->log();
        $otherLog = $this->log(['branch_id' => $this->otherBranch->id]);
        $other = SmsRetry::create(['branch_id' => $this->otherBranch->id, 'sms_log_id' => $otherLog->id, 'fingerprint' => 'other', 'status' => 'paused']);
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        Livewire::test(Index::class)->call('controlRetries', 'resume')->call('controlRetries', 'cancel');
        $this->assertSame('paused', $other->fresh()->status);
        $this->assertDatabaseHas('sms_retries', ['branch_id' => $this->branch->id, 'status' => 'cancelled']);
        $user->revokePermissionTo('sms.send');
        Livewire::test(Index::class)->call('controlRetries', 'resume')->assertForbidden();
        Livewire::test(Index::class)->call('retryEntry', $other->id)->assertForbidden();
        Livewire::test(Index::class)->call('retryFailedMessages')->assertForbidden();
    }

    public function test_deleted_source_and_revoked_permission_are_skipped_without_sending(): void
    {
        Http::preventStrayRequests();
        $log = $this->log();
        $this->log(['message' => 'Second']);
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $log->delete();
        $this->consoleContext();
        app(SmsRetryQueue::class)->process(SmsRetry::findOrFail(1));
        $user->revokePermissionTo('sms.send');
        app(SmsRetryQueue::class)->process(SmsRetry::findOrFail(2));
        $this->assertSame(2, SmsRetry::where('status', 'skipped')->count());
        Http::assertNothingSent();
    }

    public function test_later_identical_notifications_can_be_queued_after_success_without_requeuing_old_failures(): void
    {
        $this->log();
        $user = $this->operator();
        $queue = app(SmsRetryQueue::class);
        $this->assertSame(1, $queue->enqueue(now()->startOfDay(), now()->endOfDay(), $user));
        $this->configureProvider();
        Http::fake(['*' => Http::response(['successful' => true, 'request_id' => 'ok'])]);
        for ($generation = 1; $generation <= 3; $generation++) {
            $this->consoleContext();
            Cache::forget('sms-retries:next-send');
            $this->artisan('sms:work-retries')->assertSuccessful();
            $this->actingAs($user);
            $this->setBranchContext();
            $this->assertSame(0, $queue->enqueue(now()->startOfDay(), now()->endOfDay(), $user));
            $this->travel(2)->seconds();
            $this->log();
            $this->assertSame(1, $queue->enqueue(now()->startOfDay(), now()->endOfDay(), $user));
        }
        Http::assertSentCount(3);
    }

    public function test_rate_limit_cooldown_prevents_another_pending_message_from_sending(): void
    {
        $this->log();
        $this->log(['message' => 'Second']);
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        Http::fake(['*' => Http::response(['successful' => false], 429)]);
        $this->consoleContext();
        $this->artisan('sms:work-retries')->assertSuccessful();
        Cache::forget('sms-retries:next-send');
        $this->artisan('sms:work-retries')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_retries', ['id' => 2, 'status' => 'pending', 'attempts' => 0]);
    }

    public function test_worker_uses_database_cache_and_links_the_attempt_before_http(): void
    {
        $this->log();
        $user = $this->operator();
        app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user);
        $this->configureProvider();
        config(['cache.default' => 'database']);
        Http::fake(function () {
            $retry = SmsRetry::withoutGlobalScopes()->firstOrFail();
            $this->assertSame('processing', $retry->status);
            $this->assertNotNull($retry->attempt_log_id);
            $this->assertDatabaseHas('sms_logs', ['id' => $retry->attempt_log_id, 'status' => 'queued']);

            return Http::response(['successful' => true, 'request_id' => 'linked']);
        });
        $this->consoleContext();
        $this->artisan('sms:work-retries')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_retries', ['status' => 'sent']);
        $this->assertDatabaseHas('cache', ['key' => config('cache.prefix').'sms-retries:next-send']);
    }

    public function test_bulk_enqueue_deduplicates_across_database_chunks(): void
    {
        Http::preventStrayRequests();
        $attributes = $this->log()->getAttributes();
        unset($attributes['id']);
        SmsLog::insert(array_fill(0, 205, $attributes));
        $user = $this->operator();
        $this->assertSame(1, app(SmsRetryQueue::class)->enqueue(now()->startOfDay(), now()->endOfDay(), $user));
        $this->assertDatabaseCount('sms_retries', 1);
        Http::assertNothingSent();
    }
}
