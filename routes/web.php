<?php

use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicAssetRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', 'admin');

Route::get('asset-transfer/{id}/download', [PdfController::class, 'downloadAssetTransfer'])->name('asset-transfer.download');
Route::get('task-completion/{id}/download', [PdfController::class, 'downloadTaskCompletion'])->name('task-completion.download');
Route::get('task-completion/{id}/preview', [PdfController::class, 'previewTaskCompletion'])->name('task-completion.preview');

Route::prefix('asset-requests')->name('asset-requests.')->group(function () {
    Route::get('/', [PublicAssetRequestController::class, 'index'])->name('index');
    Route::get('success/{uuid}', [PublicAssetRequestController::class, 'success'])->name('success');
    Route::get('approve/{token}', [PublicAssetRequestController::class, 'showApproval'])->name('show-approval');
    Route::post('approve/{token}', [PublicAssetRequestController::class, 'processApproval'])->name('process-approval');
    Route::get('{type}', [PublicAssetRequestController::class, 'create'])->name('create');
    Route::post('{type}', [PublicAssetRequestController::class, 'store'])->name('store');
});

Route::prefix('public-asset-requests')->group(function () {
    Route::get('/', [PublicAssetRequestController::class, 'legacyIndexRedirect']);
    Route::get('success/{uuid}', [PublicAssetRequestController::class, 'legacySuccessRedirect']);
    Route::get('approve/{token}', [PublicAssetRequestController::class, 'legacyApprovalRedirect']);
    Route::post('approve/{token}', [PublicAssetRequestController::class, 'processApproval']);
    Route::post('{type}', [PublicAssetRequestController::class, 'store']);
    Route::get('{type}', [PublicAssetRequestController::class, 'legacyCreateRedirect']);
});
