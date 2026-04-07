# Image Uploads (Shared Hosting)

## Architecture
- All image uploads are centralized in `App\Services\Media\ImageUploadService`.
- Public images are stored on the `public_uploads` disk.
- Private images are stored on the `private_uploads` disk.
- Database columns store relative paths only, never full URLs.

## Public vs Private
- Public images (directly web-accessible): product images, category images, storefront branding images, business logo, installment package item images.
- Private images (never direct URL-accessible): any sensitive/internal image that must be delivered through `media.private.show` with auth + policy checks.

## Storage Locations
- Public disk root: `public/uploads/images`
- Public URL base: `${APP_URL}/uploads/images`
- Private disk root: `storage/app/private`

## URL Generation
- Public image URLs: `ImageUploadService::publicUrl($relativePath)` (or `StorefrontMedia::url()` compatibility helper).
- Private image URLs: `ImageUploadService::privateUrl($relativePath, $scope)` and serve through `GET /media/private/{scope}/{path}`.

## Adding New Upload Points
1. Validate strictly: `image`, `mimes:jpg,jpeg,png,webp`, `mimetypes:image/jpeg,image/png,image/webp`, plus size/dimensions as needed.
2. Store with:
   - Public: `ImageUploadService::storePublic($upload, 'feature/folder')`
   - Private: `ImageUploadService::storePrivate($upload, 'feature/folder')`
3. For replacements, use:
   - Public: `replacePublic($upload, $existingPath, 'feature/folder')`
   - Private: `replacePrivate($upload, $existingPath, 'feature/folder')`
4. Persist only `$result->path` in the database.
5. Delete with `deletePublic()` / `deletePrivate()`.

## Shared Hosting Notes
- This image system does not depend on `php artisan storage:link`.
- Public image serving works from `public_html/uploads/images` style hosting layouts.
- Public upload hardening is applied via `public/uploads/images/.htaccess`.

## Deployment Notes (public_html)
- Ensure the domain document root points to Laravel `public`.
- Ensure `public/uploads/images` exists and remains writable by PHP.
- Ensure `storage/app/private` exists and remains writable by PHP.
- Run one-time normalization after rollout:
  - `php artisan media:normalize-image-paths`
