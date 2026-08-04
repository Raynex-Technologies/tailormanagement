<?php

namespace App\Services\WhatsApp\Templates;

use App\Contracts\WhatsAppProvider;
use App\Data\WhatsApp\TemplateDefinition;
use App\Models\WhatsappIntegration;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\DB;

class WhatsappTemplateService
{
    public function __construct(protected WhatsAppProvider $provider, protected MetaWhatsappTemplateValidator $validator, protected WhatsappTemplateStatusService $statuses) {}

    public function saveDraft(WhatsappIntegration $integration, array $data, ?WhatsappTemplate $template = null): WhatsappTemplate
    {
        $definition = TemplateDefinition::fromArray($data);
        $values = ['branch_id' => $integration->branch_id, 'whatsapp_integration_id' => $integration->id] + $definition->toArray();
        $values['components'] = $definition->components;
        $values['variable_mappings'] = $definition->variableMappings;
        $values['local_state'] = 'draft';
        $template ?: $template = new WhatsappTemplate;
        $template->fill($values);
        if ($template->exists && $template->validation_fingerprint !== $definition->fingerprint()) {
            $template->validation_fingerprint = null;
            $template->validation_result = null;
        }$template->save();

        return $template->fresh();
    }

    public function validate(WhatsappTemplate $template): TemplateValidationResult
    {
        $d = $this->definition($template);
        $result = $this->validator->validate($d);
        $template->update(['validation_result' => $result->toArray(), 'validation_fingerprint' => $d->fingerprint(), 'local_state' => $result->valid() ? 'ready_to_submit' : 'validation_failed']);

        return $result;
    }

    public function submit(WhatsappTemplate $template): array
    {
        return DB::transaction(function () use ($template) {
            $locked = WhatsappTemplate::withoutGlobalScopes()->lockForUpdate()->findOrFail($template->id);
            $d = $this->definition($locked);
            if ($locked->validation_fingerprint !== $d->fingerprint()) {
                return ['success' => false, 'error_code' => 'validation_stale', 'error_message' => 'Validate the current draft before submitting.'];
            }if ($locked->meta_template_id && $locked->submission_fingerprint === $d->fingerprint()) {
                return ['success' => true, 'template' => $locked, 'already_submitted' => true];
            }if ($locked->local_state === 'submitting') {
                return ['success' => false, 'error_code' => 'already_submitting', 'error_message' => 'This template is already being submitted.'];
            }$locked->update(['local_state' => 'submitting']);
            $result = $locked->meta_template_id ? $this->provider->updateTemplate($locked->integration, $locked->meta_template_id, $d) : $this->provider->createTemplate($locked->integration, $d);
            if (! $result['success']) {
                $locked->update(['local_state' => 'ready_to_submit']);

                return $result;
            }$fingerprint = $d->fingerprint();
            $locked->update(['meta_template_id' => data_get($result, 'data.id') ?: $locked->meta_template_id, 'category' => data_get($result, 'data.category') ?: $locked->category, 'local_state' => 'synced', 'submission_fingerprint' => $fingerprint, 'synced_fingerprint' => $fingerprint, 'submitted_at' => now()]);
            $this->statuses->apply($locked, (string) (data_get($result, 'data.status') ?: 'PENDING'), 'submission_response');

            return ['success' => true, 'template' => $locked->fresh()];
        });
    }

    public function duplicate(WhatsappTemplate $template): WhatsappTemplate
    {
        $copy = $template->replicate(['meta_template_id', 'meta_status', 'meta_quality', 'validation_result', 'validation_fingerprint', 'submission_fingerprint', 'synced_fingerprint', 'rejection_reason', 'submitted_at', 'approved_at', 'rejected_at', 'last_synced_at', 'deleted_at_meta']);
        $copy->name = substr($template->name.'_copy', 0, 512);
        $copy->local_state = 'draft';
        $copy->save();

        return $copy;
    }

    public function delete(WhatsappTemplate $template): array
    {
        if (! $template->meta_template_id) {
            $template->delete();

            return ['success' => true, 'local' => true];
        }$result = $this->provider->deleteTemplate($template->integration, $template->name);
        if ($result['success']) {
            $this->statuses->apply($template, 'DELETED', 'manual_delete');
        }

return $result;
    }

    public function definition(WhatsappTemplate $t): TemplateDefinition
    {
        return new TemplateDefinition($t->name, $t->language, $t->category, $t->components ?: [], $t->variable_mappings ?: []);
    }
}
