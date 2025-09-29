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

    /**
     * Create a new job instance.
     */
    public function __construct($ocrResultId, $pdfPath)
    {
        $this->ocrResultId = $ocrResultId;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Execute the job.
     */
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

            // Convert PDF to images using ImageMagick
            $imagePaths = $this->convertPdfToImages($this->pdfPath, $ocrResult->id);
            
            if (empty($imagePaths)) {
                throw new \Exception('Failed to convert PDF to images');
            }

            // Update database with image paths and set status to awaiting_selection
            $ocrResult->update([
                'status' => 'awaiting_selection',
                'image_paths' => json_encode($imagePaths),
                'image_path' => $imagePaths[0] ?? null, // First page as primary image
            ]);

            // Delete the original PDF after successful conversion
            Storage::delete(str_replace(Storage::path(''), '', $this->pdfPath));

        } catch (\Exception $e) {
            $ocrResult->update([
                'status' => 'error',
                'text' => 'Error: ' . $e->getMessage(),
                'image_path' => null,
                'image_paths' => null,
                'ocr_results' => null
            ]);
        }
    }

    /**
     * Convert PDF to images using ImageMagick
     */
    private function convertPdfToImages($pdfPath, $ocrResultId)
    {
        $imagePaths = [];
        $outputDir = Storage::path('ocr/images/' . $ocrResultId);
        
        // Create output directory if it doesn't exist
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Check if ImageMagick is installed
        $checkImageMagick = new Process(['magick', '-version']);
        $checkImageMagick->run();
        if (!$checkImageMagick->isSuccessful()) {
            // Try convert command (older ImageMagick versions)
            $checkConvert = new Process(['convert', '-version']);
            $checkConvert->run();
            if (!$checkConvert->isSuccessful()) {
                throw new \Exception('ImageMagick not found. Please install ImageMagick');
            }
            $convertCommand = 'convert';
        } else {
            $convertCommand = 'magick';
        }

        // Convert PDF to images (PNG format for better quality)
        $outputPattern = $outputDir . '/page-%03d.png';
        
        $process = new Process([
            $convertCommand,
            '-density', '300',          // High DPI for better quality
            '-quality', '100',          // Maximum quality
            '-colorspace', 'RGB',       // Ensure RGB colorspace
            '-background', 'white',     // White background
            '-alpha', 'remove',         // Remove transparency
            $pdfPath,
            $outputPattern
        ]);

        \Illuminate\Support\Facades\Log::info('Running ImageMagick command', [
            'command' => $process->getCommandLine(),
            'pdf_path' => $pdfPath,
            'output_pattern' => $outputPattern
        ]);

        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception('PDF to image conversion failed: ' . $process->getErrorOutput());
        }

        // Collect generated image paths
        $files = glob($outputDir . '/page-*.png');
        sort($files); // Ensure proper page order

        foreach ($files as $file) {
            $relativePath = 'ocr/images/' . $ocrResultId . '/' . basename($file);
            $imagePaths[] = $relativePath;
        }

        if (empty($imagePaths)) {
            throw new \Exception('No images were generated from PDF');
        }

        \Illuminate\Support\Facades\Log::info('PDF conversion completed', [
            'total_pages' => count($imagePaths),
            'image_paths' => $imagePaths
        ]);

        return $imagePaths;
    }
}
