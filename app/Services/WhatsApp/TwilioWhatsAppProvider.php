<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Data\WhatsApp\TemplateDefinition;
use App\Models\WhatsappIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TwilioWhatsAppProvider implements WhatsAppProvider
{
    public function testConnection(WhatsappIntegration $integration): array
    {
        $result = $this->request($integration, 'GET', $this->accountUrl($integration));
        if ($result['success'] && (data_get($result, 'data.sid') !== $integration->twilio_account_sid || data_get($result, 'data.status') !== 'active')) {
            return $this->failure('account_inactive', 'Twilio did not return the configured active account.');
        }
        if ($result['success']) {
            $result['data'] = ['display_phone_number' => $integration->twilio_from, 'verified_name' => data_get($result, 'data.friendly_name')];
        }

        return $result;
    }

    public function sendText(WhatsappIntegration $integration, string $recipient, string $message): array
    {
        return $this->send($integration, $recipient, ['Body' => $message]);
    }

    public function sendTemplate(WhatsappIntegration $integration, string $recipient, string $name, string $language, array $components = []): array
    {
        if (! preg_match('/^HX[0-9a-fA-F]{32}$/', $name)) {
            return $this->failure('invalid_content_sid', 'An approved Twilio Content SID is required.');
        }

        return $this->send($integration, $recipient, ['ContentSid' => $name, 'ContentVariables' => json_encode((object) $components)]);
    }

    private function send(WhatsappIntegration $integration, string $recipient, array $content): array
    {
        $result = $this->request($integration, 'POST', $this->accountUrl($integration, '/Messages.json'), $content + [
            'From' => 'whatsapp:'.$integration->twilio_from,
            'To' => 'whatsapp:+'.ltrim($recipient, '+'),
            'StatusCallback' => rtrim(config('app.url'), '/').route('webhooks.twilio-whatsapp', ['webhookKey' => $integration->webhook_key], false),
        ], true);
        if (! $result['success']) {
            return $result;
        }
        $sid = data_get($result, 'data.sid');

        return is_string($sid) && preg_match('/^SM[0-9a-fA-F]{32}$/', $sid)
            ? ['success' => true, 'message_id' => $sid]
            : $this->failure('delivery_unknown', 'Twilio returned no message SID. Check Twilio logs before retrying.');
    }

    public function createTemplate(WhatsappIntegration $integration, TemplateDefinition $definition): array
    {
        if (count($definition->components) !== 1 || ($definition->components[0]['type'] ?? '') !== 'BODY' || ! in_array($definition->category, ['UTILITY', 'MARKETING'], true)) {
            return $this->failure('unsupported_template', 'Twilio submission currently supports Utility and Marketing text bodies only.');
        }
        $body = $definition->components[0];

        return $this->request($integration, 'POST', $this->contentUrl(), [
            'friendly_name' => $definition->name, 'language' => $definition->language,
            'variables' => (object) ($body['examples'] ?? []),
            'types' => ['twilio/text' => ['body' => $body['text']]],
        ]);
    }

    public function fetchContent(WhatsappIntegration $integration, string $sid): array
    {
        if (! preg_match('/^HX[0-9a-fA-F]{32}$/', $sid)) {
            return $this->failure('invalid_content_sid', 'Enter a valid Twilio Content SID.');
        }

        return $this->request($integration, 'GET', $this->contentUrl('/'.$sid));
    }

    public function submitApproval(WhatsappIntegration $integration, string $sid, TemplateDefinition $definition): array
    {
        return $this->request($integration, 'POST', $this->contentUrl('/'.$sid.'/ApprovalRequests/whatsapp'), ['name' => $definition->name, 'category' => $definition->category]);
    }

    public function approval(WhatsappIntegration $integration, string $sid): array
    {
        return $this->request($integration, 'GET', $this->contentUrl('/'.$sid.'/ApprovalRequests'));
    }

    public function updateTemplate(WhatsappIntegration $integration, string $metaTemplateId, TemplateDefinition $definition): array
    {
        return $this->failure('immutable_content', 'Duplicate this template with a new name to submit revised content.');
    }

    public function deleteTemplate(WhatsappIntegration $integration, string $name): array
    {
        if (! preg_match('/^HX[0-9a-fA-F]{32}$/', $name)) {
            return $this->failure('invalid_content_sid', 'A Twilio Content SID is required.');
        }

        return $this->request($integration, 'DELETE', $this->contentUrl('/'.$name));
    }

    public function listTemplates(WhatsappIntegration $integration, ?string $after = null): array
    {
        return $this->failure('local_sync_only', 'Refresh approval statuses for templates submitted from this branch.');
    }

    public function uploadTemplateMedia(WhatsappIntegration $integration, string $path, string $mimeType, string $fileName): array
    {
        return $this->failure('unsupported_media', 'This Twilio template editor supports text notification templates.');
    }

    public function markAsRead(WhatsappIntegration $integration, string $externalMessageId): array
    {
        return $this->failure('unsupported_read_receipt', 'Marking inbound messages as read is not supported by this Twilio integration.');
    }

    public function configureWebhooks(WhatsappIntegration $integration, string $callbackUrl): array
    {
        return $this->failure('manual_webhook_setup', 'Set the displayed incoming-message URL in the Twilio WhatsApp sender settings. Outbound status callbacks are attached automatically.');
    }

    private function request(WhatsappIntegration $integration, string $method, string $url, array $data = [], bool $form = false): array
    {
        if (! $integration->twilioConfigured()) {
            return $this->failure('not_configured', 'Configure the Twilio account, Auth Token, and WhatsApp sender first.');
        }
        try {
            $client = Http::withBasicAuth($integration->twilio_account_sid, $integration->twilio_auth_token)
                ->acceptJson()->timeout((int) config('twilio.timeout', 30))->withoutRedirecting();
            if ($form) {
                $client = $client->asForm();
            }
            $response = $client->send($method, $url, $data === [] ? [] : [($form ? 'form_params' : 'json') => $data]);
            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json() ?: []];
            }

            // Never blindly repeat a POST after a timeout or 5xx: it may have been accepted.
            return $this->failure((string) ($response->json('code') ?: 'twilio_api_error'),
                $response->status() === 401 ? 'Twilio rejected the account credentials.' : 'Twilio rejected the request. Review the error code in Twilio before retrying.',
                $response->status() === 429, in_array($response->status(), [401, 403], true)) + ['outcome_unknown' => $response->serverError()];
        } catch (ConnectionException) {
            return $this->failure('request_outcome_unknown', 'Twilio could not be reached or the response timed out. Check Twilio logs before retrying.') + ['outcome_unknown' => true];
        }
    }

    private function accountUrl(WhatsappIntegration $integration, string $suffix = '.json'): string
    {
        return rtrim(config('twilio.messages_api_base_url'), '/').'/Accounts/'.$integration->twilio_account_sid.$suffix;
    }

    private function contentUrl(string $suffix = ''): string
    {
        return rtrim(config('twilio.content_api_base_url'), '/').'/Content'.$suffix;
    }

    private function failure(string $code, string $message, bool $retryable = false, bool $integrationFailure = false): array
    {
        return ['success' => false, 'error_code' => $code, 'error_message' => $message, 'retryable' => $retryable, 'integration_failure' => $integrationFailure];
    }
}
