<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;
use App\Support\Invoices\InvoiceTemplatePreviewRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

class InvoiceTemplatePreviewController extends Controller
{
    public function __invoke(int $template, InvoiceTemplatePreviewRenderer $renderer): Response
    {
        Gate::authorize('roles.manage');

        $invoiceTemplate = InvoiceTemplate::query()
            ->whereKey($template)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            return response($renderer->render($invoiceTemplate, BusinessSetting::instance()))
                ->header('Content-Type', 'text/html; charset=UTF-8')
                ->header('Cache-Control', 'private, no-store');
        } catch (Throwable $exception) {
            report($exception);

            return response()
                ->view('invoices.preview-unavailable', status: 422)
                ->header('Cache-Control', 'private, no-store');
        }
    }
}
