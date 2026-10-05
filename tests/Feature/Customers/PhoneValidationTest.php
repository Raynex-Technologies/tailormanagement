<?php

namespace Tests\Feature\Customers;

use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Requests\Storefront\CustomerAddressRequest;
use App\Livewire\Customers\Index;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Supplier;
use App\Rules\ValidPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneValidationTest extends TestCase
{
    public function test_customer_form_rejects_invalid_and_saves_country_and_number(): void
    {
        $this->actingAsRole('branch_manager');
        $page = Livewire::test(Index::class)->call('openCreateModal')->set('name', 'Phone test');
        foreach (['abc0712345678', '+255', '+999712345678'] as $invalid) {
            $page->set('phone', $invalid)->call('save')->assertHasErrors('phone');
        }
        $page->set('phone', '+254712345678')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Phone test', 'phone' => '+254712345678', 'phone_country_code' => '+254', 'phone_national_number' => '712345678', 'whatsapp_phone_country_code' => '+254']);
        $customer = Customer::where('name', 'Phone test')->firstOrFail();
        Livewire::test(Index::class)->call('openEditModal', $customer->id)->set('phone', '0712345678')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'phone' => '+255712345678', 'phone_country_code' => '+255', 'phone_national_number' => '712345678']);
    }

    public function test_normalization_happens_before_unique_validation(): void
    {
        $this->actingAsRole('branch_manager');
        Customer::create(['name' => 'Existing', 'phone' => '+255712345678']);
        Livewire::test(Index::class)->call('openCreateModal')->set('name', 'Duplicate')->set('phone', '0712345678')->call('save')->assertHasErrors(['phone' => 'unique']);
    }

    public function test_legacy_format_collision_is_rejected_after_metadata_backfill(): void
    {
        $this->actingAsRole('branch_manager');
        $customer = Customer::create(['name' => 'Legacy', 'phone' => '+255712345678']);
        DB::table('customers')->where('id', $customer->id)->update(['phone' => '0712 345 678']);
        Livewire::test(Index::class)->call('openCreateModal')->set('name', 'Duplicate')->set('phone', '+255712345678')->call('save')->assertHasErrors('phone');
    }

    public function test_model_writes_validate_and_clear_phone_parts(): void
    {
        $supplier = Supplier::factory()->create(['branch_id' => $this->branch->id, 'phone' => '+447911123456']);
        $this->assertSame('+44', $supplier->phone_country_code);
        $supplier->update(['phone' => null]);
        $this->assertNull($supplier->fresh()->phone_national_number);
        $this->expectException(ValidationException::class);
        $supplier->update(['phone' => 'bad0712345678']);
    }

    public function test_business_contact_fields_all_save_parts(): void
    {
        $setting = BusinessSetting::instance();
        $setting->update(['phone' => '0712345678', 'alternate_phone' => '+254712345678', 'storefront_contact_phone' => '+447911123456']);
        $this->assertSame('+255', $setting->phone_country_code);
        $this->assertSame('+254', $setting->alternate_phone_country_code);
        $this->assertSame('7911123456', $setting->storefront_contact_phone_national_number);
    }

    public function test_storefront_requests_use_phone_validation_even_without_browser(): void
    {
        foreach ([new CheckoutRequest, new CustomerAddressRequest] as $request) {
            $rules = $request->rules()['phone'];
            $this->assertTrue(Validator::make(['phone' => 'letters0712345678'], ['phone' => $rules])->fails());
            $this->assertFalse(Validator::make(['phone' => '+254712345678'], ['phone' => $rules])->fails());
        }
        $this->assertTrue(Validator::make(['phone' => ['0712345678']], ['phone' => [new ValidPhone]])->fails());
    }

    public function test_migration_preserves_legacy_values_and_splits_only_valid_numbers(): void
    {
        $valid = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $invalid = Customer::factory()->create(['branch_id' => $this->branch->id]);
        DB::table('customers')->where('id', $valid->id)->update(['phone' => '0712 345 678', 'phone_country_code' => null, 'phone_national_number' => null]);
        DB::table('customers')->where('id', $invalid->id)->update(['phone' => 'invalid', 'phone_country_code' => null, 'phone_national_number' => null]);
        $migration = require database_path('migrations/2026_10_03_000002_add_phone_country_codes.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('customers', ['id' => $valid->id, 'phone' => '0712 345 678', 'phone_country_code' => '+255', 'phone_national_number' => '712345678']);
        $this->assertDatabaseHas('customers', ['id' => $invalid->id, 'phone' => 'invalid', 'phone_country_code' => null]);
    }

    public function test_invalid_outbound_sms_never_reaches_provider(): void
    {
        Http::preventStrayRequests();
        $this->setBranchContext();
        \App\Models\BeemConfig::instance()->update(['sms_enabled' => true]);
        foreach (['+255', '071', 'abc0712345678'] as $phone) {
            $log = app(\App\Services\Sms\SmsService::class)->send($phone, 'Test');
            $this->assertSame(\App\Enums\SmsStatus::Failed, $log->status);
        }
        Http::assertNothingSent();
    }

    public function test_separate_country_code_and_national_number_are_authoritative_for_html_forms(): void
    {
        $request = CustomerAddressRequest::create('/', 'POST', [
            'recipient_name' => 'Test', 'country' => 'TZ', 'city' => 'Dar', 'address_line1' => 'Test address',
            'phone' => '+255712345678', 'phone_country_code' => '+44', 'phone_national_number' => '7911123456',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $this->actingAsRole('branch_manager');
        $request->validateResolved();
        $this->assertSame('+447911123456', $request->validated('phone'));
        $this->expectException(ValidationException::class);
        \App\Support\InternationalPhone::fromInput(['phone_country_code' => '+999', 'phone_national_number' => '712345678']);
    }

    public function test_unrelated_settings_save_does_not_validate_an_unsubmitted_phone_draft(): void
    {
        $this->actingAsRole('admin');
        Livewire::test(\App\Livewire\Administration\BusinessSettings::class)
            ->set('phone', 'invalid draft')->call('saveSystemUiSettings')->assertHasNoErrors();
    }

    public function test_pos_new_customer_validates_the_phone_field_and_saves_international_parts(): void
    {
        $user = $this->actingAsRole('sales');
        $user->givePermissionTo('customers.create');
        Livewire::test(\App\Livewire\Pos\PosTerminal::class)->call('openCustomerModal')
            ->set('newCustomerName', 'International walk-in')->set('newCustomerPhone', 'abc0712345678')
            ->call('createCustomer')->assertHasErrors('newCustomerPhone')
            ->set('newCustomerPhone', '+447911123456')->call('createCustomer')->assertHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'International walk-in', 'phone_country_code' => '+44', 'phone_national_number' => '7911123456']);
    }

    public function test_country_options_are_unique_and_storefront_form_keeps_split_fields_without_javascript(): void
    {
        $codes = \App\Support\InternationalPhone::callingCodes();
        $this->assertSame('+255', $codes[0]['code']);
        $this->assertCount(count(array_unique(array_column($codes, 'code'))), $codes);
        $html = \Illuminate\Support\Facades\Blade::render('<x-storefront.phone-input name="phone" value="+254712345678" />');
        $this->assertStringContainsString('name="phone_country_code"', $html);
        $this->assertStringContainsString('name="phone_national_number"', $html);
        $this->assertStringContainsString('value="712345678"', $html);
        $this->assertSame(1, substr_count($html, 'value="+1"'));
    }
}
