<?php

namespace App\Jobs;

use App\Models\OcrResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Intervention\Image\Facades\Image;

class ProcessRegions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ocrResultId;
    protected $regions;

    public function __construct($ocrResultId, array $regions)
    {
        $this->ocrResultId = $ocrResultId;
        $this->regions = $regions;
    }

    public function handle(): void
    {
        $ocrResult = OcrResult::find($this->ocrResultId);
        if (!$ocrResult) {
            return;
        }

        try {
            $imagePath = Storage::path($ocrResult->image_path);
            $image = Image::make($imagePath);
            $results = [];

            foreach ($this->regions as $region) {
                // Create a cropped image for the region
                $croppedImage = $image->crop(
                    $region['width'],
                    $region['height'],
                    $region['x'],
                    $region['y']
                );

                // Save the cropped image temporarily
                $tempPath = Storage::path('ocr/temp_' . uniqid() . '.png');
                $croppedImage->save($tempPath);

                // Process the region with Tesseract
                $text = (new TesseractOCR($tempPath))
                    ->lang('ind+eng')
                    ->run();

                // Add to results
                $results[] = [
                    'region_id' => $region['id'],
                    'coordinates' => [
                        'x' => $region['x'],
                        'y' => $region['y'],
                        'width' => $region['width'],
                        'height' => $region['height']
                    ],
                    'text' => trim($text)
                ];

                // Clean up temporary file
                unlink($tempPath);
            }

            // Update the OCR result
            $ocrResult->update([
                'status' => 'done',
                'selected_regions' => $this->regions,
                'ocr_results' => $results
            ]);

        } catch (\Exception $e) {
            $ocrResult->update([
                'status' => 'error',
                'ocr_results' => ['error' => $e->getMessage()]
            ]);
        }
    }
}