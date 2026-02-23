<?php

namespace App\Livewire\Invoices;

use App\Mail\InvoiceMailable;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\OrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app.sidebar')]
class Show extends Component
{
    public Invoice $invoice;

    public bool $isEditing = false;

    public string $issue_date = '';

    public ?string $due_date = null;

    public string $notes = '';

    public ?float $discount = 0;

    public array $lines = [];

    public float $subtotal = 0;

    public float $total = 0;

    public string $emailTo = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice->load(['order.customer', 'lines', 'branch']);
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

        foreach ($this->invoice->lines as $line) {
            $this->lines[] = [
                'id' => $line->id,
                'order_line_id' => $line->order_line_id,
                'item_name' => $line->item_name,
                'qty' => (float) $line->qty,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'notes' => $line->notes ?? '',
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
        $this->invoice->refresh()->load(['order.customer', 'lines', 'branch']);
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
        if (count($this->lines) <= 1) {
            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->calculateTotals();
    }

    public function updatedLines(): void
    {
        $this->calculateTotals();
    }

    public function updatedDiscount(): void
    {
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

            $this->invoice = $invoice->fresh(['order.customer', 'lines', 'branch']);
        });

        $this->isEditing = false;
        $this->fillFormFromInvoice();
        session()->flash('success', 'Invoice updated successfully.');
    }

    public function sendByEmail(): void
    {
        $this->authorize('send', $this->invoice);

        $this->validate([
            'emailTo' => ['required', 'email', 'max:191'],
        ]);

        try {
            $invoice = $this->invoice->fresh(['order.customer', 'lines', 'branch']);
            Mail::to($this->emailTo)->send(new InvoiceMailable($invoice, BusinessSetting::instance()));

            $invoice->update([
                'sent_at' => now(),
                'sent_to_email' => $this->emailTo,
                'updated_by' => auth()->id(),
            ]);

            $this->invoice = $invoice->fresh(['order.customer', 'lines', 'branch']);
            session()->flash('success', "Invoice sent to {$this->emailTo}.");
        } catch (\Throwable $e) {
            $this->addError('emailTo', 'Failed to send invoice email: '.$e->getMessage());
        }
    }

    public function render()
    {
        $settings = BusinessSetting::instance();

        return view('livewire.invoices.show', [
            'settings' => $settings,
        ])->title($this->getTitle());
    }
}
