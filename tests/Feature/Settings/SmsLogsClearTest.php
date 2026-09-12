<?php

namespace Tests\Feature\Settings;

use App\Livewire\Sms\Logs\Index;
use App\Models\SmsLog;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SmsLogsClearTest extends TestCase
{
    public function test_clear_soft_deletes_all_statuses_and_dates_but_preserves_other_branches(): void
    {
        $logs = collect(['sent', 'failed', 'queued', 'skipped'])->map(fn ($status) => SmsLog::create([
            'branch_id' => $this->branch->id,
            'provider' => 'beem', 'to' => '+255712345678',
            'message' => 'Test', 'status' => $status,
        ]));
        $logs->first()->update(['created_at' => now()->subYear()]);
        $other = SmsLog::create([
            'branch_id' => $this->otherBranch->id,
            'provider' => 'beem', 'to' => '+255712345678',
            'message' => 'Other branch', 'status' => 'sent',
        ]);
        $user = User::factory()->forBranch($this->branch)->create();
        $user->givePermissionTo(['sms.logs.view', 'sms.send']);
        $this->actingAs($user);
        $this->setBranchContext();

        Livewire::test(Index::class)
            ->assertSee('Clear Logs')
            ->set('search', 'not matching')
            ->set('dateFrom', now()->toDateString())
            ->set('statusFilter', 'failed')
            ->call('openClearLogsModal')
            ->assertSet('showClearLogsModal', true)
            ->call('clearLogs')
            ->assertSet('showClearLogsModal', false)
            ->assertSee('No SMS logs found');

        foreach ($logs as $log) {
            $this->assertSoftDeleted('sms_logs', ['id' => $log->id]);
        }
        $this->assertDatabaseHas('sms_logs', ['id' => $other->id, 'deleted_at' => null]);
    }

    public function test_view_only_user_cannot_clear_logs(): void
    {
        $user = User::factory()->forBranch($this->branch)->create();
        $user->givePermissionTo('sms.logs.view');
        $this->actingAs($user);
        $this->setBranchContext();
        $log = SmsLog::create([
            'provider' => 'beem', 'to' => '+255712345678',
            'message' => 'Retain', 'status' => 'sent',
        ]);

        Livewire::test(Index::class)->assertDontSee('wire:click="openClearLogsModal"', false)
            ->call('openClearLogsModal')->assertForbidden();
        Livewire::test(Index::class)->call('clearLogs')->assertForbidden();
        $this->assertDatabaseHas('sms_logs', ['id' => $log->id, 'deleted_at' => null]);
    }
}
