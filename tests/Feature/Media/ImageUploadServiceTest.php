<?php

namespace Tests\Feature\Media;

use App\Livewire\Administration\BusinessSettings;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    public function test_successful_public_image_upload_uses_relative_uuid_path(): void
    {
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        $service = app(ImageUploadService::class);
        $result = $service->storePublic(UploadedFile::fake()->image('original-name.jpg'), 'products');

        $this->assertNotNull($result->url);
        $this->assertStringStartsWith('products/', $result->path);
        $this->assertStringContainsString('/'.now()->format('Y/m').'/', $result->path);
        $this->assertStringEndsWith('.jpg', $result->path);
        $this->assertNotSame('original-name.jpg', basename($result->path));
        $this->assertStringNotContainsString('http://', $result->path);
        $this->assertStringNotContainsString('https://', $result->path);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists($result->path);
    }

    public function test_successful_private_image_upload_uses_relative_uuid_path(): void
    {
        Storage::fake(ImageUploadService::PRIVATE_DISK);

        $service = app(ImageUploadService::class);
        $result = $service->storePrivate(UploadedFile::fake()->image('private-photo.png'), 'orders/attachments');

        $this->assertNull($result->url);
        $this->assertStringStartsWith('orders/attachments/', $result->path);
        $this->assertStringContainsString('/'.now()->format('Y/m').'/', $result->path);
        $this->assertNotSame('private-photo.png', basename($result->path));
        Storage::disk(ImageUploadService::PRIVATE_DISK)->assertExists($result->path);
    }

    public function test_invalid_image_mime_type_is_rejected(): void
    {
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        $service = app(ImageUploadService::class);

        $this->expectException(ValidationException::class);

        $service->storePublic(
            UploadedFile::fake()->create('bad.svg', 8, 'image/svg+xml'),
            'products'
        );
    }

    public function test_replacing_public_image_deletes_old_file(): void
    {
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        $service = app(ImageUploadService::class);
        $old = $service->storePublic(UploadedFile::fake()->image('old.jpg'), 'categories');

        $new = $service->replacePublic(UploadedFile::fake()->image('new.jpg'), $old->path, 'categories');

        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertMissing($old->path);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists($new->path);
    }

    public function test_deleting_public_image_removes_physical_file(): void
    {
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        $service = app(ImageUploadService::class);
        $stored = $service->storePublic(UploadedFile::fake()->image('remove.jpg'), 'categories');

        $service->deletePublic($stored->path);

        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertMissing($stored->path);
    }

    public function test_private_image_route_requires_authentication(): void
    {
        Storage::fake(ImageUploadService::PRIVATE_DISK);
        $result = app(ImageUploadService::class)->storePrivate(
            UploadedFile::fake()->image('secret.jpg'),
            'orders/attachments'
        );

        $response = $this->get(route('media.private.show', [
            'scope' => 'orders',
            'path' => $result->path,
        ]));

        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_private_image_route_requires_authorization(): void
    {
        Storage::fake(ImageUploadService::PRIVATE_DISK);
        $result = app(ImageUploadService::class)->storePrivate(
            UploadedFile::fake()->image('secret.jpg'),
            'orders/attachments'
        );

        $this->actingAsRole('customer', $this->branch);

        $response = $this->get(route('media.private.show', [
            'scope' => 'orders',
            'path' => $result->path,
        ]));

        $response->assertForbidden();
    }

    public function test_private_image_route_streams_file_for_authorized_user(): void
    {
        Storage::fake(ImageUploadService::PRIVATE_DISK);
        $result = app(ImageUploadService::class)->storePrivate(
            UploadedFile::fake()->image('secret.jpg'),
            'orders/attachments'
        );

        $this->actingAsRole('branch_manager', $this->branch);

        $response = $this->get(route('media.private.show', [
            'scope' => 'orders',
            'path' => $result->path,
        ]));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_business_settings_logo_upload_uses_public_uploads_disk(): void
    {
        Storage::fake(ImageUploadService::PUBLIC_DISK);
        $this->actingAsRole('superadmin', $this->branch);

        Livewire::test(BusinessSettings::class)
            ->set('logoUpload', UploadedFile::fake()->image('company-logo.jpg'))
            ->call('saveBusinessSettings')
            ->assertHasNoErrors();

        $settings = \App\Models\BusinessSetting::instance()->fresh();

        $this->assertNotNull($settings->logo_path);
        $this->assertStringStartsWith('business-logos/', $settings->logo_path);
        $this->assertStringNotContainsString('http://', (string) $settings->logo_path);
        $this->assertStringNotContainsString('https://', (string) $settings->logo_path);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists($settings->logo_path);
    }
}
