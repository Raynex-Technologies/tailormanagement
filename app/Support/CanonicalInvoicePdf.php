<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Scopes\BranchScope;
use Barryvdh\DomPDF\Facade\Pdf;

class CanonicalInvoicePdf
{
    public function __construct(
        protected InvoiceTemplateResolver $templateResolver,
    ) {}

    public function invoice(Invoice $invoice): Invoice
    {
        return Invoice::query()
            ->withoutGlobalScope(BranchScope::class)
            ->with([
                'order' => fn ($query) => $query
                    ->with(['customer', 'branch', 'packageInstances'])
                    ->withSum('payments', 'amount'),
                'lines.orderLine',
                'branch',
            ])
            ->findOrFail($invoice->getKey());
    }

    public function render(Invoice $invoice, ?BusinessSetting $settings = null): string
    {
        $settings ??= BusinessSetting::instance();
        $invoice = $this->invoice($invoice);
        $template = $this->templateResolver->resolve($settings);

        $pdf = Pdf::loadView('invoices.print', [
            'invoice' => $invoice,
            'settings' => $settings,
            'template' => $template,
            'paymentMethods' => PaymentMethod::forInvoiceDocument(),
            'downloadMode' => true,
        ])->setPaper('a4');

        $pdf->getDomPDF()->add_info('InvoiceTemplate', $template->slug);

        return $pdf->output(['compress' => 0]);
    }

    public function filename(Invoice $invoice): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->invoice_no) ?: 'invoice';

        return $number.'.pdf';
    }
}
