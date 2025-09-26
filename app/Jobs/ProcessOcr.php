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

        $ocrResult->update(['status' => 'processing']);

        try {
            $outputPrefix = Storage::disk('public')->path('ocr/img_' . uniqid());
            
            // Ensure the ocr directory exists in public storage
            Storage::disk('public')->makeDirectory('ocr');
            
            $process = new Process(['pdftoppm', '-png', $this->pdfPath, $outputPrefix]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            // Get the generated image files
            $files = glob($outputPrefix . '-*.png');
            
            if (empty($files)) {
                throw new \Exception('No images were generated from the PDF');
            }

            // Sort files to ensure correct page order
            sort($files);
            
            // Process all pages
            $imagePaths = [];
            foreach ($files as $index => $file) {
                $imagePath = 'ocr/' . basename($file);
                
                // Move each page to public storage
                $sourceFile = str_replace(Storage::disk('public')->path(''), '', $file);
                Storage::disk('public')->move($sourceFile, $imagePath);
                
                $imagePaths[] = $imagePath;
            }

            // Update database with all image paths
            $ocrResult->update([
                'status' => 'awaiting_selection',
                'image_path' => $imagePaths[0], // Keep first page as primary for backward compatibility
                'image_paths' => json_encode($imagePaths) // Store all pages
            ]);

            // Delete the original PDF
            Storage::delete(str_replace(Storage::path(''), '', $this->pdfPath));

        } catch (\Exception $e) {
            $ocrResult->update([
                'status' => 'error',
                'ocr_results' => json_encode(['error' => $e->getMessage()])
            ]);
        }
    }
}
