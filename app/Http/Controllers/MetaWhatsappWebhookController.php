<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappWebhook;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetaWhatsappWebhookController extends Controller
{
    public function __invoke(Request $request, string $webhookKey): Response
    {
        $integration = WhatsappIntegration::query()->where('webhook_key', $webhookKey)->firstOrFail();
        if ($request->isMethod('get')) {
            return $this->verify($request, $integration);
        }
        $raw = $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256');
        if (! $this->validSignature($raw, $signature, $integration->app_secret)) {
            return response('Invalid signature', 403);
        }
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response('Invalid payload', 422);
        }
        if (! $this->identifiersMatch($payload, $integration)) {
            return response('Integration mismatch', 403);
        }
        $key = hash('sha256', $integration->id.'|'.$raw);
        $event = WhatsappWebhookEvent::firstOrCreate(['event_key' => $key], ['whatsapp_integration_id' => $integration->id, 'event_type' => 'notification', 'payload' => $payload, 'accepted_at' => now()]);
        if ($event->wasRecentlyCreated) {
            ProcessWhatsappWebhook::dispatch($event->id);
        }

        return response('EVENT_RECEIVED', 200);
    }

    protected function verify(Request $request, WhatsappIntegration $integration): Response
    {
        if (filled($integration->webhook_verify_token) && $request->query('hub_mode') === 'subscribe' && hash_equals((string) $integration->webhook_verify_token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

return response('Verification failed', 403);
    }

    protected function validSignature(string $raw, string $signature, ?string $secret): bool
    {
        if (! filled($secret) || ! preg_match('/^sha256=([a-f0-9]{64})$/i', $signature, $m)) {
            return false;
        }

return hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($m[1]));
    }

    protected function identifiersMatch(array $payload, WhatsappIntegration $integration): bool
    {
        $seen = false;
        foreach ($payload['entry'] ?? [] as $entry) {
            if (isset($entry['id'])) {
                $seen = true;
                if ((string) $entry['id'] !== (string) $integration->waba_id) {
                    return false;
                }
            } foreach ($entry['changes'] ?? [] as $change) {
                $phone = data_get($change, 'value.metadata.phone_number_id');
                if ($phone !== null) {
                    $seen = true;
                    if ((string) $phone !== (string) $integration->phone_number_id) {
                        return false;
                    }
                }
            }
        }

return $seen;
    }
}
