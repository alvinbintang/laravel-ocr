<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrController;
use App\Http\Controllers\ExcelExportController; // ADDED

Route::get('/ocr', [OcrController::class, 'index'])->name('ocr.index');
Route::post('/ocr', [OcrController::class, 'extract'])->name('ocr.extract');
Route::get('/ocr/result/{id}', [OcrController::class, 'showResult'])->name('ocr.result'); // ADDED
Route::get('/ocr/export/{id}', [ExcelExportController::class, 'exportToExcel'])->name('ocr.export'); // ADDED