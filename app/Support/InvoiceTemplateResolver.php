<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;

class InvoiceTemplateResolver
{
    public function resolve(?BusinessSetting $settings = null): InvoiceTemplate
    {
        $settings ??= BusinessSetting::instance();

        $selectedTemplateId = $settings->invoice_template_id;
        if ($selectedTemplateId) {
            $selected = InvoiceTemplate::query()
                ->whereKey($selectedTemplateId)
                ->where('is_active', true)
                ->first();

            if ($selected) {
                return $selected;
            }
        }

        $default = InvoiceTemplate::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($default) {
            return $default;
        }

        $firstActive = InvoiceTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if ($firstActive) {
            return $firstActive;
        }

        return InvoiceTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->firstOrFail();
    }
}

