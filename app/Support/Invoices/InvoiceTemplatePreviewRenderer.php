<?php

namespace App\Support\Invoices;

use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;
use Illuminate\Support\Facades\View;
use RuntimeException;

class InvoiceTemplatePreviewRenderer
{
    public function __construct(private readonly InvoicePreviewDataFactory $dataFactory) {}

    public function assertRenderable(InvoiceTemplate $template): void
    {
        $view = trim((string) $template->blade_view);

        if (! $template->exists || ! $template->is_active || ! str_starts_with($view, 'invoices.templates.') || ! View::exists($view)) {
            throw new RuntimeException('The invoice template is not registered or cannot be rendered.');
        }
    }

    public function render(InvoiceTemplate $template, ?BusinessSetting $currentSettings = null): string
    {
        $this->assertRenderable($template);
        $data = $this->dataFactory->make($currentSettings);

        return view('invoices.print', [
            ...$data,
            'template' => $template,
            'emailMode' => true,
            'previewMode' => true,
        ])->render();
    }
}
