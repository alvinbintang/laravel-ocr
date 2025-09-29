<?php

namespace App\Jobs;

use App\Models\OcrResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ProcessOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ocrResultId;
    protected $pdfPath;

    public function __construct($ocrResultId, $pdfPath)
    {
        $this->ocrResultId = $ocrResultId;
        $this->pdfPath = $pdfPath;
    }

    public function handle(): void
    {
        $ocrResult = OcrResult::find($this->ocrResultId);
        if (!$ocrResult) {
            return;
        }

        // Update status to processing and clear any previous results
        $ocrResult->update([
            'status' => 'processing',
            'text' => null,
            'image_path' => null,
            'image_paths' => null,
            'ocr_results' => null
        ]);

        try {
            // Log PDF information for debugging
            \Illuminate\Support\Facades\Log::info('PDF Processing Details', [
                'pdf_path' => $this->pdfPath,
                'file_exists' => file_exists($this->pdfPath),
                'file_size' => file_exists($this->pdfPath) ? filesize($this->pdfPath) : 0,
                'file_permissions' => file_exists($this->pdfPath) ? substr(sprintf('%o', fileperms($this->pdfPath)), -4) : 'N/A'
            ]);

            // Use tesseract directly on PDF
            $outputPath = Storage::path('ocr/output_' . uniqid() . '.txt');
            
            // Check if tesseract is installed
            $checkTesseract = new Process(['which', 'tesseract']);
            $checkTesseract->run();
            if (!$checkTesseract->isSuccessful()) {
                throw new \Exception('Tesseract not found. Please install tesseract-ocr');
            }

            // Run OCR directly on PDF
            $process = new Process([
                'tesseract',
                $this->pdfPath,     // Input PDF
                pathinfo($outputPath, PATHINFO_DIRNAME) . '/' . pathinfo($outputPath, PATHINFO_FILENAME),  // Output path without extension
                'PDF',              // Specify PDF input
                '-l', 'eng',        // Language
                '--oem', '1',       // OCR Engine Mode
                '--psm', '1'        // Page Segmentation Mode
            ]);
            
            \Illuminate\Support\Facades\Log::info('Running Tesseract command', [
                'command' => $process->getCommandLine(),
                'pdf_path' => $this->pdfPath,
                'output_path' => $outputPath
            ]);

            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception('OCR failed: ' . $process->getErrorOutput());
            }

            // Read the OCR results
            if (!file_exists($outputPath)) {
                throw new \Exception('OCR output file not found');
            }

            $ocrText = file_get_contents($outputPath);
            
            // Clean up temporary file
            @unlink($outputPath);

            // Update database with OCR results
            $ocrResult->update([
                'status' => 'done',
                'text' => $ocrText,         // Store text in the dedicated column
                'image_path' => null,       // We're not using images anymore
                'image_paths' => null,      // We're not using images anymore
                'ocr_results' => null       // No need for additional JSON storage
            ]);

            // Delete the original PDF
            Storage::delete(str_replace(Storage::path(''), '', $this->pdfPath));

        } catch (\Exception $e) {
            $ocrResult->update([
                'status' => 'error',
                'text' => 'Error: ' . $e->getMessage(),  // Store error in text field
                'image_path' => null,
                'image_paths' => null,
                'ocr_results' => null
            ]);
        }
    }
}
