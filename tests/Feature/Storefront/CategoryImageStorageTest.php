<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\Admin\CategoryManager;
use App\Models\InventoryCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryImageStorageTest extends TestCase
{
    public function test_category_image_upload_stores_file_on_storefront_categories_disk(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);

        $this->assertSame(
            public_path('uploads/categories'),
            config('filesystems.disks.storefront_categories.root')
        );

        Storage::fake(InventoryCategory::STOREFRONT_IMAGE_DISK);

        $upload = UploadedFile::fake()->image('category.jpg');

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
        $this->assertStringNotContainsString('/storage/', (string) $category->storefront_image_path);
        $this->assertStringNotContainsString('storefront/categories/', (string) $category->storefront_image_path);
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists($category->storefront_image_path);
    }

    public function test_category_image_url_accessor_uses_storefront_categories_url(): void
    {
        config([
            'filesystems.disks.storefront_categories.url' => 'https://rajcruzbrand.shop/uploads/categories',
        ]);

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => '/storage/storefront/categories/lookbook.jpg',
        ])->fresh();

        $this->assertSame('lookbook.jpg', $category->storefront_image_path);
        $this->assertSame('https://rajcruzbrand.shop/uploads/categories/lookbook.jpg', $category->image_url);
    }

    public function test_replacing_category_image_deletes_old_file(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        Storage::fake(InventoryCategory::STOREFRONT_IMAGE_DISK);

        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->put('old-image.jpg', 'old');

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => 'old-image.jpg',
        ]);

        $newUpload = UploadedFile::fake()->image('new-image.jpg');

        Livewire::test(CategoryManager::class)
            ->call('openEditModal', $category->id)
            ->set('imageUpload', $newUpload)
            ->call('save')
            ->assertHasNoErrors();

        $category->refresh();

        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertMissing('old-image.jpg');
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists($category->storefront_image_path);
        $this->assertNotSame('old-image.jpg', $category->storefront_image_path);
    }

    public function test_deleting_category_deletes_image_file(): void
    {
        $this->actingAsRole('branch_manager', $this->branch);
        Storage::fake(InventoryCategory::STOREFRONT_IMAGE_DISK);

        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->put('delete-me.jpg', 'content');

        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => 'delete-me.jpg',
        ]);

        Livewire::test(CategoryManager::class)
            ->call('confirmDelete', $category->id)
            ->call('delete')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('inventory_categories', [
            'id' => $category->id,
        ]);
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertMissing('delete-me.jpg');
    }

    public function test_storefront_category_image_migration_command_normalizes_legacy_values_and_copies_files(): void
    {
        Storage::fake('public');
        Storage::fake(InventoryCategory::STOREFRONT_IMAGE_DISK);

        Storage::disk('public')->put('storefront/categories/plain.jpg', 'plain');
        Storage::disk('public')->put('storefront/categories/prefixed.jpg', 'prefixed');
        Storage::disk('public')->put('storefront/categories/with-storage-prefix.jpg', 'storage-prefix');
        Storage::disk('public')->put('storefront/categories/from-url.jpg', 'from-url');

        $plain = $this->createCategoryWithRawImagePath('plain.jpg');
        $prefixed = $this->createCategoryWithRawImagePath('storefront/categories/prefixed.jpg');
        $storagePrefixed = $this->createCategoryWithRawImagePath('/storage/storefront/categories/with-storage-prefix.jpg');
        $fullUrl = $this->createCategoryWithRawImagePath('https://rajcruzbrand.shop/storage/storefront/categories/from-url.jpg');
        $missing = $this->createCategoryWithRawImagePath('/storage/storefront/categories/missing.jpg');

        $this->artisan('storefront:migrate-category-images')
            ->assertExitCode(0);

        $this->assertSame('plain.jpg', $plain->fresh()->storefront_image_path);
        $this->assertSame('prefixed.jpg', $prefixed->fresh()->storefront_image_path);
        $this->assertSame('with-storage-prefix.jpg', $storagePrefixed->fresh()->storefront_image_path);
        $this->assertSame('from-url.jpg', $fullUrl->fresh()->storefront_image_path);
        $this->assertSame('missing.jpg', $missing->fresh()->storefront_image_path);

        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists('plain.jpg');
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists('prefixed.jpg');
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists('with-storage-prefix.jpg');
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertExists('from-url.jpg');
        Storage::disk(InventoryCategory::STOREFRONT_IMAGE_DISK)->assertMissing('missing.jpg');
    }

    protected function createCategoryWithRawImagePath(string $rawPath): InventoryCategory
    {
        $category = InventoryCategory::factory()->create([
            'branch_id' => $this->branch->id,
            'storefront_image_path' => null,
        ]);

        DB::table('inventory_categories')
            ->where('id', $category->id)
            ->update(['storefront_image_path' => $rawPath]);

        return $category;
    }
}
