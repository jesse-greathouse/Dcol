<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BlogController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

# Blog
Route::middleware('auth:sanctum')->get('/blog', [BlogController::class, 'index']);
Route::middleware('auth:sanctum')->get('/blog/domain/{domainName}', [BlogController::class, 'domainName']);
Route::middleware('auth:sanctum')->get('/blog/{id}', [BlogController::class, 'show']);
Route::middleware('auth:sanctum')->post('/blog', [BlogController::class, 'store']);
Route::middleware('auth:sanctum')->put('/blog/{id}', [BlogController::class, 'update']);
Route::middleware('auth:sanctum')->delete('/blog/{id}', [BlogController::class, 'delete']);

# AiModel
Route::middleware('auth:sanctum')->get('/ai_model', [AiModelController::class, 'index']);
// Route::middleware('auth:sanctum')->get('/blog/domain/{domainName}', [AiModelController::class, 'domainName']);
// Route::middleware('auth:sanctum')->get('/blog/{id}', [BlAiModelControllerogController::class, 'show']);
// Route::middleware('auth:sanctum')->post('/blog', [AiModelController::class, 'store']);
// Route::middleware('auth:sanctum')->put('/blog/{id}', [AiModelController::class, 'update']);
// Route::middleware('auth:sanctum')->delete('/blog/{id}', [AiModelController::class, 'delete']);
