<?php

namespace App\Livewire\Administration;

use App\Models\BusinessSetting;
use App\Models\InvoiceTemplate;
use App\Models\PaymentMethod;
use App\Services\Media\ImageUploadService;
use App\Support\Invoices\InvoiceTemplatePreviewRenderer;
use App\Support\InvoiceTemplateResolver;
use App\Support\SystemUiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

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

    public string $ui_primary_color = SystemUiSettings::DEFAULT_PRIMARY;

    public string $ui_secondary_color_1 = SystemUiSettings::DEFAULT_SECONDARY_1;

    public string $ui_secondary_color_2 = SystemUiSettings::DEFAULT_SECONDARY_2;

    public string $email_from_name = '';

    public string $email_from_address = '';

    public string $email_reply_to = '';

    public ?int $invoice_template_id = null;

    public ?int $preview_invoice_template_id = null;

    public bool $showInvoiceTemplatePreview = false;

    public bool $tax_enabled = false;

    public string $tax_name = 'VAT';

    public ?float $tax_rate = 0;

    public bool $allow_order_dates_flexibility = false;

    public ?int $editingPaymentMethodId = null;

    public string $paymentMethodName = '';

    public string $paymentMethodCode = '';

    public string $paymentMethodAccountNumber = '';

    public string $paymentMethodAccountHolderName = '';

    public string $paymentMethodType = 'offline';

    public bool $paymentMethodEnabled = true;

    public bool $paymentMethodShowOnInvoice = false;

    public bool $paymentMethodOnline = false;

    public int $paymentMethodSortOrder = 0;

    public string $paymentMethodDescription = '';

    public string $paymentMethodConsumerKey = '';

    public string $paymentMethodConsumerSecret = '';

    public bool $showPaymentMethodModal = false;

    protected BusinessSetting $settings;

    public function mount(): void
    {
        $this->authorize('roles.manage');

        $requestedTab = (string) request()->query('tab', '');
        $availableTabs = ['business', 'orders', 'payment_methods', 'tax', 'invoice_templates'];

        if (auth()->user()->can('settings.system-ui.view')) {
            $availableTabs[] = 'system_ui';
        }

        if (in_array($requestedTab, $availableTabs, true)) {
            $this->tab = $requestedTab;
        }

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
        $this->ui_primary_color = SystemUiSettings::normalize($settings->ui_primary_color) ?? SystemUiSettings::DEFAULT_PRIMARY;
        $this->ui_secondary_color_1 = SystemUiSettings::normalize($settings->ui_secondary_color_1) ?? SystemUiSettings::DEFAULT_SECONDARY_1;
        $this->ui_secondary_color_2 = SystemUiSettings::normalize($settings->ui_secondary_color_2) ?? SystemUiSettings::DEFAULT_SECONDARY_2;

        $this->email_from_name = $settings->email_from_name ?? '';
        $this->email_from_address = $settings->email_from_address ?? '';
        $this->email_reply_to = $settings->email_reply_to ?? '';
        $this->invoice_template_id = $settings->invoice_template_id
            ?: app(InvoiceTemplateResolver::class)->find($settings)?->id;

        $this->tax_enabled = (bool) $settings->tax_enabled;
        $this->tax_name = $settings->tax_name ?? 'VAT';
        $this->tax_rate = $settings->tax_rate !== null ? (float) $settings->tax_rate : 0;
        $this->allow_order_dates_flexibility = (bool) $settings->allow_order_dates_flexibility;

    }

    public function updatedUiPrimaryColor(): void
    {
        $this->ui_primary_color = strtoupper($this->ui_primary_color);
    }

    public function updatedUiSecondaryColor1(): void
    {
        $this->ui_secondary_color_1 = strtoupper($this->ui_secondary_color_1);
    }

    public function updatedUiSecondaryColor2(): void
    {
        $this->ui_secondary_color_2 = strtoupper($this->ui_secondary_color_2);
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
            'logoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
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
            $result = app(ImageUploadService::class)->replacePublic(
                upload: $this->logoUpload,
                existingPath: $settings->logo_path,
                directory: 'business-logos'
            );

            $data['logo_path'] = $result->path;
        }

        $settings->update($data);
        Cache::forget('layout:business-logo-url');
        Cache::forget('layout:business-name');
        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Business settings updated successfully.');
    }

    public function removeLogo(): void
    {
        $this->authorize('roles.manage');

        $settings = BusinessSetting::instance();
        if ($settings->logo_path) {
            app(ImageUploadService::class)->deletePublic($settings->logo_path);
            $settings->update(['logo_path' => null]);
            Cache::forget('layout:business-logo-url');
        }

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Business logo removed.');
    }

    public function saveSystemUiSettings(): void
    {
        $this->authorize('settings.system-ui.update');

        $this->validate($this->systemUiRules());

        $settings = BusinessSetting::instance();
        $settings->update([
            'ui_primary_color' => SystemUiSettings::normalize($this->ui_primary_color),
            'ui_secondary_color_1' => SystemUiSettings::normalize($this->ui_secondary_color_1),
            'ui_secondary_color_2' => SystemUiSettings::normalize($this->ui_secondary_color_2),
        ]);

        SystemUiSettings::clearCache();

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'System UI settings updated successfully.');
    }

    public function resetSystemUiSettings(): void
    {
        $this->authorize('settings.system-ui.update');

        $settings = BusinessSetting::instance();
        $settings->update([
            'ui_primary_color' => SystemUiSettings::DEFAULT_PRIMARY,
            'ui_secondary_color_1' => SystemUiSettings::DEFAULT_SECONDARY_1,
            'ui_secondary_color_2' => SystemUiSettings::DEFAULT_SECONDARY_2,
        ]);

        SystemUiSettings::clearCache();

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'System UI settings updated successfully.');
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

        $this->activateInvoiceTemplate((int) $this->invoice_template_id);
    }

    public function openInvoiceTemplatePreview(int $templateId): void
    {
        $this->authorize('roles.manage');
        $this->resetErrorBag('invoice_template_id');

        $template = InvoiceTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $this->addError('invoice_template_id', 'The selected invoice template is not available.');

            return;
        }

        try {
            app(InvoiceTemplatePreviewRenderer::class)->assertRenderable($template);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('invoice_template_id', 'This invoice template preview is currently unavailable.');

            return;
        }

        $this->preview_invoice_template_id = $template->id;
        $this->showInvoiceTemplatePreview = true;
    }

    public function closeInvoiceTemplatePreview(): void
    {
        $this->showInvoiceTemplatePreview = false;
        $this->preview_invoice_template_id = null;
    }

    public function updatedShowInvoiceTemplatePreview(bool $show): void
    {
        if (! $show) {
            $this->preview_invoice_template_id = null;
        }
    }

    public function activateInvoiceTemplate(int $templateId, bool $closePreview = false): void
    {
        $this->authorize('roles.manage');
        $this->resetErrorBag('invoice_template_id');

        $template = InvoiceTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            $this->addError('invoice_template_id', 'The selected invoice template is not available.');

            return;
        }

        $settings = BusinessSetting::instance();

        try {
            app(InvoiceTemplatePreviewRenderer::class)->render($template, $settings);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('invoice_template_id', 'This invoice template could not be activated because it failed to render.');

            return;
        }

        $settings->update(['invoice_template_id' => $template->id]);

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        if ($closePreview) {
            $this->closeInvoiceTemplatePreview();
        }

        session()->flash('success', 'Invoice template updated successfully.');
    }

    public function saveOrderSettings(): void
    {
        $this->authorize('roles.manage');

        $this->validate([
            'allow_order_dates_flexibility' => ['boolean'],
        ]);

        $settings = BusinessSetting::instance();
        $settings->update([
            'allow_order_dates_flexibility' => (bool) $this->allow_order_dates_flexibility,
        ]);

        $this->settings = $settings->fresh();
        $this->fillFromModel($this->settings);

        session()->flash('success', 'Order settings updated successfully.');
    }

    public function savePaymentMethod(): void
    {
        $this->authorize('roles.manage');

        $isOnlineGateway = $this->isOnlineGatewayForm();
        $isPesapalGateway = $isOnlineGateway && $this->isPesapalGatewayCode();

        $validated = $this->validate([
            'paymentMethodName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_methods', 'name')->ignore($this->editingPaymentMethodId),
            ],
            'paymentMethodCode' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_methods', 'code')->ignore($this->editingPaymentMethodId),
            ],
            'paymentMethodAccountNumber' => ['nullable', 'string', 'max:100'],
            'paymentMethodAccountHolderName' => ['nullable', 'string', 'max:191'],
            'paymentMethodType' => ['required', Rule::in(['online', 'offline'])],
            'paymentMethodEnabled' => ['boolean'],
            'paymentMethodShowOnInvoice' => ['boolean'],
            'paymentMethodOnline' => ['boolean'],
            'paymentMethodSortOrder' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'paymentMethodDescription' => ['nullable', 'string', 'max:2000'],
            'paymentMethodConsumerKey' => $isPesapalGateway
                ? ['required', 'string', 'max:191']
                : ['nullable', 'string', 'max:191'],
            'paymentMethodConsumerSecret' => $isPesapalGateway
                ? ['required', 'string', 'max:191']
                : ['nullable', 'string', 'max:191'],
        ]);

        $existingMethod = $this->editingPaymentMethodId
            ? PaymentMethod::find($this->editingPaymentMethodId)
            : null;

        $settings = (array) ($existingMethod?->settings ?? []);
        if ($isPesapalGateway) {
            $settings['consumer_key'] = trim((string) $validated['paymentMethodConsumerKey']);
            $settings['consumer_secret'] = trim((string) $validated['paymentMethodConsumerSecret']);
        } else {
            unset($settings['consumer_key'], $settings['consumer_secret']);
        }

        PaymentMethod::updateOrCreate(
            ['id' => $this->editingPaymentMethodId],
            [
                'name' => $validated['paymentMethodName'],
                'code' => strtolower($validated['paymentMethodCode']),
                'account_number' => $validated['paymentMethodAccountNumber'] ?: null,
                'account_holder_name' => $validated['paymentMethodAccountHolderName'] ?: null,
                'type' => $isOnlineGateway ? 'online' : 'offline',
                'is_enabled' => (bool) $validated['paymentMethodEnabled'],
                'show_on_invoice' => (bool) $validated['paymentMethodShowOnInvoice'],
                'is_online' => $isOnlineGateway,
                'sort_order' => (int) ($validated['paymentMethodSortOrder'] ?? 0),
                'description' => $validated['paymentMethodDescription'] ?: null,
                'settings' => empty($settings) ? null : $settings,
            ]
        );

        $this->resetPaymentMethodForm();
        $this->showPaymentMethodModal = false;
        session()->flash('success', 'Payment method saved.');
    }

    public function openCreatePaymentMethodModal(): void
    {
        $this->authorize('roles.manage');

        $this->resetPaymentMethodForm();
        $this->showPaymentMethodModal = true;
    }

    public function editPaymentMethod(int $paymentMethodId): void
    {
        $this->authorize('roles.manage');

        $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
        $this->editingPaymentMethodId = $paymentMethod->id;
        $this->paymentMethodName = $paymentMethod->name;
        $this->paymentMethodCode = $paymentMethod->code ?? '';
        $this->paymentMethodAccountNumber = $paymentMethod->account_number ?? '';
        $this->paymentMethodAccountHolderName = $paymentMethod->account_holder_name ?? '';
        $this->paymentMethodType = $paymentMethod->type ?? ($paymentMethod->is_online ? 'online' : 'offline');
        $this->paymentMethodEnabled = (bool) $paymentMethod->is_enabled;
        $this->paymentMethodShowOnInvoice = (bool) $paymentMethod->show_on_invoice;
        $this->paymentMethodOnline = (bool) $paymentMethod->is_online;
        $this->paymentMethodSortOrder = (int) ($paymentMethod->sort_order ?? 0);
        $this->paymentMethodDescription = $paymentMethod->description ?? '';
        $settings = (array) ($paymentMethod->settings ?? []);
        $this->paymentMethodConsumerKey = (string) ($settings['consumer_key'] ?? '');
        $this->paymentMethodConsumerSecret = (string) ($settings['consumer_secret'] ?? '');
        $this->showPaymentMethodModal = true;
    }

    public function closePaymentMethodModal(): void
    {
        $this->showPaymentMethodModal = false;
        $this->resetPaymentMethodForm();
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
        $this->paymentMethodCode = '';
        $this->paymentMethodAccountNumber = '';
        $this->paymentMethodAccountHolderName = '';
        $this->paymentMethodType = 'offline';
        $this->paymentMethodEnabled = true;
        $this->paymentMethodShowOnInvoice = false;
        $this->paymentMethodOnline = false;
        $this->paymentMethodSortOrder = 0;
        $this->paymentMethodDescription = '';
        $this->paymentMethodConsumerKey = '';
        $this->paymentMethodConsumerSecret = '';
        $this->resetErrorBag([
            'paymentMethodName',
            'paymentMethodCode',
            'paymentMethodAccountNumber',
            'paymentMethodAccountHolderName',
            'paymentMethodType',
            'paymentMethodSortOrder',
            'paymentMethodDescription',
            'paymentMethodConsumerKey',
            'paymentMethodConsumerSecret',
        ]);
    }

    public function isOnlineGatewayForm(): bool
    {
        return $this->paymentMethodType === 'online' || $this->paymentMethodOnline;
    }

    protected function isPesapalGatewayCode(): bool
    {
        return strtolower(trim($this->paymentMethodCode)) === 'pesapal';
    }

    protected function systemUiRules(): array
    {
        $hexRule = ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'];

        return [
            'ui_primary_color' => $hexRule,
            'ui_secondary_color_1' => $hexRule,
            'ui_secondary_color_2' => $hexRule,
        ];
    }

    public function render()
    {
        $settings = BusinessSetting::instance();
        $invoiceTemplates = InvoiceTemplate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.administration.business-settings', [
            'settings' => $settings,
            'paymentMethods' => PaymentMethod::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'invoiceTemplates' => $invoiceTemplates,
            'activeInvoiceTemplate' => app(InvoiceTemplateResolver::class)->find($settings),
            'previewInvoiceTemplate' => $this->preview_invoice_template_id
                ? $invoiceTemplates->firstWhere('id', $this->preview_invoice_template_id)
                : null,
        ]);
    }
}
