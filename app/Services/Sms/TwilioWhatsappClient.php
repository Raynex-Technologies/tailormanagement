<?php

namespace App\Services\Sms;

use App\Models\TwilioWhatsappConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TwilioWhatsappClient
{
    protected string $accountSid;

    protected string $authToken;

    protected string $fromNumber;

    protected string $messagingServiceSid;

    public function __construct()
    {
        $config = TwilioWhatsappConfig::instance();

        $this->accountSid = trim((string) ($config->account_sid ?: config('twilio.account_sid', '')));
        $this->authToken = trim((string) ($config->auth_token ?: config('twilio.auth_token', '')));
        $this->fromNumber = trim((string) ($config->from_number ?: config('twilio.whatsapp_from', '')));
        $this->messagingServiceSid = trim((string) ($config->messaging_service_sid ?: config('twilio.messaging_service_sid', '')));
    }

    public function isConfigured(): bool
    {
        return $this->accountSid !== ''
            && $this->authToken !== ''
            && ($this->fromNumber !== '' || $this->messagingServiceSid !== '');
    }

    public function send(string $to, string $message): array
    {
        $payload = [
            'To' => $this->formatWhatsappAddress($to),
            'Body' => $message,
        ];

        if ($this->messagingServiceSid !== '') {
            $payload['MessagingServiceSid'] = $this->messagingServiceSid;
        } else {
            $payload['From'] = $this->formatWhatsappAddress($this->fromNumber);
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->timeout((int) config('twilio.timeout', 30))
                ->retry((int) config('twilio.retry_times', 2), (int) config('twilio.retry_sleep', 500), null, false)
                ->post($this->messagesUrl(), $payload);

            $body = $response->json() ?? [];

            return [
                'success' => $response->successful(),
                'message_id' => $body['sid'] ?? null,
                'raw_response' => $body,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message_id' => null,
                'raw_response' => ['error' => $exception->getMessage()],
                'http_status' => 0,
            ];
        }
    }

    public function sendContentTemplate(string $to, string $contentSid, array $variables = []): array
    {
        $payload = [
            'To' => $this->formatWhatsappAddress($to),
            'ContentSid' => $contentSid,
            'ContentVariables' => json_encode((object) $variables),
        ];

        if ($this->messagingServiceSid !== '') {
            $payload['MessagingServiceSid'] = $this->messagingServiceSid;
        } else {
            $payload['From'] = $this->formatWhatsappAddress($this->fromNumber);
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->timeout((int) config('twilio.timeout', 30))
                ->retry((int) config('twilio.retry_times', 2), (int) config('twilio.retry_sleep', 500), null, false)
                ->post($this->messagesUrl(), $payload);

            $body = $response->json() ?? [];

            return [
                'success' => $response->successful(),
                'message_id' => $body['sid'] ?? null,
                'raw_response' => $body,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message_id' => null,
                'raw_response' => ['error' => $exception->getMessage()],
                'http_status' => 0,
            ];
        }
    }

    public function submitTemplate(string $code, string $body, string $language = 'en', string $category = 'UTILITY'): array
    {
        $contentName = Str::of($code)
            ->lower()
            ->replaceMatches('/[^a-z0-9_]+/', '_')
            ->trim('_')
            ->limit(64, '')
            ->toString();

        $compiled = $this->compileTemplateBody($body);

        try {
            $contentResponse = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->timeout((int) config('twilio.timeout', 30))
                ->retry((int) config('twilio.retry_times', 2), (int) config('twilio.retry_sleep', 500), null, false)
                ->post($this->contentUrl('/Content'), [
                    'friendly_name' => Str::headline($code),
                    'language' => $language,
                    'variables' => (object) $compiled['variables'],
                    'types' => [
                        'twilio/text' => [
                            'body' => $compiled['body'],
                        ],
                    ],
                ]);

            $content = $contentResponse->json() ?? [];
            $contentSid = $content['sid'] ?? null;

            if (! $contentResponse->successful() || ! $contentSid) {
                return [
                    'success' => false,
                    'status' => 'rejected',
                    'content_sid' => null,
                    'approval_request_sid' => null,
                    'raw_response' => $content,
                    'http_status' => $contentResponse->status(),
                ];
            }

            $approvalResponse = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->timeout((int) config('twilio.timeout', 30))
                ->retry((int) config('twilio.retry_times', 2), (int) config('twilio.retry_sleep', 500), null, false)
                ->post($this->contentUrl("/Content/{$contentSid}/ApprovalRequests/whatsapp"), [
                    'name' => $contentName,
                    'category' => strtoupper($category),
                ]);

            $approval = $approvalResponse->json() ?? [];

            return [
                'success' => $approvalResponse->successful(),
                'status' => $approvalResponse->successful() ? 'pending' : 'rejected',
                'content_sid' => $contentSid,
                'approval_request_sid' => $approval['sid'] ?? null,
                'raw_response' => [
                    'content' => $content,
                    'approval' => $approval,
                ],
                'http_status' => $approvalResponse->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status' => 'rejected',
                'content_sid' => null,
                'approval_request_sid' => null,
                'raw_response' => ['error' => $exception->getMessage()],
                'http_status' => 0,
            ];
        }
    }

    public function fetchTemplateStatus(string $contentSid): array
    {
        try {
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->timeout((int) config('twilio.timeout', 30))
                ->retry((int) config('twilio.retry_times', 2), (int) config('twilio.retry_sleep', 500), null, false)
                ->get($this->contentUrl("/Content/{$contentSid}/ApprovalRequests"));

            $body = $response->json() ?? [];
            $request = $body['approval_requests'][0] ?? $body['whatsapp'] ?? $body;
            $status = strtolower((string) ($request['status'] ?? 'pending'));

            if (! in_array($status, ['approved', 'rejected', 'pending'], true)) {
                $status = 'pending';
            }

            return [
                'success' => $response->successful(),
                'status' => $status,
                'rejection_reason' => $request['rejection_reason'] ?? $request['rejection_reason_code'] ?? null,
                'raw_response' => $body,
                'http_status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'status' => 'pending',
                'rejection_reason' => null,
                'raw_response' => ['error' => $exception->getMessage()],
                'http_status' => 0,
            ];
        }
    }

    protected function compileTemplateBody(string $body): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $body, $matches);

        $variables = [];
        $compiled = $body;
        $indexByName = [];

        foreach ($matches[1] ?? [] as $name) {
            if (! isset($indexByName[$name])) {
                $indexByName[$name] = (string) (count($indexByName) + 1);
                $variables[$indexByName[$name]] = Str::headline($name);
            }

            $compiled = str_replace('{'.$name.'}', '{{'.$indexByName[$name].'}}', $compiled);
        }

        return [
            'body' => $compiled,
            'variables' => $variables,
        ];
    }

    protected function formatWhatsappAddress(string $phone): string
    {
        return str_starts_with($phone, 'whatsapp:') ? $phone : 'whatsapp:'.$phone;
    }

    protected function messagesUrl(): string
    {
        $baseUrl = rtrim((string) config('twilio.messages_api_base_url'), '/');

        return "{$baseUrl}/Accounts/{$this->accountSid}/Messages.json";
    }

    protected function contentUrl(string $path): string
    {
        return rtrim((string) config('twilio.content_api_base_url'), '/').$path;
    }
}
