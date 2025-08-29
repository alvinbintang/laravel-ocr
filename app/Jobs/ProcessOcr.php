<?php

namespace App\Jobs;

use App\Models\OcrResult; // ADDED
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage; // ADDED
use thiagoalessio\TesseractOCR\TesseractOCR; // ADDED
use Symfony\Component\Process\Process; // ADDED

class ProcessOcr implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
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
            $outputPrefix = Storage::path('ocr/tmp_'.uniqid());
            $process = new Process(['pdftoppm', '-png', $this->pdfPath, $outputPrefix]);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            $files = glob($outputPrefix.'-*.png');
            $resultText = '';

            foreach ($files as $img) {
                $text = (new TesseractOCR($img))
                    ->lang('ind+eng')
                    ->run();

                $resultText .= "=== Halaman ===\n".$text."\n\n";
            }

            $ocrResult->update(['text' => $resultText, 'status' => 'done']);

            foreach ($files as $img) {
                unlink($img);
            }
            Storage::delete(str_replace(Storage::path(''), '', $this->pdfPath));

        } catch (\Exception $e) {
            $ocrResult->update(['status' => 'error', 'text' => $e->getMessage()]);
        }
    }
}
