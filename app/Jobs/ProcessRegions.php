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
    protected $currentPage;
    protected $previewDimensions;
    protected $pageRotations; // ADDED: Store page rotations for scaling

    public function __construct($ocrResultId, array $regions, int $currentPage = 1, array $previewDimensions = null, array $pageRotations = [])
    {
        $this->ocrResultId = $ocrResultId;
        $this->regions = $regions;
        $this->currentPage = $currentPage;
        $this->previewDimensions = $previewDimensions;
        $this->pageRotations = $pageRotations; // ADDED: Store page rotations
    }

    public function handle(): void
    {
        try {
            $ocrResult = OcrResult::findOrFail($this->ocrResultId);
            
            // ADDED: Debug logging for preview dimensions
            \Log::info("ProcessRegions started", [
                'ocr_result_id' => $this->ocrResultId,
                'current_page' => $this->currentPage,
                'preview_dimensions' => $this->previewDimensions,
                'regions_count' => count($this->regions),
                'page_rotations' => $this->pageRotations // ADDED: Log rotations
            ]);

            // Get the image path for the specific page
            $imagePaths = $ocrResult->image_paths ?? [];
            if (!isset($imagePaths[$this->currentPage - 1])) {
                throw new \Exception("Image not found for page {$this->currentPage}");
            }

            $imagePath = storage_path('app/public/' . $imagePaths[$this->currentPage - 1]);
            
            // Initialize arrays for results and cropped images
            $results = [];
            $croppedImages = [];

            // Load original image
            $image = Image::read($imagePath);
            
            // UPDATED: Get original dimensions before rotation
            $originalWidth = $image->width();
            $originalHeight = $image->height();
            
            // Get rotation for current page and apply if needed
            $rotation = $this->pageRotations[$this->currentPage] ?? 0;
            
            if ($rotation > 0) {
                // Calculate size of new canvas needed to fit rotated image
                $diagonal = ceil(sqrt(pow($originalWidth, 2) + pow($originalHeight, 2)));
                
                // Create new square canvas large enough to hold rotated image
                $newCanvas = Image::canvas($diagonal, $diagonal);
                
                // Calculate offset to center original image in new canvas
                $offsetX = ($diagonal - $originalWidth) / 2;
                $offsetY = ($diagonal - $originalHeight) / 2;
                
                // Insert original image into center of new canvas
                $newCanvas->insert($image, 'top-left', $offsetX, $offsetY);
                
                // Rotate the canvas with the centered image
                $newCanvas->rotate(-$rotation); // Negative because CSS rotation is clockwise
                
                // Update image reference
                $image = $newCanvas;
                
                \Log::info("Applied rotation to image", [
                    'page' => $this->currentPage,
                    'rotation' => $rotation,
                    'original_dimensions' => [
                        'width' => $originalWidth,
                        'height' => $originalHeight
                    ],
                    'new_dimensions' => [
                        'width' => $diagonal,
                        'height' => $diagonal
                    ]
                ]);
            }

            // Get final image dimensions for coordinate scaling
            $ocrImageWidth = $image->width();
            $ocrImageHeight = $image->height();

            foreach ($this->regions as $region) {
                // ADDED: Scale coordinates from preview to OCR image dimensions
                $scaledRegion = $this->scaleCoordinates($region, $ocrImageWidth, $ocrImageHeight);

                // Crop the region from the image
                $croppedImage = $image->crop($scaledRegion['width'], $scaledRegion['height'], $scaledRegion['x'], $scaledRegion['y']);
                
                // Save cropped image for debugging and permanent storage
                $croppedImageName = "cropped_page_{$this->currentPage}_region_{$region['id']}_" . time() . ".png";
                $croppedImagePath = "ocr_results/{$this->ocrResultId}/cropped/{$croppedImageName}";
                $croppedImageFullPath = Storage::disk('public')->path($croppedImagePath);
                
                // Ensure directory exists
                $croppedImageDir = dirname($croppedImageFullPath);
                if (!is_dir($croppedImageDir)) {
                    mkdir($croppedImageDir, 0755, true);
                }
                
                // Save cropped image
                $croppedImage->save($croppedImageFullPath);
                
                // Store cropped image info
                $croppedImages[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage,
                    'path' => $croppedImagePath,
                    'coordinates' => $scaledRegion
                ];

                // Save to temporary file for OCR processing
                $tempPath = tempnam(sys_get_temp_dir(), 'ocr_region_') . '.png';
                $croppedImage->save($tempPath);

                // UPDATED: OCR processing with fallback configurations for better table reading
                try {
                    // Primary configuration: PSM 6 (single uniform block of text)
                    $tesseract = new TesseractOCR($tempPath);
                    $tesseract->lang('ind+eng')
                        ->psm(6) // Page segmentation mode: Assume a single uniform block of text
                        ->oem(1) // OCR Engine mode: Neural nets LSTM only
                        ->format('tsv')
                        ->config('tessedit_char_whitelist', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,;:\'"-()\[\]{}!?@#$%^&*+=<>/\\| ')
                        ->dpi(300); // Higher DPI for better recognition
                    
                    // Run OCR
                    $tsv = $tesseract->run();
                    
                        $text = $this->parseTsvOutput($tsv);
                    
                    // Check if we got reasonable results (minimum text length)
                    if (strlen(trim($text)) < 3) {
                        throw new \Exception("Insufficient text detected with PSM 6");
                    }
                    
                    \Log::info("OCR successful with PSM 6", [
                        'region_id' => $region['id'],
                        'text_length' => strlen($text)
                    ]);
                    
                } catch (\Exception $e) {
                    \Log::warning("OCR failed with PSM 6, trying PSM 4", [
                        'region_id' => $region['id'],
                        'error' => $e->getMessage()
                    ]);
                    
                    try {
                                                // Fallback 1: PSM 4 (single column of text)
                        $tesseract = new TesseractOCR($tempPath);
                        $tesseract->lang('ind+eng')
                            ->psm(4)
                            ->oem(1)
                            ->format('tsv')
                            ->config('tessedit_char_whitelist', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,;:\'"-()\[\]{}!?@#$%^&*+=<>/\\| ')
                            ->dpi(300);
                        
                        $tsv = $tesseract->run();
                        $text = $this->parseTsvOutput($tsv);
                        
                        if (strlen(trim($text)) < 3) {
                            throw new \Exception("Insufficient text detected with PSM 4");
                        }
                        
                        \Log::info("OCR successful with PSM 4 fallback", [
                            'region_id' => $region['id'],
                            'text_length' => strlen($text)
                        ]);
                        
                    } catch (\Exception $e2) {
                        \Log::warning("OCR failed with PSM 4, trying PSM 11", [
                            'region_id' => $region['id'],
                            'error' => $e2->getMessage()
                        ]);
                        
                        try {
                            // ADDED: Fallback 2: PSM 11 (sparse text)
                            $tesseract = new TesseractOCR($tempPath);
                            $tesseract->lang('ind+eng')
                                ->psm(11)
                                ->oem(1)
                                ->format('tsv')
                                ->config('tessedit_char_whitelist', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,;:\'"-()\[\]{}!?@#$%^&*+=<>/\\| ')
                                ->dpi(300);
                            
                            $tsv = $tesseract->run();
                            $text = $this->parseTsvOutput($tsv);
                            
                            \Log::info("OCR completed with PSM 11 fallback", [
                                'region_id' => $region['id'],
                                'text_length' => strlen($text)
                            ]);
                            
                        } catch (\Exception $e3) {
                            \Log::error("All OCR configurations failed", [
                                'region_id' => $region['id'],
                                'errors' => [$e->getMessage(), $e2->getMessage(), $e3->getMessage()]
                            ]);
                            
                            $text = "OCR processing failed for this region";
                        }
                    }
                }

                // Add results with both scaled and original coordinates
                $results[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage,
                    'coordinates' => [
                        'x' => $scaledRegion['x'],
                        'y' => $scaledRegion['y'],
                        'width' => $scaledRegion['width'],
                        'height' => $scaledRegion['height']
                    ],
                    'original_coordinates' => [
                        'x' => $region['x'],
                        'y' => $region['y'],
                        'width' => $region['width'],
                        'height' => $region['height']
                    ],
                    'rotation' => $rotation, // ADDED: Include rotation information
                    'text' => trim($text)
                ];

                // Clean up temporary file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
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

    private function scaleCoordinates(array $region, int $ocrImageWidth, int $ocrImageHeight, int $rotation): array
    {
        // If no preview dimensions provided, return original coordinates (fallback)
        if (!$this->previewDimensions || !isset($this->previewDimensions['width']) || !isset($this->previewDimensions['height'])) {
            return $region;
        }

        $previewWidth = $this->previewDimensions['width'];
        $previewHeight = $this->previewDimensions['height'];

        // Scale coordinates
        $scaleX = $ocrImageWidth / $previewWidth;
        $scaleY = $ocrImageHeight / $previewHeight;
        
        $x = $region['x'] * $scaleX;
        $y = $region['y'] * $scaleY;
        $width = $region['width'] * $scaleX;
        $height = $region['height'] * $scaleY;
        
        if ($rotation > 0) {
            // Calculate center point of the image
            $centerX = $ocrImageWidth / 2;
            $centerY = $ocrImageHeight / 2;
            
            // Convert rotation to radians
            $radians = deg2rad($rotation);
            
            // Translate point to origin (center of image)
            $translatedX = $x - $centerX;
            $translatedY = $y - $centerY;
            
            // Apply rotation transformation
            $newX = $translatedX * cos($radians) - $translatedY * sin($radians);
            $newY = $translatedX * sin($radians) + $translatedY * cos($radians);
            
            // Translate back
            $x = $newX + $centerX;
            $y = $newY + $centerY;
            
            // For 90° or 270° rotations, swap width and height
            if ($rotation == 90 || $rotation == 270) {
                list($width, $height) = [$height, $width];
            }
        }

        return [
            'id' => $region['id'],
            'x' => (int) round($x),
            'y' => (int) round($y),
            'width' => (int) round($width),
            'height' => (int) round($height),
            'page' => $region['page'] ?? $this->currentPage,
            'rotation' => $rotation // ADDED: Include rotation for reference
        ];
    }

    // UPDATED: Improved method to parse TSV output from Tesseract with line-based grouping for table structure
    private function parseTsvOutput(string $tsv): string
    {
        $lines = explode("\n", trim($tsv));
        $lineGroups = []; // UPDATED: Group text by line_num for table structure
        
        // Skip header line and process data lines
        for ($i = 1; $i < count($lines); $i++) {
            $columns = explode("\t", $lines[$i]);
            
            // TSV format: level, page_num, block_num, par_num, line_num, word_num, left, top, width, height, conf, text
            if (count($columns) >= 12) {
                $level = (int)$columns[0];
                $parNum = (int)$columns[3];
                $lineNum = (int)$columns[4];
                $wordNum = (int)$columns[5];
                $left = (int)$columns[6];
                $top = (int)$columns[7];
                $confidence = (int)$columns[10];
                $wordText = trim($columns[11]);
                
                // Only include words with reasonable confidence (> 30 for better table capture)
                if ($confidence > 30 && !empty($wordText) && $level === 5) { // Level 5 = word level
                    // UPDATED: Group by line_num to maintain table row structure
                    if (!isset($lineGroups[$lineNum])) {
                        $lineGroups[$lineNum] = [];
                    }
                    
                    // Store word with position for proper ordering within line
                    $lineGroups[$lineNum][] = [
                        'text' => $wordText,
                        'left' => $left,
                        'top' => $top,
                        'confidence' => $confidence,
                        'word_num' => $wordNum
                    ];
                }
            }
        }
        
        // UPDATED: Process each line group to maintain table structure
        $result = [];
        ksort($lineGroups); // Sort by line number
        
        foreach ($lineGroups as $lineNum => $words) {
            if (empty($words)) continue;
            
            // Sort words by horizontal position (left coordinate) for proper column order
            usort($words, function($a, $b) {
                return $a['left'] <=> $b['left'];
            });
            
            // Join words in the line with appropriate spacing
            $lineText = '';
            $lastRight = 0;
            
            foreach ($words as $word) {
                // Add spacing based on horizontal gap between words
                if ($lastRight > 0) {
                    $gap = $word['left'] - $lastRight;
                    if ($gap > 50) { // Large gap suggests column separation
                        $lineText .= "\t"; // Use tab for column separation
                    } elseif ($gap > 20) { // Medium gap
                        $lineText .= "  "; // Double space
                    } else {
                        $lineText .= " "; // Single space
                    }
                }
                
                $lineText .= $word['text'];
                $lastRight = $word['left'] + 50; // Approximate word width
            }
            
            if (!empty(trim($lineText))) {
                $result[] = trim($lineText);
            }
        }
        
        // UPDATED: Join lines with newlines to preserve table row structure
        return implode("\n", $result);
    }
}