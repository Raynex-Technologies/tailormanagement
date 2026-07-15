<?php

namespace App\Providers;

use App\Http\Middleware\SetBranchContext;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\CapitalAllocation;
use App\Models\Conversation;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GarmentCategory;
use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use App\Models\InstallmentPlan;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Invoice;
use App\Models\Message;
use App\Models\OfficeAvailabilityWindow;
use App\Models\OfficeUnavailabilityPeriod;
use App\Models\OnlineBooking;
use App\Models\Order;
use App\Models\OrderStockRequest;
use App\Models\Package;
use App\Models\PosSale;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\AvailabilityPolicy;
use App\Policies\BranchPolicy;
use App\Policies\CapitalAllocationPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\DeliveryNotePolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\GarmentOptionPolicy;
use App\Policies\InstallmentPlanPolicy;
use App\Policies\InventoryCategoryPolicy;
use App\Policies\InventoryItemPolicy;
use App\Policies\InventoryUnitPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MessagePolicy;
use App\Policies\OnlineBookingPolicy;
use App\Policies\OrderPolicy;
use App\Policies\OrderStockRequestPolicy;
use App\Policies\PackagePolicy;
use App\Policies\PosSalePolicy;
use App\Policies\PrivateImagePolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseRequestPolicy;
use App\Policies\UserPolicy;
use App\Support\BranchContext;
use App\Support\PrivateImage;
use App\Support\SystemUiSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Branch::class => BranchPolicy::class,
        Appointment::class => AppointmentPolicy::class,
        AppointmentType::class => AvailabilityPolicy::class,
        CapitalAllocation::class => CapitalAllocationPolicy::class,
        Conversation::class => ConversationPolicy::class,
        DeliveryNote::class => DeliveryNotePolicy::class,
        Expense::class => ExpensePolicy::class,
        ExpenseCategory::class => ExpenseCategoryPolicy::class,
        GarmentCategory::class => GarmentOptionPolicy::class,
        GarmentOptionGroup::class => GarmentOptionPolicy::class,
        GarmentOption::class => GarmentOptionPolicy::class,
        InventoryCategory::class => InventoryCategoryPolicy::class,
        InventoryItem::class => InventoryItemPolicy::class,
        InventoryUnit::class => InventoryUnitPolicy::class,
        Invoice::class => InvoicePolicy::class,
        InstallmentPlan::class => InstallmentPlanPolicy::class,
        Message::class => MessagePolicy::class,
        OfficeAvailabilityWindow::class => AvailabilityPolicy::class,
        OfficeUnavailabilityPeriod::class => AvailabilityPolicy::class,
        OnlineBooking::class => OnlineBookingPolicy::class,
        Order::class => OrderPolicy::class,
        Package::class => PackagePolicy::class,
        OrderStockRequest::class => OrderStockRequestPolicy::class,
        PosSale::class => PosSalePolicy::class,
        PurchaseOrder::class => PurchaseOrderPolicy::class,
        PurchaseRequest::class => PurchaseRequestPolicy::class,
        PrivateImage::class => PrivateImagePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix MySQL key length issue for older MySQL versions
        Schema::defaultStringLength(191);

        Livewire::addPersistentMiddleware([
            SetBranchContext::class,
        ]);

        $this->configureDefaults();
        $this->configureRuntimeMailSettings();
        $this->configureViewComposers();
        $this->configureGates();
        $this->configureRateLimiting();
        $this->registerPolicies();
        $this->registerEventListeners();
    }

    /**
     * Register event listeners.
     *
     * NOTE: Listeners in app/Listeners are auto-discovered by Laravel 11+
     * via their handle() type hints. Do NOT manually register them here
     * or they will fire twice.
     */
    protected function registerEventListeners(): void
    {
        // All listeners are auto-discovered:
        // - SendOrderCreatedSms      -> OrderCreated
        // - SendOrderStatusSms       -> OrderStatusChanged
        // - SendOrderDueDateChangedSms -> OrderDueDateChanged
        // - SendOrderPaymentSms      -> OrderPaymentRecorded
        // - CreateInAppNotificationForPayment -> OrderPaymentRecorded
        // - ClearBranchContextOnLogout -> Logout
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureRuntimeMailSettings(): void
    {
        try {
            if (! Schema::hasTable('business_settings')) {
                return;
            }

            $settings = BusinessSetting::query()->first();
            if (! $settings) {
                return;
            }

            if (filled($settings->mail_mailer)) {
                config()->set('mail.default', $settings->mail_mailer);
            }

            if (filled($settings->mail_host)) {
                config()->set('mail.mailers.smtp.host', $settings->mail_host);
            }

            if (filled($settings->mail_port)) {
                config()->set('mail.mailers.smtp.port', (int) $settings->mail_port);
            }

            if (filled($settings->mail_username)) {
                config()->set('mail.mailers.smtp.username', $settings->mail_username);
            }

            if (filled($settings->mail_password)) {
                config()->set('mail.mailers.smtp.password', $settings->mail_password);
            }

            if (filled($settings->mail_timeout)) {
                config()->set('mail.mailers.smtp.timeout', (int) $settings->mail_timeout);
            }

            $scheme = $settings->mail_encryption;
            if ($scheme === 'none') {
                $scheme = null;
            }

            if ($settings->mail_encryption !== null) {
                config()->set('mail.mailers.smtp.scheme', $scheme);
                config()->set('mail.mailers.smtp.encryption', $scheme);
            }

            if (filled($settings->email_from_address)) {
                config()->set('mail.from.address', $settings->email_from_address);
            }

            if (filled($settings->email_from_name)) {
                config()->set('mail.from.name', $settings->email_from_name);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function configureViewComposers(): void
    {
        View::composer('layouts.app.sidebar', function ($view): void {
            $businessName = Cache::remember('layout:business-name', now()->addMinutes(10), function () {
                return BusinessSetting::query()->value('business_name') ?: 'Tailex';
            });

            $businessLogoUrl = Cache::remember('layout:business-logo-url', now()->addMinutes(10), function () {
                return BusinessSetting::query()->first()?->logo_url;
            });

            $urgentOpenOrdersCount = 0;
            $authUser = auth()->user();

            if ($authUser && $authUser->can('orders.view')) {
                $branchKey = BranchContext::isInitialized()
                    ? (string) (BranchContext::id() ?? 'all')
                    : 'uninitialized';
                $roleKey = $authUser->hasRole('tailor') ? 'tailor' : 'standard';

                $cacheKey = "layout:urgent-open-orders:{$authUser->id}:{$branchKey}:{$roleKey}";

                $urgentOpenOrdersCount = Cache::remember($cacheKey, now()->addSeconds(30), function () use ($authUser) {
                    $urgentOrdersQuery = Order::query()->urgentOpen();

                    if ($authUser->hasRole('tailor')) {
                        $urgentOrdersQuery->forTailor($authUser->id);
                    }

                    return $urgentOrdersQuery->count();
                });
            }

            $view->with([
                'businessName' => $businessName,
                'businessLogoUrl' => $businessLogoUrl,
                'urgentOpenOrdersCount' => $urgentOpenOrdersCount,
                'systemUiCssVariables' => SystemUiSettings::cssVariables(),
            ]);
        });
    }

    /**
     * Configure authorization gates.
     * Spatie laravel-permission automatically registers gates for permissions.
     * Superadmin role gets implicit access to all permissions.
     */
    protected function configureGates(): void
    {
        // Superadmin bypass: grant all abilities to superadmin role
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Configure rate limiting for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Web requests - 120 per minute
        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // API requests - 60 per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // SMS sending - 10 per minute per user (prevent abuse)
        RateLimiter::for('sms', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Message sending - 30 per minute per user (prevent spam)
        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Login attempts - 5 per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Heavy operations (reports, exports) - 10 per minute
        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Storefront payment callbacks/IPNs - stricter source throttling.
        RateLimiter::for('storefront-payments', function (Request $request) {
            $merchantReference = (string) ($request->query('OrderMerchantReference')
                ?: $request->query('merchant_reference')
                ?: $request->input('order_merchant_reference')
                ?: $request->input('merchant_reference')
                ?: 'none');

            return [
                Limit::perMinute(120)->by($request->ip()),
                Limit::perMinute(30)->by($request->ip().'|'.$merchantReference),
            ];
        });
    }
}
