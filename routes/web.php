<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\OcrController;
use App\Http\Controllers\Admin\ExcelExportController;

Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');
Route::post('/ocr/extract', [OcrController::class, 'extract'])->name('ocr.extract');
Route::get('/ocr/{id}/preview', [OcrController::class, 'preview'])->name('ocr.preview');
Route::get('/ocr/{id}/rka-preview', [OcrController::class, 'rkaPreview'])->name('ocr.rka-preview'); // ADDED: New route for RKA preview page
Route::get('/ocr/{id}/multiselect', [OcrController::class, 'multiselect'])->name('ocr.multiselect'); // ADDED: New route for multiselect page
Route::get('/ocr/{id}/status', [OcrController::class, 'status'])->name('ocr.status');
Route::get('/ocr/{id}/status-check', [OcrController::class, 'statusCheck'])->name('ocr.status-check'); // ADDED: API endpoint for status checking
Route::post('/ocr/{id}/crop-regions', [OcrController::class, 'cropRegions'])->name('ocr.crop-regions');
Route::get('/ocr/{id}/crop-preview', [OcrController::class, 'cropPreview'])->name('ocr.crop-preview');
Route::post('/ocr/{id}/confirm-crop', [OcrController::class, 'confirmCrop'])->name('ocr.confirm-crop');
Route::post('/ocr/{id}/process-regions', [OcrController::class, 'processRegions'])->name('ocr.process-regions');
Route::get('/ocr/{id}/result', [OcrController::class, 'showResult'])->name('ocr.result');
Route::get('/ocr/export/{id}', [ExcelExportController::class, 'exportToExcel'])->name('ocr.export');
Route::get('/ocr/export-json/{id}', [OcrController::class, 'exportJson'])->name('ocr.export-json');
Route::get('/ocr/export-csv/{id}', [OcrController::class, 'exportCsv'])->name('ocr.export-csv');
Route::post('/ocr/{id}/save-rotations', [OcrController::class, 'saveRotations'])->name('ocr.save-rotations');
Route::post('/ocr/{id}/apply-rotation', [OcrController::class, 'applyRotation'])->name('ocr.apply-rotation'); // ADDED: New route for actual image rotation