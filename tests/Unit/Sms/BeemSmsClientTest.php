<?php

namespace Tests\Unit\Sms;

use App\Models\BeemConfig;
use App\Services\Sms\BeemSmsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeemSmsClientTest extends TestCase
{
    public function test_send_makes_http_post_with_correct_payload_and_returns_success(): void
    {
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'sender_name' => 'MYAPP',
            'sms_enabled' => true,
        ]);

        Http::fake([
            'https://apisms.beem.africa/v1/send' => Http::response([
                'successful' => true,
                'request_id' => 'beem-req-xyz',
            ], 200),
        ]);

        $client = app(BeemSmsClient::class);
        $result = $client->send('+255712345678', 'Test message');

        $this->assertTrue($result['success']);
        $this->assertEquals('beem-req-xyz', $result['message_id']);
        $this->assertEquals(200, $result['http_status']);

        Http::assertSent(function ($request) {
            $url = $request->url();
            if (strpos($url, 'apisms.beem.africa/v1/send') === false) {
                return false;
            }
            $body = $request->data();
            return $body['source_addr'] === 'MYAPP'
                && $body['message'] === 'Test message'
                && isset($body['recipients']['recipient_id_0'])
                && $body['recipients']['recipient_id_0'] === '255712345678';
        });
    }

    public function test_send_returns_failure_when_api_returns_unsuccessful(): void
    {
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'sender_name' => 'MYAPP',
            'sms_enabled' => true,
        ]);

        Http::fake([
            'https://apisms.beem.africa/v1/send' => Http::response([
                'successful' => false,
                'message' => 'Invalid recipient',
            ], 200),
        ]);

        $client = app(BeemSmsClient::class);
        $result = $client->send('+255712345678', 'Test');

        $this->assertFalse($result['success']);
        $this->assertNull($result['message_id']);
        $this->assertEquals(200, $result['http_status']);
        $this->assertArrayHasKey('raw_response', $result);
        $this->assertFalse($result['raw_response']['successful'] ?? true);
    }

    public function test_is_configured_returns_true_when_keys_set(): void
    {
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => 'key',
            'secret_key' => 'secret',
            'sender_name' => 'X',
            'sms_enabled' => false,
        ]);

        $client = app(BeemSmsClient::class);
        $this->assertTrue($client->isConfigured());
    }

    public function test_is_configured_returns_false_when_keys_missing(): void
    {
        BeemConfig::query()->delete();
        BeemConfig::create([
            'api_key' => '',
            'secret_key' => '',
            'sender_name' => 'X',
            'sms_enabled' => false,
        ]);

        $client = app(BeemSmsClient::class);
        $this->assertFalse($client->isConfigured());
    }
}
