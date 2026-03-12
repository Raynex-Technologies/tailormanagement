<?php

namespace App\Livewire\Administration;

use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;
use App\Models\PaymentMethod;
use App\Support\InvoiceTemplateResolver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app.sidebar')]
#[Title('Business Settings')]
class BusinessSettings extends Component
{
    use WithFileUploads;

    public string $tab = 'business';

    public string $business_name = '';
    public string $phone = '';
    public string $alternate_phone = '';
    public string $email = '';
    public string $tin_number = '';
    public string $address = '';
    public ?string $logo_path = null;
    public $logoUpload = null;

    public string $email_from_name = '';
    public string $email_from_address = '';
    public string $email_reply_to = '';
    public ?int $invoice_template_id = null;

    public bool $tax_enabled = false;
    public string $tax_name = 'VAT';
    public ?float $tax_rate = 0;

    public ?int $editingPaymentMethodId = null;
    public string $paymentMethodName = '';
    public string $paymentMethodAccountNumber = '';
    public string $paymentMethodAccountHolderName = '';

    protected BusinessSetting $settings;

    public function mount(): void
    {
        $this->authorize('roles.manage');
        $this->settings = BusinessSetting::instance();
        $this->fillFromModel($this->settings);
    }

    protected function fillFromModel(BusinessSetting $settings): void
    {
        $this->business_name = $settings->business_name ?? config('app.name', 'Tailoring Business');
        $this->phone = $settings->phone ?? '';
        $this->alternate_phone = $settings->alternate_phone ?? '';
        $this->email = $settings->email ?? '';
        $this->tin_number = $settings->tin_number ?? '';
        $this->address = $settings->address ?? '';
        $this->logo_path = $settings->logo_path;
        $this->logoUpload = null;

        $this->email_from_name = $settings->email_from_name ?? '';
        $this->email_from_address = $settings->email_from_address ?? '';
        $this->email_reply_to = $settings->email_reply_to ?? '';
        $this->invoice_template_id = $settings->invoice_template_id
            ?: app(InvoiceTemplateResolver::class)->resolve($settings)->id;

        $this->tax_enabled = (bool) $settings->tax_enabled;
        $this->tax_name = $settings->tax_name ?? 'VAT';
        $this->tax_rate = $settings->tax_rate !== null ? (float) $settings->tax_rate : 0;
    }

    public function saveBusinessSettings(): void
    {
        $this->authorize('roles.manage');

        $this->validate([
            'business_name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'tin_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'logoUpload' => ['nullable', 'image', 'max:2048'],
        ]);

        $settings = BusinessSetting::instance();
        $data = [
            'business_name' => $this->business_name,
            'phone' => $this->phone ?: null,
            'alternate_phone' => $this->alternate_phone ?: null,
            'email' => $this->email ?: null,
            'tin_number' => $this->tin_number ?: null,
            'address' => $this->address ?: null,
        ];

        if ($this->logoUpload) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $this->logoUpload->store('business-logos', 'public');
        }

        $settings->update($data);
        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Business settings updated successfully.');
    }

    public function removeLogo(): void
    {
        $this->authorize('roles.manage');

        $settings = BusinessSetting::instance();
        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Business logo removed.');
    }

    public function saveEmailSettings(): void
    {
        $this->authorize('roles.manage');

        $this->validate([
            'email_from_name' => ['nullable', 'string', 'max:191'],
            'email_from_address' => ['nullable', 'email', 'max:191'],
            'email_reply_to' => ['nullable', 'email', 'max:191'],
        ]);

        $settings = BusinessSetting::instance();
        $settings->update([
            'email_from_name' => $this->email_from_name ?: null,
            'email_from_address' => $this->email_from_address ?: null,
            'email_reply_to' => $this->email_reply_to ?: null,
        ]);

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Email settings updated successfully.');
    }

    public function saveInvoiceTemplateSettings(): void
    {
        $this->authorize('roles.manage');

        $this->validate([
            'invoice_template_id' => [
                'required',
                'integer',
                Rule::exists('invoice_templates', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $settings = BusinessSetting::instance();
        $settings->update([
            'invoice_template_id' => $this->invoice_template_id,
        ]);

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Invoice template updated successfully.');
    }

    public function savePaymentMethod(): void
    {
        $this->authorize('roles.manage');

        $validated = $this->validate([
            'paymentMethodName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_methods', 'name')->ignore($this->editingPaymentMethodId),
            ],
            'paymentMethodAccountNumber' => ['nullable', 'string', 'max:100'],
            'paymentMethodAccountHolderName' => ['nullable', 'string', 'max:191'],
        ]);

        PaymentMethod::updateOrCreate(
            ['id' => $this->editingPaymentMethodId],
            [
                'name' => $validated['paymentMethodName'],
                'account_number' => $validated['paymentMethodAccountNumber'] ?: null,
                'account_holder_name' => $validated['paymentMethodAccountHolderName'] ?: null,
            ]
        );

        $this->resetPaymentMethodForm();
        session()->flash('success', 'Payment method saved.');
    }

    public function editPaymentMethod(int $paymentMethodId): void
    {
        $this->authorize('roles.manage');

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        $this->editingPaymentMethodId = $paymentMethod->id;
        $this->paymentMethodName = $paymentMethod->name;
        $this->paymentMethodAccountNumber = $paymentMethod->account_number ?? '';
        $this->paymentMethodAccountHolderName = $paymentMethod->account_holder_name ?? '';
    }

    public function deletePaymentMethod(int $paymentMethodId): void
    {
        $this->authorize('roles.manage');

        $paymentMethod = PaymentMethod::withCount('orderPayments')->findOrFail($paymentMethodId);

        if ($paymentMethod->id === 1) {
            $this->addError('paymentMethodName', 'Default payment method cannot be deleted.');

            return;
        }

        if ($paymentMethod->order_payments_count > 0) {
            $this->addError('paymentMethodName', 'This payment method is already used and cannot be deleted.');

            return;
        }

        $paymentMethod->delete();

        if ($this->editingPaymentMethodId === $paymentMethodId) {
            $this->resetPaymentMethodForm();
        }

        session()->flash('success', 'Payment method deleted.');
    }

    public function resetPaymentMethodForm(): void
    {
        $this->editingPaymentMethodId = null;
        $this->paymentMethodName = '';
        $this->paymentMethodAccountNumber = '';
        $this->paymentMethodAccountHolderName = '';
        $this->resetErrorBag([
            'paymentMethodName',
            'paymentMethodAccountNumber',
            'paymentMethodAccountHolderName',
        ]);
    }

    public function render()
    {
        $settings = BusinessSetting::instance();

        return view('livewire.administration.business-settings', [
            'settings' => $settings,
            'paymentMethods' => PaymentMethod::query()
                ->orderByRaw('CASE WHEN id = 1 THEN 0 ELSE 1 END')
                ->orderBy('name')
                ->get(),
            'invoiceTemplates' => InvoiceTemplate::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'activeInvoiceTemplate' => app(InvoiceTemplateResolver::class)->resolve($settings),
        ]);
    }
}
