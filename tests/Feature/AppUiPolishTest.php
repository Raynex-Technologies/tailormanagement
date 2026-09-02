<?php

namespace Tests\Feature;

use App\Enums\OrderCatalogItemType;
use App\Livewire\Notifications\NotificationBell;
use App\Livewire\OrderCatalog\ItemForm;
use App\Livewire\OrderCatalog\PackageForm;
use App\Livewire\Orders\Form as OrderForm;
use App\Models\OrderCatalogItem;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AppUiPolishTest extends TestCase
{
    public function test_order_forms_keep_one_submit_area_at_the_end_and_defer_money_updates_until_blur(): void
    {
        $this->actingAsRole('admin');

        $itemHtml = Livewire::test(ItemForm::class)->html();
        $this->assertSame(1, substr_count($itemHtml, 'data-form-actions="catalog-item"'));
        $this->assertGreaterThan(strpos($itemHtml, 'Availability'), strpos($itemHtml, 'data-form-actions="catalog-item"'));
        $this->assertStringContainsString('wire:model.blur="defaultSellingPrice"', $itemHtml);
        $this->assertStringContainsString('data-money-input', $itemHtml);
        $this->assertStringContainsString('inputmode="decimal"', $itemHtml);
        $this->assertStringNotContainsString('form="catalog-item-form"', $itemHtml);

        $catalogItem = OrderCatalogItem::create([
            'name' => 'UI Polish Suit',
            'type' => OrderCatalogItemType::Garment,
            'available_all_branches' => true,
            'default_selling_price' => 125000,
            'requires_measurements' => true,
        ]);
        $packageHtml = Livewire::test(PackageForm::class)
            ->call('addCatalogItem', $catalogItem->id)
            ->html();
        $this->assertSame(1, substr_count($packageHtml, 'data-form-actions="package-template"'));
        $this->assertStringContainsString('wire:model.blur="components.0.package_unit_price"', $packageHtml);
        $this->assertStringNotContainsString('sticky bottom-3', $packageHtml);

        $orderHtml = Livewire::test(OrderForm::class)->call('addLine')->html();
        $this->assertSame(1, substr_count($orderHtml, 'data-form-actions="order"'));
        $this->assertGreaterThan(strpos($orderHtml, 'Deposit'), strpos($orderHtml, 'data-form-actions="order"'));
        $this->assertStringContainsString('wire:model.blur="discount"', $orderHtml);
        $this->assertStringContainsString('wire:model.blur="lines.0.unit_price"', $orderHtml);
        $this->assertStringContainsString('wire:model.blur="deposit_amount"', $orderHtml);
        $this->assertStringContainsString('data-order-additional-information', $orderHtml);
        $this->assertStringContainsString('data-order-expenses', $orderHtml);
        $this->assertStringContainsString('data-order-deposit', $orderHtml);
        $this->assertStringNotContainsString('form="order-form"', $orderHtml);

        $moneyScript = file_get_contents(resource_path('js/modules/money-inputs.js'));
        $this->assertStringContainsString("addEventListener('blur'", $moneyScript);
        $this->assertStringNotContainsString("addEventListener('input'", $moneyScript);
    }

    public function test_orders_workspace_uses_the_shared_green_breadcrumb_and_active_navigation_markers(): void
    {
        $this->actingAsRole('admin');

        $css = file_get_contents(resource_path('css/app.css'));
        $sidebar = file_get_contents(resource_path('views/layouts/app/sidebar.blade.php'));

        $this->assertStringContainsString('--tm-accent: #A3E635', $css);
        $this->assertStringContainsString('--tm-accent-hover: #84CC16', $css);
        $this->assertStringContainsString('var(--tailorpro-navigation-accent-start)', $sidebar);
        $this->assertStringContainsString('app-profile-accent', $sidebar);
        $this->assertStringContainsString("desktopSidebarCollapsed ? 'is-collapsed w-20' : 'w-64'", $sidebar);
        $this->assertStringContainsString('.desktop-sidebar.is-collapsed .desktop-sidebar-nav a', $sidebar);
        $this->assertStringContainsString('32deg', $css);
        $this->assertStringContainsString('148deg', $css);
        $this->assertStringContainsString('rgba(255, 255, 255, 0.017)', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.app-mobile-sidebar a\.bg-lime-400\s*\{[^}]*background:\s*var\(--tailorpro-secondary\)/s',
            $sidebar,
        );

        $response = $this->get(route('orders.create'))
            ->assertOk()
            ->assertSee('data-workspace-breadcrumb-accent="secondary"', false)
            ->assertSee('data-sidebar-active', false)
            ->assertSee('data-messages-nav-trigger', false)
            ->assertSee('data-messages-nav-menu="desktop"', false)
            ->assertSee('data-messages-nav-menu="mobile"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('overflow: visible', false);

        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'data-sidebar-active'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-messages-nav-trigger'));
        $this->assertSame(2, substr_count($response->getContent(), '>Messages</span>'));

        $storefront = file_get_contents(resource_path('views/livewire/storefront/admin/product-manager.blade.php'));
        $this->assertStringContainsString('wire:model.blur="couponDiscountValue" type="number"', $storefront);
        $this->assertStringNotContainsString('data-money-input wire:model.blur="couponDiscountValue"', $storefront);
    }

    public function test_notification_dropdown_exposes_permission_states_and_preserves_read_actions(): void
    {
        $user = $this->actingAsRole('admin');
        $firstId = (string) Str::uuid();
        $secondId = (string) Str::uuid();

        foreach ([$firstId, $secondId] as $index => $id) {
            $user->notifications()->create([
                'id' => $id,
                'type' => self::class,
                'data' => [
                    'title' => 'UI notification '.($index + 1),
                    'message' => 'Notification regression coverage',
                ],
            ]);
        }

        $component = Livewire::test(NotificationBell::class)
            ->assertSeeHtml('data-notification-panel')
            ->assertSee('Enable browser notifications')
            ->assertSee('Browser notifications enabled')
            ->assertSee('Browser notifications are blocked. Enable them from your browser/site settings.')
            ->assertSee('This browser does not support browser notifications.')
            ->assertSeeHtml('requestBrowserPermission()')
            ->assertDontSeeHtml('new Notification');

        $component->call('markAsRead', $firstId)->assertHasNoErrors();
        $this->assertNotNull($user->notifications()->findOrFail($firstId)->read_at);

        $component->call('markAllAsRead')->assertHasNoErrors();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
