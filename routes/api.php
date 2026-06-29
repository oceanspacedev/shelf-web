<?php

use App\Http\Controllers\AuthenticatedUserController;
use App\Http\Controllers\Integrations\WhatsappAssetQueryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', AuthenticatedUserController::class);

Route::post('/integrations/whatsapp/assets/query', WhatsappAssetQueryController::class)
    ->name('integrations.whatsapp.assets.query');
