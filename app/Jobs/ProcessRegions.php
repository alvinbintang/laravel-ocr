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
            $croppedImages = []; // ADDED: Store cropped image paths

            foreach ($this->regions as $region) {
                // Create a cropped image for the region
                // FIXED: Updated for Intervention Image v3 syntax
                // crop(width, height, offset_x, offset_y, position: 'top-left')
                $croppedImage = $image->crop(
                    $region['width'],
                    $region['height'],
                    $region['x'],
                    $region['y'],
                    position: 'top-left'
                );

                // Save the cropped image temporarily with unique name
                $tempFileName = 'ocr/temp_region_' . $region['id'] . '_' . uniqid() . '.png';
                $tempPath = Storage::disk('public')->path($tempFileName);
                
                // ADDED: Save the cropped image permanently for display
                $permanentFileName = 'ocr/cropped/' . $ocrResult->id . '/page_' . $this->currentPage . '_region_' . $region['id'] . '.png';
                $permanentPath = Storage::disk('public')->path($permanentFileName);
                
                // Ensure directories exist
                $tempDir = dirname($tempPath);
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }
                
                $permanentDir = dirname($permanentPath);
                if (!is_dir($permanentDir)) {
                    mkdir($permanentDir, 0755, true);
                }
                
                // Save the cropped image (both temp and permanent)
                $croppedImage->save($tempPath);
                $croppedImage->save($permanentPath);
                
                // ADDED: Store permanent image path
                $croppedImages[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage,
                    'path' => $permanentFileName
                ];

                // Process the region with Tesseract using TSV format with improved configuration
                $tesseract = new TesseractOCR($tempPath);
                $tesseract->lang('ind+eng')
                    ->psm(6) // Page segmentation mode: Assume a single uniform block of text
                    ->oem(1) // OCR Engine mode: Neural nets LSTM only
                    ->format('tsv')
                    ->config('tessedit_char_whitelist', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,;:\'"-()[]{}!?@#$%^&*+=<>/\\| ')
                    ->dpi(300); // Higher DPI for better recognition
                
                // Run OCR
                $tsv = $tesseract->run();

                // Parse TSV output to extract text with improved confidence handling
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
            $existingCroppedImages = $ocrResult->cropped_images ?? []; // ADDED: Get existing cropped images
            
            // Remove existing results for this page
            $existingResults = array_filter($existingResults, function($result) {
                return !isset($result['page']) || $result['page'] != $this->currentPage;
            });
            
            // ADDED: Remove existing cropped images for this page
            $existingCroppedImages = array_filter($existingCroppedImages, function($image) {
                return !isset($image['page']) || $image['page'] != $this->currentPage;
            });
            
            // Add new results for this page
            $allResults = array_merge($existingResults, $results);
            $allCroppedImages = array_merge($existingCroppedImages, $croppedImages); // ADDED: Merge cropped images

            // Update the OCR result
            $ocrResult->update([
                'status' => 'done',
                'selected_regions' => $this->regions,
                'ocr_results' => $allResults,
                'cropped_images' => $allCroppedImages // ADDED: Save cropped images
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

    // Improved method to parse TSV output from Tesseract with better text structure preservation
    private function parseTsvOutput(string $tsv): string
    {
        $lines = explode("\n", trim($tsv));
        $result = [];
        $currentLine = -1;
        $currentParagraph = -1;
        
        // Skip header line and process data lines
        for ($i = 1; $i < count($lines); $i++) {
            $columns = explode("\t", $lines[$i]);
            
            // TSV format: level, page_num, block_num, par_num, line_num, word_num, left, top, width, height, conf, text
            if (count($columns) >= 12) {
                $level = (int)$columns[0];
                $parNum = (int)$columns[3];
                $lineNum = (int)$columns[4];
                $confidence = (int)$columns[10];
                $wordText = trim($columns[11]);
                
                // Only include words with reasonable confidence (> 40)
                if ($confidence > 40 && !empty($wordText)) {
                    // Track paragraph and line changes to preserve structure
                    if ($parNum !== $currentParagraph) {
                        $currentParagraph = $parNum;
                        if (!empty($result)) {
                            $result[] = "\n\n"; // Double newline for paragraph breaks
                        }
                    } elseif ($lineNum !== $currentLine) {
                        $currentLine = $lineNum;
                        if (!empty($result)) {
                            $result[] = "\n"; // Single newline for line breaks
                        }
                    } else {
                        // Add space between words on the same line
                        if (!empty($result)) {
                            $result[] = " ";
                        }
                    }
                    
                    $result[] = $wordText;
                }
            }
        }
        
        return trim(implode('', $result));
    }
}