<?php

namespace App\Services\Sms;

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

    public function __construct()
    {
        $this->apiKey = config('beem.api_key');
        $this->secretKey = config('beem.secret_key');
        $this->senderId = config('beem.sender_id');
        $this->baseUrl = config('beem.base_url');
        $this->timeout = config('beem.timeout', 30);
        $this->retryTimes = config('beem.retry_times', 2);
        $this->retrySleep = config('beem.retry_sleep', 500);
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

        // Prepare recipient list for Beem format
        $recipientList = [];
        foreach ($recipients as $index => $phone) {
            // Remove + from phone number (Beem expects without +)
            $cleanPhone = ltrim($phone, '+');
            $recipientList["recipient_id_{$index}"] = $cleanPhone;
        }

        // Prepare request payload
        $payload = [
            'source_addr' => $this->senderId,
            'encoding' => 0,
            'message' => $message,
            'recipients' => $recipientList,
        ];

        try {
            $response = Http::withBasicAuth($this->apiKey, $this->secretKey)
                ->timeout($this->timeout)
                ->retry($this->retryTimes, $this->retrySleep)
                ->post("{$this->baseUrl}/send", $payload);

            $body = $response->json() ?? [];

            // Beem returns successful: true on success
            $isSuccess = $response->successful() && ($body['successful'] ?? false);

            return [
                'success' => $isSuccess,
                'message_id' => $body['request_id'] ?? null,
                'raw_response' => $body,
                'http_status' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Beem SMS API error', [
                'message' => $e->getMessage(),
                'recipients' => $recipients,
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
