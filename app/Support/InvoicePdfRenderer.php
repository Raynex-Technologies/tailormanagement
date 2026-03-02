<?php

namespace App\Support;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

class InvoicePdfRenderer
{
    private const PAGE_WIDTH = 612;

    private const PAGE_HEIGHT = 792;

    private const LEFT_MARGIN = 40;

    private const TOP_BASELINE = 760;

    private const BOTTOM_MARGIN = 40;

    private const FONT_SIZE = 10;

    private const LINE_HEIGHT = 14;

    private const MAX_LINE_LENGTH = 92;

    /**
     * Render a simple text-first PDF for invoice downloads without external dependencies.
     *
     * @param  Collection<int, PaymentMethod>  $paymentMethods
     */
    public function render(Invoice $invoice, BusinessSetting $settings, Collection $paymentMethods): string
    {
        $paymentMethods = $paymentMethods->isNotEmpty()
            ? $paymentMethods->take(3)->values()
            : PaymentMethod::forInvoiceDocument();

        $pages = $this->buildPages($invoice, $settings, $paymentMethods);

        return $this->buildPdf($pages);
    }

    /**
     * @param  Collection<int, PaymentMethod>  $paymentMethods
     * @return array<int, array<int, string>>
     */
    private function buildPages(Invoice $invoice, BusinessSetting $settings, Collection $paymentMethods): array
    {
        $maxLines = (int) floor((self::TOP_BASELINE - self::BOTTOM_MARGIN) / self::LINE_HEIGHT);
        $pages = [[]];

        $appendLine = function (string $line = '') use (&$pages, $maxLines): void {
            $currentPageIndex = array_key_last($pages);

            if (count($pages[$currentPageIndex]) >= $maxLines) {
                $pages[] = [];
                $currentPageIndex = array_key_last($pages);
            }

            $pages[$currentPageIndex][] = $line;
        };

        $appendWrapped = function (string $text, int $width = self::MAX_LINE_LENGTH, string $prefix = '') use ($appendLine): void {
            $cleanText = trim(preg_replace('/\s+/', ' ', $text) ?? '');

            if ($cleanText === '') {
                $appendLine();

                return;
            }

            $wrapped = wordwrap($cleanText, $width, "\n", true);
            $lines = explode("\n", $wrapped);

            foreach ($lines as $index => $line) {
                $appendLine(($index === 0 ? $prefix : str_repeat(' ', strlen($prefix))) . $line);
            }
        };

        $appendKeyValue = function (string $label, string $value) use ($appendWrapped): void {
            $appendWrapped($label.': '.$value);
        };

        $appendLine(strtoupper($settings->business_name ?: config('app.name', 'Tailoring Business')));

        foreach (array_filter([
            $settings->phone,
            $settings->alternate_phone,
            $settings->email,
            $settings->tin_number ? 'TIN: '.$settings->tin_number : null,
            $settings->address,
        ]) as $detail) {
            $appendWrapped((string) $detail);
        }

        $appendLine();
        $appendLine('INVOICE '.$invoice->invoice_no);
        $appendLine(str_repeat('=', self::MAX_LINE_LENGTH));
        $appendKeyValue('Issue Date', optional($invoice->issue_date)->format('M d, Y') ?: 'N/A');
        $appendKeyValue('Due Date', optional($invoice->due_date)->format('M d, Y') ?: 'N/A');
        $appendKeyValue('Order', $invoice->order?->order_no ?? 'N/A');
        $appendKeyValue('Branch', $invoice->branch?->name ?? 'N/A');
        $appendKeyValue('Payment Status', $invoice->order?->payment_status?->label() ?? 'N/A');
        $appendKeyValue('Paid Amount', money_tzs($invoice->order?->paid_amount ?? 0));
        $appendKeyValue('Balance Due', money_tzs($invoice->order?->balance_due ?? 0));

        if ($invoice->sent_at) {
            $appendKeyValue('Sent', $invoice->sent_at->format('M d, Y H:i'));
        }

        $appendLine();
        $appendLine('BILL TO');
        $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));
        $appendWrapped($invoice->order?->customer?->name ?? 'Customer');

        foreach (array_filter([
            $invoice->order?->customer?->phone,
            $invoice->order?->customer?->email,
            $invoice->order?->customer?->address,
        ]) as $customerDetail) {
            $appendWrapped((string) $customerDetail);
        }

        if ($paymentMethods->isNotEmpty()) {
            $appendLine();
            $appendLine('PAYMENT METHODS');
            $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));

            foreach ($paymentMethods as $paymentMethod) {
                $appendWrapped($paymentMethod->name, self::MAX_LINE_LENGTH - 2, '- ');

                if ($paymentMethod->account_number) {
                    $appendWrapped('Account Number: '.$paymentMethod->account_number, self::MAX_LINE_LENGTH - 4, '  ');
                }

                if ($paymentMethod->account_holder_name) {
                    $appendWrapped('Account Holder: '.$paymentMethod->account_holder_name, self::MAX_LINE_LENGTH - 4, '  ');
                }
            }
        }

        $appendLine();
        $appendLine('ITEMS');
        $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));
        $appendLine(sprintf('%-44s %8s %16s %16s', 'Item', 'Qty', 'Unit Price', 'Line Total'));
        $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));

        foreach ($invoice->lines as $line) {
            $nameLines = explode("\n", wordwrap((string) $line->item_name, 44, "\n", true));
            $firstName = array_shift($nameLines) ?? '';

            $appendLine(sprintf(
                '%-44s %8s %16s %16s',
                $firstName,
                number_format((float) $line->qty, 2),
                money_tzs($line->unit_price),
                money_tzs($line->line_total)
            ));

            foreach ($nameLines as $nameLine) {
                $appendLine($nameLine);
            }

            if ($line->notes) {
                $appendWrapped('Notes: '.$line->notes, 80, '  ');
            }
        }

        $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));
        $appendKeyValue('Subtotal', money_tzs($invoice->subtotal));
        $appendKeyValue('Discount', '-'.money_tzs($invoice->discount, false));
        $appendKeyValue('Tax', money_tzs($invoice->tax_amount));
        $appendKeyValue('Total', money_tzs($invoice->total));

        if ($invoice->notes) {
            $appendLine();
            $appendLine('NOTES');
            $appendLine(str_repeat('-', self::MAX_LINE_LENGTH));
            $appendWrapped($invoice->notes);
        }

        $appendLine();
        $appendWrapped('Generated by '.($settings->business_name ?: config('app.name', 'Tailoring Business')));

        return $pages;
    }

    /**
     * @param  array<int, array<int, string>>  $pages
     */
    private function buildPdf(array $pages): string
    {
        $objects = [];
        $pageObjectNumbers = [];
        $contentObjectNumbers = [];
        $fontObjectNumber = 3;

        $currentObjectNumber = 4;

        foreach ($pages as $pageLines) {
            $pageObjectNumbers[] = $currentObjectNumber++;
            $contentObjectNumbers[] = $currentObjectNumber++;
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $number) => $number.' 0 R', $pageObjectNumbers)).'] /Count '.count($pageObjectNumbers).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        foreach ($pages as $index => $pageLines) {
            $pageObjectNumber = $pageObjectNumbers[$index];
            $contentObjectNumber = $contentObjectNumbers[$index];

            $contentStream = $this->buildContentStream($pageLines);

            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 '.$fontObjectNumber.' 0 R >> >> /Contents '.$contentObjectNumber.' 0 R >>';
            $objects[$contentObjectNumber] = "<< /Length ".strlen($contentStream)." >>\nstream\n".$contentStream."\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects));

        $pdf .= "xref\n0 ".($objectCount + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $objectCount; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $pdf .= "trailer\n<< /Size ".($objectCount + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function buildContentStream(array $lines): string
    {
        $content = [
            'BT',
            '/F1 '.self::FONT_SIZE.' Tf',
            self::LINE_HEIGHT.' TL',
            '1 0 0 1 '.self::LEFT_MARGIN.' '.self::TOP_BASELINE.' Tm',
        ];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $content[] = 'T*';
            }

            $content[] = '('.$this->escapePdfText($line).') Tj';
        }

        $content[] = 'ET';

        return implode("\n", $content);
    }

    private function escapePdfText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('(', '\(', $value);
        $value = str_replace(')', '\)', $value);

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }
}
