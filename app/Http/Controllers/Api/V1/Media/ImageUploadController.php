<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $directory = public_path('images/uploads');
        File::ensureDirectoryExists($directory);

        $image = $validated['image'];
        $filename = Str::uuid().'.'.strtolower($image->extension());
        $image->move($directory, $filename);

        return ApiResponse::success([
            'image_url' => '/images/uploads/'.$filename,
        ], 'Gambar berhasil diunggah', 201);
    }
}
