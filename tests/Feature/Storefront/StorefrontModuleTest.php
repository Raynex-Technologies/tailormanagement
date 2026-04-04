<?php

namespace Tests\Feature\Storefront;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Enums\Priority;
use App\Enums\StorefrontFulfillmentStatus;
use App\Livewire\Orders\Show as OrdersShow;
use App\Livewire\Storefront\Admin\CmsManager;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CmsPage;
use App\Models\CustomOrderProgressUpdate;
use App\Models\Customer;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\StorefrontOrderStatusUpdatedNotification;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BranchContext::clear();
    }

    public function test_storefront_disabled_routes_redirect_to_dashboard(): void
    {
        $this->configureStorefront([
            'storefront_enabled' => false,
        ]);

        $response = $this->get(route('storefront.home'));

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_cms_page_slug_and_excerpt_are_auto_generated_and_body_is_sanitized(): void
    {
        $this->actingAsRole('superadmin', $this->branch);

        $titleSeed = 'About TailorPro '.Str::upper(Str::random(5));
        $rawTitle = "<b>{$titleSeed}</b>";

        Livewire::test(CmsManager::class)
            ->set('tab', 'pages')
            ->set('pageTitle', $rawTitle)
            ->set('pageBody', "<p>Line One</p>\n<p>Line Two</p>")
            ->set('pagePublished', true)
            ->call('savePage')
            ->assertHasNoErrors();

        $page = CmsPage::query()->latest('id')->first();
        $this->assertNotNull($page);

        $this->assertStringStartsWith(Str::slug($titleSeed), $page->slug);
        $this->assertSame($titleSeed, $page->excerpt);
        $this->assertStringNotContainsString('<', (string) $page->body);
        $this->assertStringContainsString('Line One', (string) $page->body);
        $this->assertStringContainsString('Line Two', (string) $page->body);
    }

    public function test_guest_cart_merges_into_customer_cart_after_login(): void
    {
        $this->configureStorefront();
        $item = $this->createStorefrontItem($this->branch, 100);

        $guestAddResponse = $this->post(route('storefront.cart.add'), [
            'inventory_item_id' => $item->id,
            'quantity' => 2,
        ]);
        $guestAddResponse->assertSessionHasNoErrors();

        $guestCart = Cart::query()->whereNull('user_id')->latest('id')->first();
        $this->assertNotNull($guestCart);

        $customerUser = $this->createUserWithRole('customer', $this->branch);
        Customer::query()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $customerUser->id,
            'name' => $customerUser->name,
            'email' => $customerUser->email,
            'phone' => null,
        ]);

        $userCart = Cart::query()->create([
            'user_id' => $customerUser->id,
            'token' => Str::random(40),
            'currency' => 'TZS',
            'last_activity_at' => now(),
        ]);

        CartItem::query()->create([
            'cart_id' => $userCart->id,
            'inventory_item_id' => $item->id,
            'line_key' => $item->id.':base',
            'quantity' => 1,
            'unit_price' => 100,
            'line_total' => 100,
            'discount_total' => 0,
        ]);

        $response = $this->actingAs($customerUser)
            ->withCookie(config('storefront.cart.cookie', 'tailorpro_cart'), $guestCart->token)
            ->get(route('storefront.cart.index'));

        $response->assertOk();

        $userCart->refresh();
        $mergedLine = $userCart->items()->where('line_key', $item->id.':base')->first();

        $this->assertNotNull($mergedLine);
        $this->assertSame(3.0, (float) $mergedLine->quantity);
        $this->assertDatabaseMissing('carts', ['id' => $guestCart->id]);
    }

    public function test_checkout_requires_shipping_and_payment_selection(): void
    {
        $this->configureStorefront([
            'allow_cash_on_delivery' => true,
        ]);

        $item = $this->createStorefrontItem($this->branch, 150);
        $user = $this->createCustomerUser($this->branch);

        $cart = $this->seedUserCart($user, $item, 1, 150);

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'cash'],
            [
                'name' => 'Cash on Delivery',
                'type' => 'offline',
                'is_enabled' => true,
                'is_online' => false,
                'sort_order' => 1,
            ]
        );

        $response = $this->actingAs($user)->post(route('storefront.checkout.place'), [
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '+15551234567',
            'notes' => 'Please call before delivery.',
            'shipping_address' => [
                'country' => 'US',
                'state' => 'CA',
                'city' => 'Los Angeles',
                'address_line1' => '123 Main St',
            ],
            'billing_same_as_shipping' => true,
        ]);

        $response->assertSessionHasErrors([
            'shipping_method_code',
            'payment_method_code',
        ]);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_checkout_places_storefront_order_with_cash_on_delivery(): void
    {
        $this->configureStorefront([
            'allow_cash_on_delivery' => true,
        ]);

        $item = $this->createStorefrontItem($this->branch, 200);
        $user = $this->createCustomerUser($this->branch);
        $cart = $this->seedUserCart($user, $item, 2, 200);

        ShippingMethod::query()->create([
            'code' => 'flat_global',
            'name' => 'Flat Rate',
            'type' => 'flat_rate',
            'amount' => 15,
            'currency' => 'TZS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::query()->updateOrCreate(
            ['code' => 'cash'],
            [
                'name' => 'Cash on Delivery',
                'type' => 'offline',
                'is_enabled' => true,
                'is_online' => false,
                'sort_order' => 1,
            ]
        );

        $payload = [
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '+1555000111',
            'shipping_method_code' => 'flat_global',
            'payment_method_code' => 'cash',
            'notes' => 'Deliver in the morning.',
            'shipping_address' => [
                'country' => 'US',
                'state' => 'CA',
                'city' => 'San Francisco',
                'address_line1' => '500 Howard St',
                'address_line2' => 'Suite 42',
                'postal_code' => '94105',
            ],
            'billing_same_as_shipping' => true,
        ];

        $response = $this->actingAs($user)->post(route('storefront.checkout.place'), $payload);

        $order = Order::query()->where('customer_id', $user->customerProfile->id)->latest('id')->first();
        $this->assertNotNull($order);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('storefront.checkout.confirmation', $order, false));

        $this->assertSame('storefront', $order->order_type);
        $this->assertSame('flat_global', $order->shipping_method_code);
        $this->assertSame(StorefrontFulfillmentStatus::Pending, $order->fulfillment_status);
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_checkout_redirects_to_pesapal_for_online_payment(): void
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $this->configureStorefront([
            'allow_cash_on_delivery' => false,
        ]);

        config([
            'services.pesapal.mode' => 'sandbox',
            'services.pesapal.notification_id' => null,
        ]);

        $item = $this->createStorefrontItem($this->branch, 120);
        $user = $this->createCustomerUser($this->branch);
        $cart = $this->seedUserCart($user, $item, 1, 120);

        ShippingMethod::query()->create([
            'code' => 'flat_global',
            'name' => 'Flat Rate',
            'type' => 'flat_rate',
            'amount' => 10,
            'currency' => 'TZS',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $paymentMethod = PaymentMethod::query()->updateOrCreate(
            ['code' => 'pesapal'],
            [
                'name' => 'Pesapal',
                'type' => 'online',
                'is_enabled' => true,
                'is_online' => true,
                'sort_order' => 1,
                'settings' => [
                    'mode' => 'sandbox',
                    'consumer_key' => 'method_consumer_key',
                    'consumer_secret' => 'method_consumer_secret',
                ],
            ]
        );

        Http::fake([
            '*Auth/RequestToken' => Http::response([
                'token' => 'token-123',
            ], 200),
            '*URLSetup/RegisterIPN' => Http::response([
                'ipn_id' => 'ipn-123',
            ], 200),
            '*Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'TRACK-REDIRECT-001',
                'redirect_url' => 'https://payments.example.test/checkout/TRACK-REDIRECT-001',
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('storefront.checkout.place'), [
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '+1555000222',
            'shipping_method_code' => 'flat_global',
            'payment_method_code' => 'pesapal',
            'shipping_address' => [
                'country' => 'US',
                'state' => 'CA',
                'city' => 'Los Angeles',
                'address_line1' => '100 Main Street',
                'postal_code' => '90001',
            ],
            'billing_same_as_shipping' => true,
        ]);

        $response->assertRedirect('https://payments.example.test/checkout/TRACK-REDIRECT-001');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => Order::query()->latest('id')->value('id'),
            'gateway' => 'pesapal',
            'gateway_reference' => 'TRACK-REDIRECT-001',
            'checkout_url' => 'https://payments.example.test/checkout/TRACK-REDIRECT-001',
        ]);

        $this->assertSame('ipn-123', data_get($paymentMethod->fresh()->settings, 'notification_id'));
        $this->assertSame(0, $cart->fresh()->items()->count());
    }

    public function test_pesapal_callback_is_idempotent_for_verified_transactions(): void
    {
        $this->configureStorefront();

        $manager = $this->createUserWithRole('branch_manager', $this->branch);
        $customerUser = $this->createCustomerUser($this->branch);
        $item = $this->createStorefrontItem($this->branch, 300);

        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'order_type' => 'storefront',
            'order_source' => 'online',
            'customer_id' => $customerUser->customerProfile->id,
            'status' => OrderStatus::New,
            'fulfillment_status' => StorefrontFulfillmentStatus::AwaitingPayment,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 300,
            'discount' => 0,
            'total' => 300,
            'currency' => 'TZS',
            'tax_total' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 300,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        $order->lines()->create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'sku' => $item->sku,
            'qty' => 1,
            'unit_price' => 300,
            'line_total' => 300,
        ]);

        $paymentMethod = PaymentMethod::query()->updateOrCreate(
            ['code' => 'pesapal'],
            [
                'name' => 'Pesapal',
                'type' => 'online',
                'is_enabled' => true,
                'is_online' => true,
                'sort_order' => 1,
                'settings' => [
                    'consumer_key' => 'test_consumer_key',
                    'consumer_secret' => 'test_consumer_secret',
                ],
            ]
        );

        $transaction = PaymentTransaction::query()->create([
            'branch_id' => $this->branch->id,
            'order_id' => $order->id,
            'customer_id' => $customerUser->customerProfile->id,
            'payment_method_id' => $paymentMethod->id,
            'gateway' => 'pesapal',
            'merchant_reference' => 'TEST-TXN-1234',
            'gateway_reference' => 'TRACK-1234',
            'amount' => 300,
            'currency' => 'TZS',
            'status' => PaymentTransactionStatus::Pending,
            'purpose' => PaymentTransactionPurpose::StorefrontOrder,
            'initiated_at' => now(),
            'created_by' => $manager->id,
        ]);

        Http::fake([
            '*Auth/RequestToken' => Http::response(['token' => 'token-123'], 200),
            '*Transactions/GetTransactionStatus*' => Http::response([
                'payment_status_description' => 'COMPLETED',
            ], 200),
        ]);

        $this->get(route('storefront.payments.callback', [
            'merchant_reference' => $transaction->merchant_reference,
            'OrderTrackingId' => $transaction->gateway_reference,
        ]));

        $this->get(route('storefront.payments.callback', [
            'merchant_reference' => $transaction->merchant_reference,
            'OrderTrackingId' => $transaction->gateway_reference,
        ]));

        $transaction->refresh();
        $order->refresh();

        $this->assertSame(PaymentTransactionStatus::Verified, $transaction->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(StorefrontFulfillmentStatus::Paid, $order->fulfillment_status);
        $this->assertSame(1, $order->payments()->where('payment_transaction_id', $transaction->id)->count());
    }

    public function test_customer_can_only_view_their_own_storefront_order(): void
    {
        $this->configureStorefront();

        $owner = $this->createCustomerUser($this->branch);
        $other = $this->createCustomerUser($this->branch);

        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'order_type' => 'storefront',
            'order_source' => 'online',
            'customer_id' => $owner->customerProfile->id,
            'status' => OrderStatus::New,
            'fulfillment_status' => StorefrontFulfillmentStatus::Pending,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 120,
            'discount' => 0,
            'total' => 120,
            'currency' => 'TZS',
            'tax_total' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 120,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => null,
        ]);

        $response = $this->actingAs($other)->get(route('storefront.account.orders.show', $order));

        $response->assertForbidden();
    }

    public function test_custom_order_progress_page_only_shows_customer_visible_updates(): void
    {
        $this->configureStorefront([
            'custom_order_portal_enabled' => true,
        ]);

        $customerUser = $this->createCustomerUser($this->branch);

        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'order_type' => 'tailoring',
            'order_source' => 'manual',
            'customer_id' => $customerUser->customerProfile->id,
            'status' => OrderStatus::InProgress,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 800,
            'discount' => 0,
            'total' => 800,
            'currency' => 'TZS',
            'tax_total' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 800,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => null,
        ]);

        CustomOrderProgressUpdate::query()->create([
            'order_id' => $order->id,
            'stage_key' => 'in_production',
            'stage_label' => 'In Production',
            'note' => 'Tailor has started stitching.',
            'is_customer_visible' => true,
        ]);

        CustomOrderProgressUpdate::query()->create([
            'order_id' => $order->id,
            'stage_key' => 'internal_qc',
            'stage_label' => 'Internal QC',
            'note' => 'Internal-only note.',
            'is_customer_visible' => false,
        ]);

        $response = $this->actingAs($customerUser)->get(route('storefront.account.custom-orders.show', $order));

        $response->assertOk();
        $response->assertSee('In Production');
        $response->assertDontSee('Internal QC');
    }

    public function test_branch_manager_can_update_storefront_fulfillment_from_order_show(): void
    {
        $this->configureStorefront();

        $manager = $this->actingAsRole('branch_manager', $this->branch);
        $customerUser = $this->createCustomerUser($this->branch);

        $order = Order::query()->create([
            'branch_id' => $this->branch->id,
            'order_type' => 'storefront',
            'order_source' => 'online',
            'customer_id' => $customerUser->customerProfile->id,
            'status' => OrderStatus::New,
            'fulfillment_status' => StorefrontFulfillmentStatus::Pending,
            'order_date' => now()->toDateString(),
            'priority' => Priority::Normal,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'currency' => 'TZS',
            'tax_total' => 0,
            'shipping_total' => 0,
            'discount_total' => 0,
            'grand_total' => 500,
            'payment_status' => PaymentStatus::Unpaid,
            'created_by' => $manager->id,
        ]);

        Notification::fake();

        Livewire::test(OrdersShow::class, ['order' => $order])
            ->set('newFulfillmentStatus', StorefrontFulfillmentStatus::Shipped->value)
            ->set('fulfillmentNote', 'Order packed and handed over to carrier.')
            ->call('updateStorefrontFulfillmentStatus')
            ->assertHasNoErrors();

        $order->refresh();

        $this->assertSame(StorefrontFulfillmentStatus::Shipped, $order->fulfillment_status);
        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'status' => 'shipped',
        ]);
        Notification::assertSentTo($customerUser, StorefrontOrderStatusUpdatedNotification::class);
    }

    protected function configureStorefront(array $overrides = []): void
    {
        BusinessSetting::instance()->update(array_merge([
            'storefront_enabled' => true,
            'storefront_catalog_mode' => false,
            'custom_order_portal_enabled' => true,
            'guest_checkout_enabled' => true,
            'allow_cash_on_delivery' => false,
            'storefront_currency' => 'TZS',
            'storefront_default_branch_id' => $this->branch->id,
        ], $overrides));
    }

    protected function createStorefrontItem(Branch $branch, float $price): InventoryItem
    {
        $category = InventoryCategory::factory()->create([
            'branch_id' => $branch->id,
            'storefront_is_visible' => true,
        ]);

        $item = InventoryItem::factory()->create([
            'branch_id' => $branch->id,
            'inventory_category_id' => $category->id,
            'status' => 'active',
            'storefront_is_visible' => true,
            'track_stock' => true,
            'allow_backorders' => false,
            'default_sell_price' => $price,
            'default_buy_price' => max(1, $price - 10),
            'is_active' => true,
        ]);

        $item->stock()->updateOrCreate(
            ['inventory_item_id' => $item->id],
            [
                'branch_id' => $branch->id,
                'qty_on_hand' => 100,
                'qty_reserved' => 0,
            ]
        );

        return $item->fresh();
    }

    protected function createCustomerUser(Branch $branch): User
    {
        $user = $this->createUserWithRole('customer', $branch);

        Customer::query()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+1555'.str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
        ]);

        return $user->fresh();
    }

    protected function seedUserCart(User $user, InventoryItem $item, float $quantity, float $unitPrice): Cart
    {
        $cart = Cart::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'token' => Str::random(40),
                'currency' => 'TZS',
                'last_activity_at' => now(),
            ]
        );

        CartItem::query()->updateOrCreate(
            [
                'cart_id' => $cart->id,
                'line_key' => $item->id.':base',
            ],
            [
                'inventory_item_id' => $item->id,
                'inventory_item_variant_id' => null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'compare_at_price' => null,
                'discount_total' => 0,
                'line_total' => $quantity * $unitPrice,
            ]
        );

        return $cart->fresh(['items']);
    }
}
