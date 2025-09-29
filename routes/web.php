<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrController;
use App\Http\Controllers\ExcelExportController;

Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');
Route::post('/ocr/extract', [OcrController::class, 'extract'])->name('ocr.extract');
Route::get('/ocr/{id}/preview', [OcrController::class, 'preview'])->name('ocr.preview');
Route::get('/ocr/{id}/status', [OcrController::class, 'status'])->name('ocr.status');
Route::get('/ocr/{id}/status-check', [OcrController::class, 'statusCheck'])->name('ocr.status-check'); // ADDED: API endpoint for status checking
Route::post('/ocr/{id}/process-regions', [OcrController::class, 'processRegions'])->name('ocr.process-regions');
Route::get('/ocr/{id}/result', [OcrController::class, 'showResult'])->name('ocr.result');
Route::get('/ocr/export/{id}', [ExcelExportController::class, 'exportToExcel'])->name('ocr.export');
Route::get('/ocr/export-json/{id}', [OcrController::class, 'exportJson'])->name('ocr.export-json');
Route::get('/ocr/export-csv/{id}', [OcrController::class, 'exportCsv'])->name('ocr.export-csv');