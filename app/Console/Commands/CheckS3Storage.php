<?php

namespace App\Console\Commands;

use Aws\Exception\AwsException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CheckS3Storage extends Command
{
    protected $signature = 'storage:check-s3';

    protected $description = 'Verify S3 write, private ACL, read, and delete using one temporary object';

    public function handle(): int
    {
        $path = 'shelf-storage-checks/'.Str::uuid().'.txt';
        $content = 'Shelf S3 check '.Str::random(32);
        $created = false;
        $disk = Storage::disk('s3');

        try {
            if (! $disk->put($path, $content, ['visibility' => 'private'])) {
                throw new RuntimeException('Write gagal.');
            }
            $created = true;
            if (! $disk->setVisibility($path, 'private')) {
                throw new RuntimeException('ACL private gagal.');
            }
            if ($disk->get($path) !== $content) {
                throw new RuntimeException('Isi hasil read tidak sesuai.');
            }
            if (! $disk->delete($path) || $disk->exists($path)) {
                throw new RuntimeException('Delete gagal.');
            }
            $created = false;
            $this->info('S3 write/private ACL/read/delete berhasil. File uji sudah dihapus.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // AWS exception messages can contain signed URLs; report only status/code.
            $cause = $exception;
            while ($cause->getPrevious() !== null && ! $cause instanceof AwsException) {
                $cause = $cause->getPrevious();
            }
            $this->error($cause instanceof AwsException
                ? 'S3 gagal: HTTP '.$cause->getStatusCode().' / '.$cause->getAwsErrorCode()
                : 'S3 gagal: '.class_basename($cause));

            return self::FAILURE;
        } finally {
            if ($created) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    $this->warn('File uji belum terhapus: '.$path);
                }
            }
        }
    }
}
