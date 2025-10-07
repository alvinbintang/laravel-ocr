<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OcrApiController;

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

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Protected API routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    
    // OCR routes
    Route::prefix('ocr')->group(function () {
        // Get all OCR results
        Route::get('/', [OcrApiController::class, 'index']);
        
        // Upload and extract PDF
        Route::post('/extract', [OcrApiController::class, 'extract']);
        
        // Get OCR result by ID
        Route::get('/{id}', [OcrApiController::class, 'show']);
        
        // Get preview data
        Route::get('/{id}/preview', [OcrApiController::class, 'preview']);
        
        // Get status
        Route::get('/{id}/status', [OcrApiController::class, 'status']);
        
        // Process regions
        Route::post('/{id}/process-regions', [OcrApiController::class, 'processRegions']);
        
        // Get processing result
        Route::get('/{id}/result', [OcrApiController::class, 'result']);
        
        // Export routes
        Route::get('/{id}/export/json', [OcrApiController::class, 'exportJson']);
        Route::get('/{id}/export/csv', [OcrApiController::class, 'exportCsv']);
        Route::get('/{id}/export/excel', [OcrApiController::class, 'exportExcel']);
        
        // Save rotations
        Route::post('/{id}/save-rotations', [OcrApiController::class, 'saveRotations']);
    });
});