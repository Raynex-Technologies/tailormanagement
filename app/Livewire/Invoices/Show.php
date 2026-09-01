<?php

namespace App\Livewire\Invoices;

use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\OrderLine;
use App\Services\Mail\CustomerEmailDeliveryService;
use App\Services\Mail\MailFailureSanitizer;
use App\Support\CanonicalInvoicePdf;
use App\Support\Livewire\NormalizesMoneyInputs;
use App\Support\Orders\OrderPackagePresenter;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    use NormalizesMoneyInputs;

    public Invoice $invoice;

    public bool $isEditing = false;

    public string $issue_date = '';

    public ?string $due_date = null;

    public string $notes = '';

    public string|float|null $discount = 0;

    public array $lines = [];

    public float $subtotal = 0;

    public float $total = 0;

    public string $emailTo = '';

    public bool $showSendInvoiceModal = false;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $this->loadInvoiceRelations($invoice);
        $this->fillFormFromInvoice();
    }

    public function getTitle(): string
    {
        return "Invoice {$this->invoice->invoice_no}";
    }

    protected function fillFormFromInvoice(): void
    {
        $this->issue_date = optional($this->invoice->issue_date)->format('Y-m-d') ?? now()->toDateString();
        $this->due_date = optional($this->invoice->due_date)->format('Y-m-d');
        $this->notes = $this->invoice->notes ?? '';
        $this->discount = (float) ($this->invoice->discount ?? 0);
        $this->emailTo = $this->invoice->order?->customer?->email ?? '';

        $this->lines = [];

        $presentation = app(OrderPackagePresenter::class)->forInvoice($this->invoice);

        foreach ($this->invoice->lines as $line) {
            $context = $presentation['line_context'][$line->id] ?? [];
            $this->lines[] = [
                'id' => $line->id,
                'order_line_id' => $line->order_line_id,
                'item_name' => $line->item_name,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'notes' => $line->notes ?? '',
                'is_package_linked' => filled($context['package'] ?? null),
                'package_context' => [
                    'starts_package' => (bool) ($context['starts_package'] ?? false),
                    'starts_ordinary' => (bool) ($context['starts_ordinary'] ?? false),
                    'package' => filled($context['package'] ?? null)
                        ? [
                            'id' => $context['package']['id'],
                            'name' => $context['package']['name'],
                            'configured_total' => $context['package']['configured_total'],
                        ]
                        : null,
                ],
            ];
        }

        if (empty($this->lines)) {
            $this->addLine();
        }

        $this->calculateTotals();
    }

    public function startEditing(): void
    {
        $this->authorize('update', $this->invoice);
        $this->isEditing = true;
    }

    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->invoice->refresh();
        $this->invoice = $this->loadInvoiceRelations($this->invoice);
        $this->fillFormFromInvoice();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'id' => null,
            'order_line_id' => null,
            'item_name' => '',
            'qty' => 1,
            'unit_price' => 0,
            'line_total' => 0,
            'notes' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        if ($this->lines[$index]['is_package_linked'] ?? false) {
            $this->addError('lines', __('Package items must be changed from the Order edit screen.'));

            return;
        }

        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->calculateTotals();
    }

    public function updatedLines(): void
    {
        $this->normalizeMoneyInputs();
        $this->calculateTotals();
    }

    public function updatedDiscount(): void
    {
        $this->normalizeMoneyInputProperty('discount');
        $this->calculateTotals();
    }

    protected function calculateTotals(): void
    {
        $this->subtotal = 0;

        foreach ($this->lines as $index => $line) {
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineTotal = $qty * $unitPrice;

            $this->lines[$index]['line_total'] = $lineTotal;
            $this->subtotal += $lineTotal;
        }

        $this->total = $this->subtotal - ((float) ($this->discount ?? 0));
    }

    public function save(): void
    {
        $this->normalizeMoneyInputs();
        $this->authorize('update', $this->invoice);

        $this->validate([
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_name' => ['required', 'string', 'max:191'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $hasValidLine = collect($this->lines)->some(fn ($line) => ! empty($line['item_name']));
        if (! $hasValidLine) {
            $this->addError('lines', 'At least one invoice line is required.');

            return;
        }

        $this->calculateTotals();
        if ($this->total < 0) {
            $this->addError('discount', 'Discount cannot exceed subtotal.');

            return;
        }

        if (! $this->packageLinesRemainCanonical()) {
            return;
        }

        $paidAmount = (float) ($this->invoice->order?->payments()->sum('amount') ?? 0);
        if ($this->total < $paidAmount) {
            $this->addError('total', __('The order total cannot be reduced below :amount because that amount has already been paid.', [
                'amount' => money_currency($paidAmount, config('app.currency', 'TZS')),
            ]));

            return;
        }

        DB::transaction(function () {
            $order = $this->invoice->order()->with('lines')->firstOrFail();

            $order->update([
                'due_date' => $this->due_date ?: null,
                'discount' => $this->discount ?? 0,
            ]);

            $keptOrderLineIds = [];

            foreach ($this->lines as $lineData) {
                if (empty($lineData['item_name'])) {
                    continue;
                }

                $attributes = [
                    'item_name' => $lineData['item_name'],
                    'qty' => $lineData['qty'],
                    'unit_price' => $lineData['unit_price'],
                    'line_total' => $lineData['line_total'],
                    'notes' => $lineData['notes'] ?: null,
                ];

                $orderLine = null;
                if (! empty($lineData['order_line_id'])) {
                    $orderLine = $order->lines->firstWhere('id', (int) $lineData['order_line_id']);
                }

                if ($orderLine) {
                    $orderLine->update($attributes);
                } else {
                    $orderLine = OrderLine::create([
                        'order_id' => $order->id,
                        ...$attributes,
                    ]);
                }

                $keptOrderLineIds[] = $orderLine->id;
            }

            if (! empty($keptOrderLineIds)) {
                $order->lines()->whereNotIn('id', $keptOrderLineIds)->delete();
            } else {
                $order->lines()->delete();
            }

            $order->recalculateTotals();
            $order->update(['payment_status' => $order->computed_payment_status]);

            $invoice = Invoice::syncFromOrder($order->fresh(['lines']), auth()->id());
            $invoice->update([
                'issue_date' => $this->issue_date,
                'due_date' => $this->due_date ?: null,
                'notes' => $this->notes ?: null,
                'updated_by' => auth()->id(),
            ]);

            $this->invoice = $this->loadInvoiceRelations($invoice->fresh());
        });

        $this->isEditing = false;
        $this->fillFormFromInvoice();
        session()->flash('success', 'Invoice updated successfully.');
    }

    protected function packageLinesRemainCanonical(): bool
    {
        $order = $this->invoice->order()->with('lines')->firstOrFail();
        $packageLines = $order->lines->whereNotNull('order_package_instance_id');
        $submitted = collect($this->lines)->keyBy(fn ($line) => (int) ($line['order_line_id'] ?? 0));

        foreach ($packageLines as $packageLine) {
            $line = $submitted->get($packageLine->id);
            if (! $line
                || (string) $line['item_name'] !== (string) $packageLine->item_name
                || ! BigDecimal::of((string) $line['qty'])->isEqualTo((string) $packageLine->qty)
                || ! BigDecimal::of((string) $line['unit_price'])->isEqualTo((string) $packageLine->unit_price)
                || (string) ($line['notes'] ?? '') !== (string) ($packageLine->notes ?? '')) {
                $this->addError('lines', __('Package items are read-only on invoices. Edit the Order to customize or remove a package.'));

                return false;
            }
        }

        return true;
    }

    public function openSendInvoiceModal(): void
    {
        $this->authorize('send', $this->invoice);
        $this->resetErrorBag('emailTo');
        $this->showSendInvoiceModal = true;
    }

    public function closeSendInvoiceModal(): void
    {
        $this->showSendInvoiceModal = false;
        $this->resetErrorBag('emailTo');
    }

    public function sendByEmail(CustomerEmailDeliveryService $deliveryService): void
    {
        $this->authorize('send', $this->invoice);

        $this->validate([
            'emailTo' => ['required', 'email', 'max:191'],
        ]);

        try {
            $invoice = $this->loadInvoiceRelations($this->invoice->fresh());
            $deliveryService->sendInvoiceManually($invoice, $this->emailTo, auth()->user());

            $this->invoice = $this->loadInvoiceRelations($invoice->fresh());
            $this->showSendInvoiceModal = false;
            session()->flash('success', "Invoice sent to {$this->emailTo}.");
        } catch (\RuntimeException $exception) {
            $this->addError('emailTo', $exception->getMessage());
        } catch (\Throwable $exception) {
            $this->addError('emailTo', app(MailFailureSanitizer::class)->message($exception));
        }
    }

    public function render()
    {
        $settings = BusinessSetting::instance();
        $order = $this->invoice->order;
        $financialSummary = $order?->financialSummary() ?? [
            'total' => (float) $this->invoice->total,
            'paid' => 0.0,
            'balance' => (float) $this->invoice->total,
            'status' => null,
        ];

        return view('livewire.invoices.show', [
            'settings' => $settings,
            'emailSendingEnabled' => (bool) $settings->email_sending_enabled,
            'invoiceAttachmentFilename' => app(CanonicalInvoicePdf::class)->filename($this->invoice),
            'financialSummary' => $financialSummary,
            'isOverdue' => $this->invoice->due_date
                && $this->invoice->due_date->isPast()
                && $financialSummary['balance'] > 0,
        ])->title($this->getTitle());
    }

    protected function loadInvoiceRelations(Invoice $invoice): Invoice
    {
        return $invoice->load([
            'order' => fn ($orderQuery) => $orderQuery
                ->with(['customer', 'packageInstances'])
                ->withSum('payments', 'amount'),
            'lines.orderLine',
            'branch',
        ]);
    }
}
