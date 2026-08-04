<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsAppProvider;
use App\Models\WhatsappIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppProvider implements WhatsAppProvider
{
    public function testConnection(WhatsappIntegration $integration): array
    {
        try {
            $phone = $this->get($integration, $integration->phone_number_id, ['fields' => 'id,display_phone_number,verified_name,whatsapp_business_account']);
            if (! $phone['success']) {
                return $phone;
            } $waba = $this->get($integration, $integration->waba_id, ['fields' => 'id,name']);
            if (! $waba['success']) {
                return $waba;
            } $owner = data_get($phone, 'data.whatsapp_business_account.id');
            if ($owner && (string) $owner !== (string) $integration->waba_id) {
                return $this->failure('phone_waba_mismatch', 'The phone number does not belong to the configured WhatsApp Business Account.', false, false);
            }

            return ['success' => true, 'data' => ['display_phone_number' => data_get($phone, 'data.display_phone_number'), 'verified_name' => data_get($phone, 'data.verified_name') ?: data_get($waba, 'data.name')]];
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached. Please try again.', true, false);
        } catch (\Throwable) {
            return $this->failure('meta_error', 'Meta could not verify this integration.', false, false);
        }
    }

    public function sendText(WhatsappIntegration $integration, string $recipient, string $message): array
    {
        try {
            $response = $this->client($integration)->post('/'.$integration->phone_number_id.'/messages', ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $recipient, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => $message]]);

            return $this->messageResult($response);
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while sending the message.', true, false);
        }
    }

    public function markAsRead(WhatsappIntegration $integration, string $externalMessageId): array
    {
        try {
            $response = $this->client($integration)->post('/'.$integration->phone_number_id.'/messages', ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $externalMessageId]);

            return $response->successful() ? ['success' => true] : $this->responseFailure($response);
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached.', true, false);
        }
    }

    public function configureWebhooks(WhatsappIntegration $integration, string $callbackUrl): array
    {
        try {
            $post = $this->client($integration)->post('/'.$integration->waba_id.'/subscribed_apps', ['subscribed_fields' => 'messages,message_template_status_update', 'override_callback_uri' => $callbackUrl, 'verify_token' => $integration->webhook_verify_token]);
            if (! $post->successful()) {
                return $this->responseFailure($post);
            } $verify = $this->get($integration, $integration->waba_id.'/subscribed_apps', []);
            if (! $verify['success']) {
                return $verify;
            } if (empty(data_get($verify, 'data.data'))) {
                return $this->failure('subscription_not_verified', 'Meta did not report an active WABA application subscription.', false, true);
            }

            return ['success' => true, 'data' => $verify['data']];
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while configuring webhooks.', true, true);
        }
    }

    public function createTemplate(WhatsappIntegration $integration, \App\Data\WhatsApp\TemplateDefinition $definition): array
    {
        try {
            $response = $this->client($integration)->post('/'.$integration->waba_id.'/message_templates', $this->templatePayload($definition));
            if (! $response->successful()) {
                return $this->responseFailure($response);
            }

return ['success' => true, 'data' => ['id' => $response->json('id'), 'status' => $response->json('status') ?: 'PENDING', 'category' => $response->json('category') ?: $definition->category]];
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while submitting the template.', true, false);
        }
    }

    public function updateTemplate(WhatsappIntegration $integration, string $metaTemplateId, \App\Data\WhatsApp\TemplateDefinition $definition): array
    {
        try {
            $response = $this->client($integration)->post('/'.$metaTemplateId, ['category' => $definition->category, 'components' => $this->templatePayload($definition)['components']]);

            return $response->successful() ? ['success' => true, 'data' => $response->json()] : $this->responseFailure($response);
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while updating the template.', true, false);
        }
    }

    public function deleteTemplate(WhatsappIntegration $integration, string $name): array
    {
        try {
            $response = $this->client($integration)->delete('/'.$integration->waba_id.'/message_templates', ['name' => $name]);

            return $response->successful() ? ['success' => true] : $this->responseFailure($response);
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while deleting the template.', true, false);
        }
    }

    public function listTemplates(WhatsappIntegration $integration, ?string $after = null): array
    {
        try {
            $query = ['fields' => 'id,name,language,category,status,quality_score,components,rejected_reason', 'limit' => 100];
            if ($after) {
                $query['after'] = $after;
            } $response = $this->client($integration)->get('/'.$integration->waba_id.'/message_templates', $query);
            if (! $response->successful()) {
                return $this->responseFailure($response);
            }

return ['success' => true, 'data' => $response->json('data') ?: [], 'after' => $response->json('paging.cursors.after'), 'has_more' => filled($response->json('paging.next'))];
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while synchronizing templates.', true, false);
        }
    }

    public function uploadTemplateMedia(WhatsappIntegration $integration, string $path, string $mimeType, string $fileName): array
    {
        try {
            if (blank($integration->meta_app_id)) {
                return $this->failure('missing_app_id', 'Meta App ID is required for example media uploads.', false, true);
            } $start = $this->client($integration)->post('/'.$integration->meta_app_id.'/uploads', ['file_length' => filesize($path), 'file_type' => $mimeType, 'file_name' => $fileName]);
            if (! $start->successful()) {
                return $this->responseFailure($start);
            } $session = $start->json('id');
            $upload = $this->client($integration)->withHeaders(['file_offset' => '0'])->withBody(file_get_contents($path), $mimeType)->post('/'.$session);
            if (! $upload->successful()) {
                return $this->responseFailure($upload);
            } $handle = $upload->json('h');

            return $handle ? ['success' => true, 'handle' => $handle] : $this->failure('missing_upload_handle', 'Meta did not return an example-media handle.', false, false);
        } catch (ConnectionException) {
            return $this->failure('network_error', 'Meta could not be reached while uploading example media.', true, false);
        }
    }

    protected function templatePayload(\App\Data\WhatsApp\TemplateDefinition $definition): array
    {
        $components = [];
        foreach ($definition->components as $component) {
            $type = strtoupper($component['type']);
            $item = ['type' => $type];
            if (isset($component['format'])) {
                $item['format'] = strtoupper($component['format']);
            } if (isset($component['text'])) {
                $item['text'] = $component['text'];
            } if ($type === 'HEADER' && in_array($item['format'] ?? null, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                $item['example'] = ['header_handle' => [$component['example_handle']]];
            } elseif ($type === 'HEADER' && ! empty($component['examples'])) {
                $item['example'] = ['header_text' => array_values($component['examples'])];
            } elseif ($type === 'BODY' && ! empty($component['examples'])) {
                $item['example'] = ['body_text' => [array_values($component['examples'])]];
            } if ($type === 'BUTTONS') {
                $item['buttons'] = $component['buttons'] ?? [];
            } $components[] = $item;
        }

return ['name' => $definition->name, 'language' => $definition->language, 'category' => $definition->category, 'components' => $components];
    }

    protected function get(WhatsappIntegration $integration, string $resource, array $query): array
    {
        $response = $this->client($integration)->get('/'.$resource, $query);

        return $response->successful() ? ['success' => true, 'data' => $response->json()] : $this->responseFailure($response);
    }

    protected function client(WhatsappIntegration $integration)
    {
        return Http::baseUrl(rtrim(config('meta-whatsapp.graph_base_url'), '/').'/'.config('meta-whatsapp.graph_version'))->withToken($integration->access_token)->acceptJson()->timeout(config('meta-whatsapp.timeout', 10));
    }

    protected function messageResult(Response $response): array
    {
        if (! $response->successful()) {
            return $this->responseFailure($response);
        } $id = $response->json('messages.0.id');

        return $id ? ['success' => true, 'message_id' => $id] : $this->failure('missing_message_id', 'Meta accepted the request without returning a message identifier.', false, false);
    }

    protected function responseFailure(Response $response): array
    {
        $code = (string) ($response->json('error.code') ?: 'meta_api_error');
        $subcode = (string) ($response->json('error.error_subcode') ?: '');
        $retryable = $response->serverError() || $response->status() === 429 || in_array((int) $code, [1, 2, 4, 17, 32, 613], true);
        $integrationFailure = in_array((int) $code, [10, 190, 200], true);
        $message = match ((int) $code) {
            190 => 'The Meta access token is invalid or expired.',10,200 => 'Meta denied the required permission.',100 => 'Meta rejected the message request.',default => $retryable ? 'Meta is temporarily unavailable.' : 'Meta rejected the request.'
        };

        return $this->failure($subcode ? "$code:$subcode" : $code, $message, $retryable, $integrationFailure);
    }

    protected function failure(string $code, string $message, bool $retryable, bool $integrationFailure): array
    {
        return ['success' => false, 'error_code' => $code, 'error_message' => $message, 'retryable' => $retryable, 'integration_failure' => $integrationFailure];
    }
}
