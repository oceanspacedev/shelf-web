<?php

namespace Tests\Feature;

use App\Models\AssetQrLabelHistory;
use App\Models\User;
use App\Services\AssetQrLabelHistoryService;
use App\Services\VehicleAssetAuditWorkbookParser;
use App\Support\StoredFile;
use Filament\Actions\ExportAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\AssetQrLabelTestCase;

class S3StorageTest extends AssetQrLabelTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');
        Storage::fake('public');
        Storage::fake('legacy-public');
    }

    public function test_new_qr_pdf_is_private_on_s3_and_download_uses_its_recorded_disk(): void
    {
        $service = app(AssetQrLabelHistoryService::class);
        $history = $service->record(null, AssetQrLabelHistory::ACTION_DOWNLOAD_PDF, $this->createAssets(1));

        $this->assertSame('s3', $history->file_disk);
        $this->assertSame('private', Storage::disk('s3')->getVisibility($history->file_path));
        Storage::disk('local')->assertMissing($history->file_path);
        config(['filesystems.default' => 'local']);
        $this->assertTrue($history->fresh()->hasStoredFile());
        $response = $service->download($history);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertSame(Storage::disk('s3')->get($history->file_path), $content);
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_existing_local_qr_pdf_remains_available_after_switching_to_s3(): void
    {
        Storage::disk('local')->put('asset-qr-labels/old.pdf', 'old pdf');
        $history = AssetQrLabelHistory::create([
            'action' => AssetQrLabelHistory::ACTION_PRINT,
            'file_path' => 'asset-qr-labels/old.pdf',
        ])->fresh();

        $this->assertSame('local', $history->file_disk);
        $this->assertTrue($history->hasStoredFile());
        $this->assertSame(200, app(AssetQrLabelHistoryService::class)->download($history)->getStatusCode());
    }

    public function test_camera_upload_returns_a_url_from_the_upload_disk(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('admin.camera-upload'), [
            'folder' => 'ob-checksheets/before',
            'photo' => UploadedFile::fake()->image('camera.jpg'),
        ])->assertOk();

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('url'));
        $this->assertSame('public', FileUpload::make('image')->getDiskName());
        $this->assertSame('s3', ExportAction::make()->getFileDisk());
        $this->assertSame('s3', ExportBulkAction::make()->getFileDisk());
    }

    public function test_remote_workbook_can_be_parsed_and_its_temporary_copy_is_deleted(): void
    {
        $fixture = file_get_contents(base_path('tests/fixtures/vehicle-audit-sample.xlsx'));
        $this->remoteDisk()->put('audit.xlsx', $fixture);
        $temporaryPath = null;

        $rows = StoredFile::withLocalPath('s3', 'audit.xlsx', function (string $path) use (&$temporaryPath, $fixture): array {
            $temporaryPath = $path;
            $this->assertSame(hash('sha256', $fixture), hash_file('sha256', $path));

            return app(VehicleAssetAuditWorkbookParser::class)->parse($path);
        });

        $this->assertNotEmpty($rows);
        $this->assertFileDoesNotExist($temporaryPath);
        Storage::disk('s3')->assertExists('audit.xlsx');
    }

    public function test_remote_temporary_copy_is_deleted_even_when_the_parser_fails(): void
    {
        $this->remoteDisk()->put('audit.xlsx', 'invalid workbook');
        $temporaryPath = null;
        try {
            StoredFile::withLocalPath('s3', 'audit.xlsx', function (string $path) use (&$temporaryPath): void {
                $temporaryPath = $path;
                throw new RuntimeException('Invalid workbook');
            });
            $this->fail('Expected parser failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invalid workbook', $exception->getMessage());
            $this->assertFileDoesNotExist($temporaryPath);
        }
    }

    public function test_pdf_images_are_embedded_from_storage_without_a_local_path(): void
    {
        $image = UploadedFile::fake()->image('letterhead.png');
        Storage::disk('public')->put('letterhead.png', $image->getContent());
        $uri = StoredFile::imageDataUri('public', 'letterhead.png');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertSame($image->getContent(), base64_decode(explode(',', $uri, 2)[1]));
    }

    public function test_migration_copies_files_updates_disks_and_preserves_local_sources(): void
    {
        Storage::disk('legacy-public')->put('images/laptop.jpg', 'photo');
        Storage::disk('local')->put('assets/legacy-root.jpg', 'legacy upload');
        Storage::disk('local')->put('livewire-tmp/pending.txt', 'temporary upload');
        Storage::disk('local')->put('asset-qr-labels/old.pdf', 'pdf');
        Storage::disk('local')->put('asset-reconciliations/old.xlsx', 'workbook');
        $history = AssetQrLabelHistory::create([
            'action' => AssetQrLabelHistory::ACTION_PRINT,
            'file_path' => 'asset-qr-labels/old.pdf',
        ]);
        $auditId = DB::table('asset_reconciliations')->insertGetId(['stored_path' => 'asset-reconciliations/old.xlsx']);

        $this->artisan('storage:migrate-to-s3')->assertSuccessful();
        $this->assertSame('photo', Storage::disk('s3')->get('images/laptop.jpg'));
        $this->assertSame('legacy upload', Storage::disk('s3')->get('assets/legacy-root.jpg'));
        Storage::disk('s3')->assertMissing('livewire-tmp/pending.txt');
        $this->assertSame('private', Storage::disk('s3')->getVisibility('images/laptop.jpg'));
        $this->assertSame('pdf', Storage::disk('s3')->get($history->file_path));
        $this->assertSame('private', Storage::disk('s3')->getVisibility($history->file_path));
        $this->assertSame('s3', $history->fresh()->file_disk);
        $this->assertSame('s3', DB::table('asset_reconciliations')->where('id', $auditId)->value('stored_disk'));
        Storage::disk('legacy-public')->assertExists('images/laptop.jpg');
        Storage::disk('local')->assertExists('asset-reconciliations/old.xlsx');
        Storage::disk('local')->assertExists($history->file_path);
        $this->artisan('storage:migrate-to-s3')->assertSuccessful();
    }

    public function test_migration_dry_run_does_not_write_to_s3_or_change_history(): void
    {
        Storage::disk('local')->put('asset-qr-labels/old.pdf', 'pdf');
        $history = AssetQrLabelHistory::create(['action' => 'print', 'file_path' => 'asset-qr-labels/old.pdf']);

        $this->artisan('storage:migrate-to-s3', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame([], Storage::disk('s3')->allFiles());
        $this->assertSame('local', $history->fresh()->file_disk);
    }

    public function test_migration_refuses_to_overwrite_a_different_s3_object(): void
    {
        Storage::disk('local')->put('asset-qr-labels/old.pdf', 'old pdf');
        Storage::disk('s3')->put('asset-qr-labels/old.pdf', 'different pdf');
        $history = AssetQrLabelHistory::create(['action' => 'print', 'file_path' => 'asset-qr-labels/old.pdf']);

        $this->artisan('storage:migrate-to-s3')->assertFailed();

        $this->assertSame('different pdf', Storage::disk('s3')->get($history->file_path));
        $this->assertSame('local', $history->fresh()->file_disk);
    }

    public function test_s3_check_removes_its_test_object(): void
    {
        $this->artisan('storage:check-s3')->assertSuccessful();
        $this->assertSame([], Storage::disk('s3')->allFiles());
    }

    public function test_s3_previews_use_signed_urls_and_private_visibility(): void
    {
        config(['filesystems.disks.public.driver' => 's3']);
        Storage::disk('public')->buildTemporaryUrlsUsing(fn ($path, $expires) => 'https://storage.example.test/'.$path.'?signed=yes');

        $this->assertSame('https://storage.example.test/image.jpg?signed=yes', StoredFile::url('image.jpg'));
        $upload = FileUpload::make('image');
        $this->assertSame('public', $upload->getDiskName());
        $this->assertSame('private', $upload->getVisibility());
    }

    public function test_stable_download_link_requires_login_and_a_valid_signature(): void
    {
        Storage::disk('public')->put('documents/receipt.pdf', 'receipt');
        $url = StoredFile::downloadUrl('documents/receipt.pdf');
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->get(route('stored-file.download', ['path' => 'documents/receipt.pdf']))->assertForbidden();
        $this->get($url)->assertOk()->assertStreamedContent('receipt');
        $this->get(str_replace('receipt.pdf', 'another.pdf', $url))->assertForbidden();
    }

    private function remoteDisk(): FilesystemAdapter
    {
        $fake = Storage::disk('s3');
        // Wrap the fake so code cannot rely on a LocalFilesystemAdapter path.
        $remote = new FilesystemAdapter($fake->getDriver(), $fake->getAdapter(), $fake->getConfig());
        Storage::set('s3', $remote);

        return $remote;
    }
}
