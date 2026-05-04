<?php

namespace Tests\Unit\Sms;

use App\Enums\SmsStatus;
use App\Models\BeemConfig;
use App\Models\SmsTemplate;
use App\Services\Sms\BeemSmsClient;
use App\Services\Sms\SmsNotificationGate;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplateRenderer;
use Database\Seeders\SmsTemplateSeeder;
use Mockery;
use Tests\TestCase;

class SmsNotificationGateTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_template_sms_sends_when_global_and_template_enabled(): void
    {
        $this->setBranchContext();
        $this->enableSmsTemplate('order_created', true);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('send')
            ->once()
            ->with('+255712345678', 'Hello Asha')
            ->andReturn([
                'success' => true,
                'message_id' => 'sms-1',
                'raw_response' => ['successful' => true],
                'http_status' => 200,
            ]);
        $this->app->instance(BeemSmsClient::class, $mock);

        SmsTemplate::instance()->update([
            'templates' => array_replace(SmsTemplate::defaultTemplates(), [
                'order_created' => 'Hello {customer_name}',
            ]),
        ]);

        $log = app(SmsService::class)->sendTemplate('order_created', '0712345678', [
            'customer_name' => 'Asha',
        ]);

        $this->assertSame(SmsStatus::Sent, $log->status);
        $this->assertSame('order_created', $log->template_code);
    }

    public function test_template_sms_skips_when_global_sms_disabled(): void
    {
        $this->setBranchContext();
        $this->enableSmsTemplate('order_created', true, false);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldNotReceive('isConfigured');
        $mock->shouldNotReceive('send');
        $this->app->instance(BeemSmsClient::class, $mock);

        $log = app(SmsService::class)->sendTemplate('order_created', '0712345678', [
            'customer_name' => 'Asha',
        ]);

        $this->assertSame(SmsStatus::Skipped, $log->status);
        $this->assertSame(SmsNotificationGate::SMS_GLOBAL_DISABLED, $log->skip_reason);
    }

    public function test_template_sms_skips_when_template_disabled(): void
    {
        $this->setBranchContext();
        $this->enableSmsTemplate('order_created', false);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldNotReceive('isConfigured');
        $mock->shouldNotReceive('send');
        $this->app->instance(BeemSmsClient::class, $mock);

        $log = app(SmsService::class)->sendTemplate('order_created', '0712345678', [
            'customer_name' => 'Asha',
        ]);

        $this->assertSame(SmsStatus::Skipped, $log->status);
        $this->assertSame(SmsNotificationGate::TEMPLATE_SMS_DISABLED, $log->skip_reason);
    }

    public function test_template_renderer_replaces_variables_and_does_not_execute_content(): void
    {
        $message = app(SmsTemplateRenderer::class)->render(
            'Hello {{ customer_name }}, {order_number}, <?php echo "bad"; ?> {missing}',
            [
                'customer_name' => 'Asha',
                'order_number' => 'ORD-0001',
            ]
        );

        $this->assertSame('Hello Asha, ORD-0001, <?php echo "bad"; ?> {missing}', $message);
    }

    public function test_sms_template_seeder_is_idempotent(): void
    {
        $this->seed(SmsTemplateSeeder::class);
        $this->seed(SmsTemplateSeeder::class);

        $this->assertSame(1, SmsTemplate::query()->count());
        $this->assertArrayHasKey('order_ready', SmsTemplate::instance()->template_settings);
    }

    protected function enableSmsTemplate(string $code, bool $templateEnabled, bool $globalEnabled = true): void
    {
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'key',
            'secret_key' => 'secret',
            'sender_name' => 'TEST',
            'sms_enabled' => $globalEnabled,
        ]);

        $row = SmsTemplate::instance();
        $settings = SmsTemplate::normalizeTemplateSettings($row->template_settings ?? []);
        $settings[$code]['sms_enabled'] = $templateEnabled;

        $row->update(['template_settings' => $settings]);
    }
}
