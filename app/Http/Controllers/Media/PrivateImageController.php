<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Services\Media\ImageUploadService;
use App\Support\PrivateImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PrivateImageController extends Controller
{
    public function __invoke(Request $request, string $scope, string $path, ImageUploadService $imageUploadService)
    {
        $privateImage = new PrivateImage(path: $path, scope: $scope);
        Gate::authorize('view', $privateImage);

        $response = $imageUploadService->streamPrivate($path);
        if ($response === null) {
            abort(404);
        }

        return $response;
    }
}
