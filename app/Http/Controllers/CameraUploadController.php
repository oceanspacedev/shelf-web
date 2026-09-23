<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CameraUploadController extends Controller
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @var list<string>
     */
    private const ALLOWED_FOLDERS = [
        'ob-checksheets',
        'ob-checksheets/before',
        'ob-checksheets/after',
    ];

    public function upload(Request $request): JsonResponse
    {
        $folder = $this->folder($request);
        if ($folder instanceof JsonResponse) {
            return $folder;
        }

        $request->validate([
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_data' => ['nullable', 'string'],
        ]);

        if ($request->hasFile('photo')) {
            return $this->storeUploadedFile($request, $folder);
        }

        if ($request->filled('image_data')) {
            return $this->storeBase64Image($request->string('image_data')->toString(), $folder);
        }

        return response()->json([
            'success' => false,
            'message' => 'Tidak ada gambar yang dikirim.',
        ], 400);
    }

    private function folder(Request $request): string|JsonResponse
    {
        $folder = trim((string) $request->input('folder', 'ob-checksheets'), '/');

        if (! in_array($folder, self::ALLOWED_FOLDERS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Folder tidak diizinkan.',
            ], 422);
        }

        return $folder;
    }

    private function storeUploadedFile(Request $request, string $folder): JsonResponse
    {
        $file = $request->file('photo');
        $extension = $file?->guessExtension();

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Format gambar tidak didukung.',
            ], 422);
        }

        $filename = $this->filename($extension === 'jpeg' ? 'jpg' : $extension);
        $storedPath = Storage::disk('public')->putFileAs($folder, $file, $filename);

        return $this->stored($storedPath);
    }

    private function storeBase64Image(string $imageData, string $folder): JsonResponse
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $imageData)) {
            return response()->json([
                'success' => false,
                'message' => 'Format gambar tidak didukung.',
            ], 422);
        }

        if (strlen($imageData) > (int) (self::MAX_BYTES * 4 / 3) + 64) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca data gambar.',
            ], 422);
        }

        $binary = base64_decode(substr($imageData, strpos($imageData, ',') + 1), true);

        if ($binary === false || strlen($binary) === 0 || strlen($binary) > self::MAX_BYTES) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca data gambar.',
            ], 422);
        }

        $info = @getimagesizefromstring($binary);
        $extension = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };

        if ($extension === null) {
            return response()->json([
                'success' => false,
                'message' => 'Format gambar tidak didukung.',
            ], 422);
        }

        $path = $folder.'/'.$this->filename($extension);
        Storage::disk('public')->put($path, $binary);

        return $this->stored($path);
    }

    private function filename(string $extension): string
    {
        return 'cam_'.now()->format('Ymd_His').'_'.Str::random(8).'.'.$extension;
    }

    private function stored(string $path): JsonResponse
    {
        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
