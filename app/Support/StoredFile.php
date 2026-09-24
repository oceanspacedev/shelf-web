<?php

namespace App\Support;

use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

final class StoredFile
{
    public static function uploadVisibility(): string
    {
        return config('filesystems.disks.public.driver') === 's3' ? 'private' : 'public';
    }

    public static function url(string $path): string
    {
        $disk = Storage::disk('public');

        return config('filesystems.disks.public.driver') === 's3'
            ? $disk->temporaryUrl($path, now()->addHour())
            : $disk->url($path);
    }

    /** A stable authenticated link for documents exported to spreadsheets. */
    public static function downloadUrl(string $path): string
    {
        return URL::signedRoute('stored-file.download', ['path' => $path]);
    }

    /** Run a parser against a local path, downloading remote files only temporarily. */
    public static function withLocalPath(string $diskName, string $path, callable $callback): mixed
    {
        $disk = Storage::disk($diskName);
        if ($disk instanceof LocalFilesystemAdapter) {
            return $callback($disk->path($path));
        }

        $source = $disk->readStream($path);
        if (! is_resource($source)) {
            throw new RuntimeException('File tersimpan tidak dapat dibaca.');
        }

        $temporary = tmpfile();
        if ($temporary === false) {
            fclose($source);
            throw new RuntimeException('File sementara tidak dapat dibuat.');
        }

        try {
            if (stream_copy_to_stream($source, $temporary) === false) {
                throw new RuntimeException('File tersimpan gagal disalin.');
            }

            return $callback(stream_get_meta_data($temporary)['uri']);
        } finally {
            fclose($source);
            fclose($temporary);
        }
    }

    public static function imageDataUri(string $diskName, string $path): string
    {
        $content = Storage::disk($diskName)->get($path);
        $mime = is_string($content) ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($content) : false;
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp', 'image/x-ms-bmp'], true)) {
            throw new RuntimeException('Format gambar tersimpan tidak didukung.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($content);
    }
}
