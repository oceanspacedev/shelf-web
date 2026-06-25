<?php

use App\Http\Controllers\PdfController;
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

