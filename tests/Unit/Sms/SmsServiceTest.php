<?php

namespace Tests\Unit\Sms;

use App\Enums\SmsStatus;
use App\Models\BeemConfig;
use App\Models\SmsLog;
use App\Services\Sms\BeemSmsClient;
use App\Services\Sms\SmsService;
use Mockery;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_calls_beem_client_and_marks_log_sent_when_enabled_and_configured(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('send')
            ->once()
            ->with('+255712345678', 'Hello from test')
            ->andReturn([
                'success' => true,
                'message_id' => 'req-123',
                'raw_response' => ['successful' => true, 'request_id' => 'req-123'],
                'http_status' => 200,
            ]);
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->send('0712345678', 'Hello from test');

        $this->assertInstanceOf(SmsLog::class, $log);
        $this->assertEquals(SmsStatus::Sent, $log->status);
        $this->assertEquals('+255712345678', $log->to);
        $this->assertEquals('req-123', $log->provider_message_id);
    }

    public function test_send_does_not_call_beem_when_sms_disabled(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => false,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldNotReceive('send');
        $mock->shouldNotReceive('isConfigured');
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->send('0712345678', 'Should not send');

        $this->assertEquals(SmsStatus::Failed, $log->status);
        $this->assertStringContainsString('SMS disabled', $log->provider_response ?? '');
    }

    public function test_send_does_not_call_beem_when_client_not_configured(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(false);
        $mock->shouldNotReceive('send');
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->send('0712345678', 'Should not send');

        $this->assertEquals(SmsStatus::Failed, $log->status);
        $this->assertStringContainsString('not configured', $log->provider_response ?? '');
    }

    public function test_send_creates_failed_log_when_phone_invalid(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldNotReceive('send');
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->send('1', 'Invalid phone'); // too short, toE164Tz returns null

        $this->assertEquals(SmsStatus::Failed, $log->status);
        $this->assertStringContainsString('Invalid phone', $log->provider_response ?? '');
    }

    public function test_send_if_phone_present_skips_send_when_no_phone(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldNotReceive('send');
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->sendIfPhonePresent(null, 'No phone');

        $this->assertNotNull($log);
        $this->assertEquals(SmsStatus::Failed, $log->status);
        $this->assertEquals('missing', $log->to);
        $this->assertStringContainsString('Missing phone', $log->provider_response ?? '');
    }

    public function test_send_if_phone_present_calls_send_when_phone_given(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('send')
            ->once()
            ->with('+255712345678', 'Order ready')
            ->andReturn([
                'success' => true,
                'message_id' => 'req-456',
                'raw_response' => [],
                'http_status' => 200,
            ]);
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->sendIfPhonePresent('0712345678', 'Order ready');

        $this->assertNotNull($log);
        $this->assertEquals(SmsStatus::Sent, $log->status);
    }

    public function test_send_updates_log_to_failed_when_beem_returns_failure(): void
    {
        $this->setBranchContext();
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-key',
            'secret_key' => 'test-secret',
            'sender_name' => 'TEST',
            'sms_enabled' => true,
        ]);

        $mock = Mockery::mock(BeemSmsClient::class);
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('send')
            ->once()
            ->andReturn([
                'success' => false,
                'message_id' => null,
                'raw_response' => ['successful' => false, 'message' => 'Invalid sender'],
                'http_status' => 200,
            ]);
        $this->app->instance(BeemSmsClient::class, $mock);

        $service = app(SmsService::class);
        $log = $service->send('0712345678', 'Test');

        $this->assertEquals(SmsStatus::Failed, $log->status);
        $this->assertStringContainsString('Invalid sender', $log->provider_response ?? '');
    }
}
