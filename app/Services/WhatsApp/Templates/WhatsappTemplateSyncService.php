<?php

namespace App\Services\WhatsApp\Templates;

use App\Contracts\WhatsAppProvider;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;

class WhatsappTemplateSyncService
{
    public function __construct(protected WhatsAppProvider $provider, protected WhatsappTemplateStatusService $statuses) {}

    public function sync(WhatsappIntegration $integration): array
    {
        $after = null;
        $count = 0;
        do {
            $page = $this->provider->listTemplates($integration, $after);
            if (! $page['success']) {
                return $page;
            }foreach ($page['data'] as $remote) {
                $template = WhatsappTemplate::withoutGlobalScopes()->where('whatsapp_integration_id', $integration->id)->where(function ($q) use ($remote) {
                    $q->where('meta_template_id', (string) $remote['id'])->orWhere(fn ($q) => $q->whereNull('meta_template_id')->where('name', $remote['name'])->where('language', $remote['language']));
                })->first();
                if (! $template) {
                    $template = new WhatsappTemplate(['branch_id' => $integration->branch_id, 'whatsapp_integration_id' => $integration->id, 'name' => $remote['name'], 'language' => $remote['language'], 'local_state' => 'synced']);
                }$template->fill(['meta_template_id' => (string) $remote['id'], 'category' => $remote['category'] ?? 'UTILITY', 'components' => $remote['components'] ?? [], 'meta_quality' => data_get($remote, 'quality_score.score'), 'last_synced_at' => now()])->save();
                $template->synced_fingerprint = $template->definitionFingerprint();
                $template->save();
                $this->statuses->apply($template, (string) ($remote['status'] ?? 'UNKNOWN'), 'manual_sync', $remote['rejected_reason'] ?? null);
                $count++;
            }$after = $page['has_more'] ? $page['after'] : null;
        } while ($after);

        return ['success' => true, 'count' => $count];
    }
}
