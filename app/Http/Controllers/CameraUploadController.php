<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CameraUploadController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'folder' => ['nullable', 'string'],
        ]);

        $folder = trim($request->input('folder', 'ob-checksheets'), '/');
        // Ensure folder stays within allowed ob-checksheets directory
        if (! str_starts_with($folder, 'ob-checksheets')) {
            $folder = 'ob-checksheets';
        }

        $filename = 'cam_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.jpg';
        $path = $folder . '/' . $filename;

        // Case 1: Uploaded file (e.g. from capture="environment")
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'cam_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $extension;
            $path = $folder . '/' . $filename;

            $storedPath = Storage::disk('public')->putFileAs($folder, $file, $filename);

            return response()->json([
                'success' => true,
                'path' => $storedPath,
                'url' => Storage::disk('public')->url($storedPath),
            ]);
        }

        // Case 2: Base64 data URL from live canvas snapshot
        if ($request->filled('image_data')) {
            $imageData = $request->input('image_data');

            // Format: data:image/jpeg;base64,....
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, jpeg

                if (! in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                    return response()->json(['success' => false, 'message' => 'Format gambar tidak didukung.'], 422);
                }

                $imageData = base64_decode($imageData);

                if ($imageData === false) {
                    return response()->json(['success' => false, 'message' => 'Gagal membaca data gambar.'], 422);
                }

                Storage::disk('public')->put($path, $imageData);

                return response()->json([
                    'success' => true,
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Tidak ada gambar yang dikirim.',
        ], 400);
    }
}
