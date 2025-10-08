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
    protected $previewDimensions; // ADDED: Store preview dimensions for scaling

    public function __construct($ocrResultId, array $regions, int $currentPage = 1, array $previewDimensions = null)
    {
        $this->ocrResultId = $ocrResultId;
        $this->regions = $regions;
        $this->currentPage = $currentPage; // ADDED: Store current page
        $this->previewDimensions = $previewDimensions; // ADDED: Store preview dimensions
    }

    public function handle(): void
    {
        try {
            $ocrResult = OcrResult::findOrFail($this->ocrResultId);

            \Log::info("ProcessRegions started", [
                'ocr_result_id' => $this->ocrResultId,
                'current_page' => $this->currentPage,
                'preview_dimensions' => $this->previewDimensions,
                'regions_count' => count($this->regions)
            ]);

            $imagePaths = $ocrResult->image_paths ?? [];
            if (!isset($imagePaths[$this->currentPage - 1])) {
                throw new \Exception("Image not found for page {$this->currentPage}");
            }

            $imagePath = storage_path('app/public/' . $imagePaths[$this->currentPage - 1]);
            if (!file_exists($imagePath)) {
                throw new \Exception("Image file does not exist: {$imagePath}");
            }

            $image = Image::read($imagePath);
            
            // UPDATED: Get original image dimensions BEFORE rotation
            $originalImageWidth = $image->width();
            $originalImageHeight = $image->height();

            \Log::info("Original image loaded", [
                'path' => $imagePath,
                'dimensions' => ['width' => $originalImageWidth, 'height' => $originalImageHeight]
            ]);

            $pageRotations = $ocrResult->page_rotations ?? [];
            $rotation = $pageRotations[$this->currentPage] ?? 0;

            // UPDATED: Apply rotation to image first
            if ($rotation > 0) {
                \Log::info("Applying rotation to image", [
                    'rotation' => $rotation,
                    'before_rotation' => ['width' => $originalImageWidth, 'height' => $originalImageHeight]
                ]);
                
                $image->rotate(-$rotation);
                
                \Log::info("Image rotated successfully", [
                    'after_rotation' => ['width' => $image->width(), 'height' => $image->height()]
                ]);
            }

            // Get final image dimensions after rotation
            $finalImageWidth = $image->width();
            $finalImageHeight = $image->height();

            $results = [];
            $croppedImages = [];

            foreach ($this->regions as $index => $region) {
                try {
                    \Log::info("Processing region {$index}", [
                        'region_id' => $region['id'],
                        'original_coordinates' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $region['x'], $region['y'], $region['width'], $region['height'])
                    ]);

                    // UPDATED: Step 1 - Scale coordinates from preview to original image dimensions
                    $scaledRegion = $this->scaleCoordinates($region, $originalImageWidth, $originalImageHeight);

                    // UPDATED: Step 2 - Apply rotation to coordinates (using original dimensions)
                    $rotatedRegion = $this->rotateCoordinates($scaledRegion, $originalImageWidth, $originalImageHeight, $rotation);

                    // UPDATED: Step 3 - Validate and clamp coordinates to final image bounds
                    $finalRegion = $this->validateAndClampCoordinates($rotatedRegion, $finalImageWidth, $finalImageHeight);

                    // UPDATED: Crop the image using final coordinates
                    $croppedImage = $image->crop(
                        $finalRegion['width'], 
                        $finalRegion['height'], 
                        $finalRegion['x'], 
                        $finalRegion['y']
                    );

                    // UPDATED: Save cropped image with high DPI (300) for better OCR accuracy
                    $croppedDir = storage_path("app/public/ocr_results/{$this->ocrResultId}/cropped");
                    if (!is_dir($croppedDir)) {
                        mkdir($croppedDir, 0755, true);
                    }

                    $croppedImagePath = "{$croppedDir}/page_{$this->currentPage}_region_{$region['id']}.png";
                    
                    // Save with high quality and DPI for OCR
                    $croppedImage->toPng()->save($croppedImagePath);

                    \Log::info("Cropped image saved", [
                        'region_id' => $region['id'],
                        'path' => $croppedImagePath,
                        'final_coordinates' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $finalRegion['x'], $finalRegion['y'], $finalRegion['width'], $finalRegion['height'])
                    ]);

                    // Store cropped image info
                    $croppedImages[] = [
                        'region_id' => $region['id'],
                        'page' => $this->currentPage,
                        'path' => str_replace(storage_path('app/public/'), '', $croppedImagePath),
                        'coordinates' => $finalRegion
                    ];

                    // UPDATED: Perform OCR with fallback PSM modes and high DPI
                    $ocrText = $this->performOcrWithFallback($croppedImagePath, $region['id']);

                    // Add to results
                    $results[] = [
                        'region_id' => $region['id'],
                        'page' => $this->currentPage,
                        'coordinates' => [
                            'x' => $finalRegion['x'],
                            'y' => $finalRegion['y'],
                            'width' => $finalRegion['width'],
                            'height' => $finalRegion['height']
                        ],
                        'original_coordinates' => [
                            'x' => $region['x'],
                            'y' => $region['y'],
                            'width' => $region['width'],
                            'height' => $region['height']
                        ],
                        'text' => $ocrText
                    ];

                    \Log::info("OCR completed for region", [
                        'region_id' => $region['id'],
                        'text_length' => strlen($ocrText),
                        'text_preview' => substr($ocrText, 0, 100) . (strlen($ocrText) > 100 ? '...' : '')
                    ]);

                } catch (\Exception $e) {
                    \Log::error("Failed to process region {$index}", [
                        'region_id' => $region['id'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    // Continue processing other regions
                    continue;
                }
            }

            // UPDATED: Merge results with existing OCR results for other pages
            $existingResults = $ocrResult->ocr_results ?? [];
            $existingCroppedImages = $ocrResult->cropped_images ?? [];
            
            // Remove existing results for this page
            $existingResults = array_filter($existingResults, function($result) {
                return !isset($result['page']) || $result['page'] != $this->currentPage;
            });
            
            // Remove existing cropped images for this page
            $existingCroppedImages = array_filter($existingCroppedImages, function($image) {
                return !isset($image['page']) || $image['page'] != $this->currentPage;
            });
            
            // Add new results for this page
            $allResults = array_merge($existingResults, $results);
            $allCroppedImages = array_merge($existingCroppedImages, $croppedImages);

            // Update the OCR result
            $ocrResult->update([
                'status' => 'done',
                'selected_regions' => $this->regions,
                'ocr_results' => $allResults,
                'cropped_images' => $allCroppedImages
            ]);

            \Log::info("ProcessRegions completed successfully", [
                'ocr_result_id' => $this->ocrResultId,
                'processed_regions' => count($results)
            ]);

        } catch (\Exception $e) {
            \Log::error("ProcessRegions failed", [
                'ocr_result_id' => $this->ocrResultId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $ocrResult->update([
                'status' => 'error',
                'ocr_results' => ['error' => $e->getMessage()]
            ]);
            
            throw $e;
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

    // UPDATED: Enhanced helper method to scale coordinates from preview to OCR image dimensions with validation
    private function scaleCoordinates(array $region, int $ocrImageWidth, int $ocrImageHeight): array
    {
        // If no preview dimensions provided, return original coordinates (fallback)
        if (!$this->previewDimensions || !isset($this->previewDimensions['width']) || !isset($this->previewDimensions['height'])) {
            \Log::warning("No preview dimensions provided, using original coordinates", [
                'region_id' => $region['id'],
                'coordinates' => $region
            ]);
            return $region;
        }

        $previewWidth = $this->previewDimensions['width'];
        $previewHeight = $this->previewDimensions['height'];

        // Calculate scaling factors
        $scaleX = $ocrImageWidth / $previewWidth;
        $scaleY = $ocrImageHeight / $previewHeight;

        // ADDED: Check for significant scaling factor differences (>10% difference)
        $scaleDifference = abs($scaleX - $scaleY) / max($scaleX, $scaleY);
        if ($scaleDifference > 0.1) {
            \Log::warning("Coordinate mismatch — scaling factor may be inaccurate", [
                'scale_x' => $scaleX,
                'scale_y' => $scaleY,
                'difference_percentage' => $scaleDifference * 100,
                'preview_dimensions' => ['width' => $previewWidth, 'height' => $previewHeight],
                'ocr_dimensions' => ['width' => $ocrImageWidth, 'height' => $ocrImageHeight]
            ]);
        }

        // Apply scaling to coordinates
        $scaledRegion = [
            'id' => $region['id'],
            'x' => (int) round($region['x'] * $scaleX),
            'y' => (int) round($region['y'] * $scaleY),
            'width' => (int) round($region['width'] * $scaleX),
            'height' => (int) round($region['height'] * $scaleY),
            'page' => $region['page'] ?? $this->currentPage
        ];

        \Log::info("Scaling region from preview -> OCR image", [
            'region_id' => $region['id'],
            'original' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $region['x'], $region['y'], $region['width'], $region['height']),
            'scaled' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $scaledRegion['x'], $scaledRegion['y'], $scaledRegion['width'], $scaledRegion['height']),
            'scale_factors' => ['x' => $scaleX, 'y' => $scaleY]
        ]);

        return $scaledRegion;
    }

    // ADDED: Helper method to rotate coordinates based on image rotation
    private function rotateCoordinates(array $region, int $imageWidth, int $imageHeight, int $rotation): array
    {
        $x = $region['x'];
        $y = $region['y'];
        $width = $region['width'];
        $height = $region['height'];

        // Normalize rotation to 0-359 degrees
        $rotation = $rotation % 360;
        if ($rotation < 0) $rotation += 360;

        $rotatedRegion = $region; // Start with original region

        switch ($rotation) {
            case 90:
                // 90° clockwise: (x,y) -> (y, imageWidth - x - width)
                $rotatedRegion['x'] = $y;
                $rotatedRegion['y'] = $imageWidth - $x - $width;
                $rotatedRegion['width'] = $height;
                $rotatedRegion['height'] = $width;
                break;

            case 180:
                // 180°: (x,y) -> (imageWidth - x - width, imageHeight - y - height)
                $rotatedRegion['x'] = $imageWidth - $x - $width;
                $rotatedRegion['y'] = $imageHeight - $y - $height;
                // width and height remain the same
                break;

            case 270:
                // 270° clockwise: (x,y) -> (imageHeight - y - height, x)
                $rotatedRegion['x'] = $imageHeight - $y - $height;
                $rotatedRegion['y'] = $x;
                $rotatedRegion['width'] = $height;
                $rotatedRegion['height'] = $width;
                break;

            default:
                // No rotation or unsupported angle
                break;
        }

        \Log::info("Applying rotation {$rotation}° -> adjusted coordinates", [
            'region_id' => $region['id'],
            'rotation' => $rotation,
            'original' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $x, $y, $width, $height),
            'rotated' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $rotatedRegion['x'], $rotatedRegion['y'], $rotatedRegion['width'], $rotatedRegion['height']),
            'image_dimensions' => ['width' => $imageWidth, 'height' => $imageHeight]
        ]);

        return $rotatedRegion;
    }

    // ADDED: Helper method to validate and clamp coordinates within image bounds
    private function validateAndClampCoordinates(array $region, int $imageWidth, int $imageHeight): array
    {
        $originalRegion = $region;
        
        // Ensure coordinates are not negative
        $region['x'] = max(0, $region['x']);
        $region['y'] = max(0, $region['y']);
        
        // Ensure region doesn't exceed image bounds
        $region['width'] = min($region['width'], $imageWidth - $region['x']);
        $region['height'] = min($region['height'], $imageHeight - $region['y']);
        
        // Ensure minimum dimensions
        $region['width'] = max(1, $region['width']);
        $region['height'] = max(1, $region['height']);

        // Log if coordinates were clamped
        if ($originalRegion !== $region) {
            \Log::warning("Coordinates clamped to image bounds", [
                'region_id' => $region['id'],
                'original' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $originalRegion['x'], $originalRegion['y'], $originalRegion['width'], $originalRegion['height']),
                'clamped' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $region['x'], $region['y'], $region['width'], $region['height']),
                'image_bounds' => ['width' => $imageWidth, 'height' => $imageHeight]
            ]);
        }

        return $region;
    }

    /**
     * ADDED: Perform OCR with fallback PSM modes and high DPI for better accuracy
     */
    private function performOcrWithFallback(string $imagePath, int $regionId): string
    {
        $psmModes = [6, 4, 11]; // PSM 6 (uniform block), PSM 4 (single column), PSM 11 (sparse text)
        
        foreach ($psmModes as $psm) {
            try {
                \Log::info("Attempting OCR with PSM {$psm}", [
                    'region_id' => $regionId,
                    'image_path' => $imagePath
                ]);
                
                $tesseract = new TesseractOCR($imagePath);
                $tesseract->lang('ind+eng')
                    ->psm($psm)
                    ->oem(1) // Neural nets LSTM only
                    ->format('tsv')
                    ->config('tessedit_char_whitelist', 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789.,;:\'\"-()\[\]{}!?@#$%^&*+=<>/\\| ')
                    ->dpi(300); // High DPI for better accuracy
                
                $tsv = $tesseract->run();
                
                if (empty($tsv)) {
                    throw new \Exception("Empty TSV output from Tesseract");
                }
                
                $text = $this->parseTsvOutput($tsv);
                
                // Check if we got reasonable results (minimum text length)
                if (strlen(trim($text)) >= 3) {
                    \Log::info("OCR successful with PSM {$psm}", [
                        'region_id' => $regionId,
                        'text_length' => strlen($text),
                        'text_preview' => substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '')
                    ]);
                    
                    return trim($text);
                }
                
                throw new \Exception("Insufficient text detected (length: " . strlen(trim($text)) . ")");
                
            } catch (\Exception $e) {
                \Log::warning("OCR failed with PSM {$psm}", [
                    'region_id' => $regionId,
                    'psm' => $psm,
                    'error' => $e->getMessage()
                ]);
                
                // Continue to next PSM mode
                continue;
            }
        }
        
        // If all PSM modes failed, return error message
        \Log::error("All OCR PSM modes failed", [
            'region_id' => $regionId,
            'attempted_psm_modes' => $psmModes
        ]);
        
        return "OCR processing failed for this region";
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