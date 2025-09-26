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
use Intervention\Image\Laravel\Facades\Image;

class ProcessRegions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ocrResultId;
    protected $regions;
    protected $currentPage; // ADDED: Track current page

    public function __construct($ocrResultId, array $regions, int $currentPage = 1)
    {
        $this->ocrResultId = $ocrResultId;
        $this->regions = $regions;
        $this->currentPage = $currentPage; // ADDED: Store current page
    }

    public function handle(): void
    {
        $ocrResult = OcrResult::find($this->ocrResultId);
        if (!$ocrResult) {
            return;
        }

        try {
            // UPDATED: Get image path for specific page
            $imagePath = $this->getImagePathForPage($ocrResult, $this->currentPage);
            if (!$imagePath) {
                throw new \Exception("Image not found for page {$this->currentPage}");
            }

            $image = Image::read($imagePath);
            $results = [];

            foreach ($this->regions as $region) {
                // Create a cropped image for the region
                $croppedImage = $image->crop(
                    $region['width'],
                    $region['height'],
                    $region['x'],
                    $region['y']
                );

                // Save the cropped image temporarily with unique name
                $tempFileName = 'ocr/temp_region_' . $region['id'] . '_' . uniqid() . '.png';
                $tempPath = Storage::disk('public')->path($tempFileName);
                
                // Ensure directory exists
                $tempDir = dirname($tempPath);
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                
                // Save the cropped image
                $croppedImage->save($tempPath);

                // Process the region with Tesseract using TSV format
                $tsv = (new TesseractOCR($tempPath))
                    ->lang('ind+eng')
                    ->psm(6)
                    ->oem(1)
                    ->format('tsv')
                    ->run();

                // Parse TSV output to extract text
                $text = $this->parseTsvOutput($tsv);

                // UPDATED: Add page information to results
                $results[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage, // ADDED: Page information
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

            // UPDATED: Merge results with existing OCR results for other pages
            $existingResults = $ocrResult->ocr_results ?? [];
            
            // Remove existing results for this page
            $existingResults = array_filter($existingResults, function($result) {
                return !isset($result['page']) || $result['page'] != $this->currentPage;
            });
            
            // Add new results for this page
            $allResults = array_merge($existingResults, $results);

            // Update the OCR result
            $ocrResult->update([
                'status' => 'done',
                'selected_regions' => $this->regions,
                'ocr_results' => $allResults
            ]);

        } catch (\Exception $e) {
            $ocrResult->update([
                'status' => 'error',
                'ocr_results' => ['error' => $e->getMessage()]
            ]);
        }
    }

    // ADDED: Helper method to get image path for specific page
    private function getImagePathForPage(OcrResult $ocrResult, int $page): ?string
    {
        // If multi-page images exist, use them
        if ($ocrResult->image_paths) {
            $imagePaths = $ocrResult->image_paths;
            if (isset($imagePaths[$page - 1])) {
                return Storage::disk('public')->path($imagePaths[$page - 1]);
            }
        }
        
        // Fallback to single image path for page 1
        if ($page === 1 && $ocrResult->image_path) {
            return Storage::disk('public')->path($ocrResult->image_path);
        }
        
        return null;
    }

    // ADDED: Helper method to parse TSV output from Tesseract
    private function parseTsvOutput(string $tsv): string
    {
        $lines = explode("\n", trim($tsv));
        $text = '';
        
        // Skip header line and process data lines
        for ($i = 1; $i < count($lines); $i++) {
            $columns = explode("\t", $lines[$i]);
            
            // TSV format: level, page_num, block_num, par_num, line_num, word_num, left, top, width, height, conf, text
            if (count($columns) >= 12) {
                $confidence = (int)$columns[10];
                $wordText = trim($columns[11]);
                
                // Only include words with reasonable confidence (> 30)
                if ($confidence > 30 && !empty($wordText)) {
                    $text .= $wordText . ' ';
                }
            }
        }
        
        return trim($text);
    }
}