<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappWebhook;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Http\Request;

class TwilioWhatsappWebhookController extends Controller
{
    public function __invoke(Request $request, string $webhookKey)
    {
        $integration = WhatsappIntegration::where('webhook_key', $webhookKey)->firstOrFail();
        abort_unless($integration->twilioConfigured(), 403);
        abort_unless(str_starts_with((string) $request->header('Content-Type'), 'application/x-www-form-urlencoded') && $request->query->count() === 0, 422);
        $parameters = $request->request->all();
        ksort($parameters, SORT_STRING);
        // APP_URL must be the public HTTPS origin configured in Twilio, including any base path.
        $signed = rtrim(config('app.url'), '/').route('webhooks.twilio-whatsapp', ['webhookKey' => $webhookKey], false);
        foreach ($parameters as $key => $value) {
            abort_unless(is_string($value), 422);
            $signed .= $key.$value;
        }
        $expected = base64_encode(hash_hmac('sha1', $signed, $integration->twilio_auth_token, true));
        abort_unless(hash_equals($expected, (string) $request->header('X-Twilio-Signature')), 403);
        abort_unless(($parameters['AccountSid'] ?? '') === $integration->twilio_account_sid, 403);
        $sid = $parameters['MessageSid'] ?? '';
        abort_unless((bool) preg_match('/^SM[0-9a-fA-F]{32}$/', $sid), 422);
        $status = $parameters['MessageStatus'] ?? $parameters['SmsStatus'] ?? '';
        $inbound = $status === 'received' || ($status === '' && array_key_exists('Body', $parameters));
        $sender = 'whatsapp:'.$integration->twilio_from;
        abort_unless(($parameters[$inbound ? 'To' : 'From'] ?? '') === $sender, 403);
        $value = [];
        if ($inbound) {
            abort_unless(str_starts_with($parameters['From'] ?? '', 'whatsapp:+'), 422);
            $value['messages'] = [[
                'id' => $sid, 'from' => substr($parameters['From'], 9), 'timestamp' => (string) now()->timestamp,
                'twilio_account_sid' => $integration->twilio_account_sid, 'twilio_from' => $integration->twilio_from,
                'type' => ((int) ($parameters['NumMedia'] ?? 0)) > 0 ? 'unsupported_media' : 'text',
                'text' => ['body' => $parameters['Body'] ?? ''],
            ]];
        } else {
            $state = match ($status) {
                'sent', 'delivered', 'read' => $status, 'failed', 'undelivered' => 'failed', default => null
            };
            if ($state) {
                $value['statuses'] = [['id' => $sid, 'status' => $state, 'timestamp' => (string) now()->timestamp, 'errors' => [['code' => $parameters['ErrorCode'] ?? 'unknown']]]];
            }
        }
        $event = WhatsappWebhookEvent::firstOrCreate(
            ['event_key' => hash('sha256', $integration->id.'|twilio|'.json_encode($parameters))],
            ['whatsapp_integration_id' => $integration->id, 'event_type' => 'twilio', 'payload' => ['entry' => [['changes' => [['field' => 'messages', 'value' => $value]]]]], 'accepted_at' => now()]
        );
        if ($event->wasRecentlyCreated || ! $event->processed_at) {
            ProcessWhatsappWebhook::dispatch($event->id);
        }
        $integration->update(['webhook_status' => 'verified', 'webhook_checked_at' => now(), 'webhook_error_message' => null]);

        return response('<Response/>', 200)->header('Content-Type', 'text/xml');
    }
}
