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
            $outputPrefix = Storage::path('ocr/img_' . uniqid());
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

            // For now, we'll just use the first page
            $imagePath = 'ocr/' . basename($files[0]);
            Storage::move(str_replace(Storage::path(''), '', $files[0]), $imagePath);
            
            // Clean up other pages if they exist
            foreach (array_slice($files, 1) as $file) {
                unlink($file);
            }

            // Update database with image path
            $ocrResult->update([
                'status' => 'awaiting_selection',
                'image_path' => $imagePath
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
