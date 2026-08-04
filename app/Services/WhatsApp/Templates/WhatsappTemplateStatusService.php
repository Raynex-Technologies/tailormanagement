<?php

namespace App\Services\WhatsApp\Templates;

use App\Models\WhatsappTemplate;
use App\Models\WhatsappTemplateStatusHistory;

class WhatsappTemplateStatusService
{
    public function apply(WhatsappTemplate $template, string $status, string $source, ?string $reason = null, ?\DateTimeInterface $at = null, ?string $quality = null): WhatsappTemplate
    {
        $status = strtoupper($status);
        $previous = $template->meta_status;
        $when = $at ?: now();
        $updates = ['meta_status' => $status, 'meta_quality' => $quality ?: $template->meta_quality, 'rejection_reason' => $status === 'REJECTED' ? $reason : ($status === 'APPROVED' ? null : $template->rejection_reason)];
        if ($status === 'APPROVED') {
            $updates['approved_at'] = $when;
        }if ($status === 'REJECTED') {
            $updates['rejected_at'] = $when;
        }if (in_array($status, ['DELETED', 'PENDING_DELETION'], true)) {
            $updates['deleted_at_meta'] = $when;
        }$template->update($updates);
        if ($previous !== $status || filled($reason)) {
            WhatsappTemplateStatusHistory::create(['whatsapp_template_id' => $template->id, 'previous_status' => $previous, 'new_status' => $status, 'source' => $source, 'reason' => $reason, 'occurred_at' => $when]);
        }

return $template->fresh();
    }
}
