<?php

$appUrl = rtrim(env('APP_URL', 'http://localhost'), '/');

$publicUploadsRoot = env('PUBLIC_UPLOADS_ROOT', env('STOREFRONT_UPLOADS_ROOT', public_path('uploads/images')));
if (! is_string($publicUploadsRoot) || trim($publicUploadsRoot) === '') {
    $publicUploadsRoot = public_path('uploads/images');
}
$publicUploadsRoot = rtrim($publicUploadsRoot, '/\\');

$publicUploadsUrl = env('PUBLIC_UPLOADS_URL', env('STOREFRONT_UPLOADS_URL', $appUrl.'/uploads/images'));
if (! is_string($publicUploadsUrl) || trim($publicUploadsUrl) === '') {
    $publicUploadsUrl = $appUrl.'/uploads/images';
}
$publicUploadsUrl = rtrim($publicUploadsUrl, '/');

$storefrontUploadsRoot = env('STOREFRONT_UPLOADS_ROOT', public_path('uploads'));
if (! is_string($storefrontUploadsRoot) || trim($storefrontUploadsRoot) === '') {
    $storefrontUploadsRoot = public_path('uploads');
}
$storefrontUploadsRoot = rtrim($storefrontUploadsRoot, '/\\');

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => $appUrl.'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'public_uploads' => [
            'driver' => 'local',
            'root' => $publicUploadsRoot,
            'url' => $publicUploadsUrl,
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'private_uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        'storefront_categories' => [
            'driver' => 'local',
            'root' => $publicUploadsRoot,
            'url' => $publicUploadsUrl,
            'visibility' => 'public',
            'legacy_root' => public_path('uploads/categories'),
            'throw' => false,
            'report' => false,
        ],

        'storefront_uploads' => [
            'driver' => 'local',
            'root' => $publicUploadsRoot,
            'url' => $publicUploadsUrl,
            'visibility' => 'public',
            'legacy_root' => $storefrontUploadsRoot,
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
