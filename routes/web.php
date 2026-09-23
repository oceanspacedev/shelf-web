<?php

use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicAssetQrController;
use App\Http\Controllers\PublicAssetRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the bootstrap/app.php routing configuration and
| will be assigned to the "web" middleware group.
|
*/

Route::redirect('/', 'asset-requests');

Route::get('asset-transfer/{id}/download', [PdfController::class, 'downloadAssetTransfer'])->middleware(['auth'])->name('asset-transfer.download');
Route::get('pengadaan/{id}/download', [PdfController::class, 'downloadPengadaan'])->middleware(['auth'])->name('pengadaan.download');
Route::get('task-completion/{id}/download', [PdfController::class, 'downloadTaskCompletion'])->middleware(['auth'])->name('task-completion.download');
Route::get('task-completion/{id}/preview', [PdfController::class, 'previewTaskCompletion'])->middleware(['auth'])->name('task-completion.preview');
Route::post('admin/camera-upload', [\App\Http\Controllers\CameraUploadController::class, 'upload'])->middleware(['auth'])->name('admin.camera-upload');

Route::get('asset-requests', [PublicAssetRequestController::class, 'index'])->name('public.asset-requests.index');
Route::get('asset-requests/status/{token}', [PublicAssetRequestController::class, 'show'])->name('public.asset-requests.show');
Route::get('asset-requests/approval/{token}', [PublicAssetRequestController::class, 'showApproval'])->name('public.asset-requests.approval');
Route::post('asset-requests/approval/{token}/approve', [PublicAssetRequestController::class, 'approveApproval'])->middleware(['throttle:public'])->name('public.asset-requests.approval.approve');
Route::post('asset-requests/approval/{token}/reject', [PublicAssetRequestController::class, 'rejectApproval'])->middleware(['throttle:public'])->name('public.asset-requests.approval.reject');
Route::post('asset-requests', [PublicAssetRequestController::class, 'store'])->middleware(['throttle:public'])->name('public.asset-requests.store');

Route::get('qr/{qr}', [PublicAssetQrController::class, 'show'])->name('qr.show');
Route::post('qr/{qr}/location', [PublicAssetQrController::class, 'storeLocation'])
    ->middleware('throttle:60,1')
    ->name('qr.location');
