<?php

namespace App\Services\Sms;

use App\Models\BeemConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemSmsClient
{
    protected string $apiKey;
    protected string $secretKey;
    protected string $senderId;
    protected string $baseUrl;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;

    /** @var bool|string SSL verification: true, false, or path to CA bundle */
    protected bool|string $verify;

    public function __construct()
    {
        $config = BeemConfig::instance();
        $this->apiKey = (string) ($config->api_key ?: config('beem.api_key', ''));
        $this->secretKey = (string) ($config->secret_key ?: config('beem.secret_key', ''));
        $this->senderId = (string) ($config->sender_name ?: config('beem.sender_id', 'INFO'));
        $this->baseUrl = config('beem.base_url', 'https://apisms.beem.africa/v1');
        $this->timeout = config('beem.timeout', 30);
        $this->retryTimes = config('beem.retry_times', 2);
        $this->retrySleep = config('beem.retry_sleep', 500);
        $this->verify = config('beem.verify', true);

        Log::debug('BeemSmsClient constructed', [
            'credentials_source' => $config->api_key ? 'db' : (config('beem.api_key') ? 'env' : 'none'),
            'has_api_key' => ! empty($this->apiKey),
            'has_secret_key' => ! empty($this->secretKey),
            'sender_id' => $this->senderId,
            'base_url' => $this->baseUrl,
        ]);
    }

    /**
     * Send an SMS message.
     *
     * @param  string|array  $recipients  Phone number(s) in E.164 format
     * @param  string  $message  The message content
     * @return array Response with success, message_id, and raw response
     */
    public function send(string|array $recipients, string $message): array
    {
        // Normalize recipients to array
        $recipients = is_array($recipients) ? $recipients : [$recipients];

        // Prepare recipient list for Beem format: array of { recipient_id, dest_addr }
        $recipientList = [];
        foreach ($recipients as $index => $phone) {
            $cleanPhone = ltrim($phone, '+');
            $recipientList[] = [
                'recipient_id' => (string) ($index + 1),
                'dest_addr' => $cleanPhone,
            ];
        }

        // Prepare request payload (Beem v1 API)
        $payload = [
            'source_addr' => $this->senderId,
            'encoding' => 0,
            'message' => $message,
            'recipients' => $recipientList,
        ];

        $url = "{$this->baseUrl}/send";
        Log::info('Beem API request', [
            'url' => $url,
            'recipient_count' => count($recipients),
            'source_addr' => $this->senderId,
            'message_length' => strlen($message),
        ]);

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->withOptions(['verify' => $this->verify])
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep)
                ->post($url, $payload);

            $body = $response->json() ?? [];
            $httpStatus = $response->status();

            // Beem returns successful: true on success
            $isSuccess = $response->successful() && ($body['successful'] ?? false);

            Log::info('Beem API response', [
                'http_status' => $httpStatus,
                'successful' => $body['successful'] ?? null,
                'request_id' => $body['request_id'] ?? null,
                'result' => $isSuccess ? 'sent' : 'failed',
                'raw_body' => $body,
            ]);

            return [
                'success' => $isSuccess,
                'message_id' => $body['request_id'] ?? null,
                'raw_response' => $body,
                'http_status' => $httpStatus,
            ];
        } catch (\Exception $e) {
            Log::error('Beem SMS API exception', [
                'message' => $e->getMessage(),
                'recipients' => $recipients,
                'url' => $url,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'raw_response' => ['error' => $e->getMessage()],
                'http_status' => 0,
            ];
        }
    }

    /**
     * Check if the client is properly configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! empty($this->secretKey);
    }

    /**
     * Check account balance (useful for monitoring).
     */
    public function getBalance(): ?array
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->withOptions(['verify' => $this->verify])
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/balance");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Beem balance check error', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
