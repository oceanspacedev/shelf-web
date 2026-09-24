<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Throwable;

class MigrateStorageToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3 {--dry-run : List files without copying or updating records}';

    protected $description = 'Copy existing Shelf uploads and private documents to S3 without deleting local files';

    public function handle(): int
    {
        try {
            foreach (['asset_qr_label_histories' => 'file_disk', 'asset_reconciliations' => 'stored_disk'] as $table => $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException('Jalankan migrasi database terlebih dahulu: '.$table.'.'.$column);
                }
            }

            foreach (Storage::disk('legacy-public')->allFiles() as $path) {
                if (basename($path) !== '.gitignore') {
                    $this->copy('legacy-public', $path, 'private');
                }
            }

            // Earlier FILESYSTEM_DISK=local uploads may be outside app/public.
            foreach (['assets', 'asset-audit', 'asset-nbh', 'asset-documents', 'asset-sales', 'asset-services', 'asset-requests', 'document', 'documents', 'task', 'kopsurat', 'vehiclechecksheet', 'ob-checksheets'] as $directory) {
                foreach (Storage::disk('local')->allFiles($directory) as $path) {
                    if (basename($path) !== '.gitignore') {
                        $this->copy('local', $path, 'private');
                    }
                }
            }

            foreach ([
                ['asset_qr_label_histories', 'file_path', 'file_disk'],
                ['asset_reconciliations', 'stored_path', 'stored_disk'],
            ] as [$table, $pathColumn, $diskColumn]) {
                DB::table($table)->where($diskColumn, 'local')->whereNotNull($pathColumn)
                    ->orderBy('id')->each(function ($row) use ($table, $pathColumn, $diskColumn): void {
                        $this->copy('local', $row->{$pathColumn}, 'private');
                        if (! $this->option('dry-run')) {
                            DB::table($table)->where('id', $row->id)->where($diskColumn, 'local')
                                ->update([$diskColumn => 's3']);
                        }
                    });
            }

            if (Schema::hasTable('exports')) {
                DB::table('exports')->where('file_disk', 'local')->whereNotNull('completed_at')
                    ->orderBy('id')->each(function ($row): void {
                        $files = Storage::disk('local')->allFiles('filament_exports/'.$row->id);
                        foreach ($files as $path) {
                            $this->copy('local', $path, 'private');
                        }
                        if ($files !== [] && ! $this->option('dry-run')) {
                            DB::table('exports')->where('id', $row->id)->update(['file_disk' => 's3']);
                        }
                    });
            }
        } catch (Throwable $exception) {
            $this->error('Migrasi dihentikan: '.($exception instanceof FilesystemException ? class_basename($exception) : $exception->getMessage()));

            return self::FAILURE;
        }

        $this->info($this->option('dry-run') ? 'Dry run selesai; tidak ada perubahan.' : 'Migrasi selesai; file lokal tetap disimpan.');

        return self::SUCCESS;
    }

    private function copy(string $sourceName, string $path, string $visibility): void
    {
        $source = Storage::disk($sourceName);
        $target = Storage::disk('s3');
        $this->line($sourceName.': '.$path);

        if (! $source->exists($path)) {
            throw new RuntimeException('File sumber tidak ditemukan: '.$path);
        }
        if ($this->option('dry-run')) {
            return;
        }

        $sourceHash = $this->checksum($sourceName, $path);
        if ($target->exists($path)) {
            if ($sourceHash !== $this->checksum('s3', $path)) {
                throw new RuntimeException('File S3 berbeda; tidak ditimpa: '.$path);
            }
        } else {
            $stream = $source->readStream($path);
            if (! is_resource($stream)) {
                throw new RuntimeException('File sumber tidak dapat dibaca: '.$path);
            }
            try {
                if (! $target->put($path, $stream, ['visibility' => $visibility])) {
                    throw new RuntimeException('Upload S3 gagal: '.$path);
                }
            } finally {
                fclose($stream);
            }

            if ($sourceHash !== $this->checksum('s3', $path)) {
                throw new RuntimeException('Checksum hasil upload tidak cocok: '.$path);
            }
        }

        if (! $target->setVisibility($path, $visibility)) {
            throw new RuntimeException('Visibility file gagal diterapkan: '.$path);
        }
    }

    private function checksum(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('File tidak dapat dibaca: '.$path);
        }
        try {
            $hash = hash_init('sha256');
            if (hash_update_stream($hash, $stream) === false) {
                throw new RuntimeException('Checksum file gagal dibaca: '.$path);
            }

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
