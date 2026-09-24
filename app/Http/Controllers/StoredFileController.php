<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:2048']]);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($data['path']), 404);

        return $disk->download($data['path']);
    }
}
