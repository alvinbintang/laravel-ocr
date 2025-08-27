<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OcrController;

Route::get('/ocr', [OcrController::class, 'index']);
Route::post('/ocr', [OcrController::class, 'extract'])->name('ocr.extract');