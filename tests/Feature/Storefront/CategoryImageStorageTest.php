<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\Admin\CategoryManager;
use App\Models\InventoryCategory;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryImageStorageTest extends TestCase
{
    public function test_category_image_upload_stores_file_on_public_uploads_disk(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        $this->assertSame(
            public_path('uploads/images'),
            config('filesystems.disks.public_uploads.root')
        );

        Storage::fake(ImageUploadService::PUBLIC_DISK);

        $upload = UploadedFile::fake()->image('category.jpg', 800, 800);

        Livewire::test(CategoryManager::class)
            ->call('openCreateModal')
            ->set('name', 'Wedding Collection')
            ->set('slug', '')
            ->set('imageUpload', $upload)
            ->call('save')
            ->assertHasNoErrors();

        $category = InventoryCategory::query()
            ->where('branch_id', $this->branch->id)
            ->where('name', 'Wedding Collection')
            ->first();

        $this->assertNotNull($category);
        $this->assertNotNull($category->storefront_image_path);
        $this->assertStringStartsWith('categories/', (string) $category->storefront_image_path);
        $this->assertStringNotContainsString('/storage/', (string) $category->storefront_image_path);
        $this->assertStringNotContainsString('http://', (string) $category->storefront_image_path);
        $this->assertStringNotContainsString('https://', (string) $category->storefront_image_path);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists($category->storefront_image_path);
    }

    public function test_category_image_url_accessor_uses_public_uploads_url(): void
    {
        config([
            'filesystems.disks.public_uploads.url' => 'https://rajcruzbrand.shop/uploads/images',
        ]);

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => '/storage/uploads/categories/lookbook.jpg',
        ])->fresh();

        $this->assertSame('categories/lookbook.jpg', $category->storefront_image_path);
        $this->assertSame('https://rajcruzbrand.shop/uploads/images/categories/lookbook.jpg', $category->image_url);
    }

    public function test_replacing_category_image_deletes_old_file(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        Storage::disk(ImageUploadService::PUBLIC_DISK)->put('categories/old-image.jpg', 'old');

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => 'categories/old-image.jpg',
        ]);

        $newUpload = UploadedFile::fake()->image('new-image.jpg', 800, 800);

        Livewire::test(CategoryManager::class)
            ->call('openEditModal', $category->id)
            ->set('imageUpload', $newUpload)
            ->call('save')
            ->assertHasNoErrors();

        $category->refresh();

        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertMissing('categories/old-image.jpg');
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists($category->storefront_image_path);
        $this->assertNotSame('categories/old-image.jpg', $category->storefront_image_path);
    }

    public function test_deleting_category_deletes_image_file(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        Storage::disk(ImageUploadService::PUBLIC_DISK)->put('categories/delete-me.jpg', 'content');

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => 'categories/delete-me.jpg',
        ]);

        Livewire::test(CategoryManager::class)
            ->call('confirmDelete', $category->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('inventory_categories', [
            'id' => $category->id,
        ]);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertMissing('categories/delete-me.jpg');
    }

    public function test_media_normalization_command_normalizes_category_path_and_migrates_file(): void
    {
        Storage::fake('public');
        Storage::fake(ImageUploadService::PUBLIC_DISK);

        Storage::disk('public')->put('storefront/categories/from-url.jpg', 'from-url');

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => null,
        ]);

        DB::table('inventory_categories')
            ->where('id', $category->id)
            ->update(['storefront_image_path' => 'https://rajcruzbrand.shop/storage/storefront/categories/from-url.jpg']);

        $this->artisan('media:normalize-image-paths')
            ->assertExitCode(0);

        $this->assertSame('categories/from-url.jpg', $category->fresh()->storefront_image_path);
        Storage::disk(ImageUploadService::PUBLIC_DISK)->assertExists('categories/from-url.jpg');
    }
}
