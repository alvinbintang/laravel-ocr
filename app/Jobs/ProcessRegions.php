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
            
            // ADDED: Debug logging for preview dimensions
            \Log::info("ProcessRegions started", [
                'ocr_result_id' => $this->ocrResultId,
                'current_page' => $this->currentPage,
                'preview_dimensions' => $this->previewDimensions,
                'regions_count' => count($this->regions)
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

            $image = Image::read($imagePath);
            
            // ADDED: Apply rotation if exists for current page
            $pageRotations = $ocrResult->page_rotations ?? [];
            $rotation = $pageRotations[$this->currentPage] ?? 0;
            
            if ($rotation > 0) {
                $image->rotate(-$rotation); // Negative because CSS rotation is clockwise, image rotation is counter-clockwise
                \Log::info("Applied rotation to image", [
                    'page' => $this->currentPage,
                    'rotation' => $rotation,
                    'ocr_result_id' => $this->ocrResultId
                ]);
            }

            // ADDED: Get actual OCR image dimensions for coordinate scaling
            $ocrImageWidth = $image->width();
            $ocrImageHeight = $image->height();

            // ADDED: Resolution detection and mismatch warnings
            if (isset($this->previewDimensions['width']) && isset($this->previewDimensions['height'])) {
                $previewWidth = $this->previewDimensions['width'];
                $previewHeight = $this->previewDimensions['height'];
                
                $scaleX = $ocrImageWidth / $previewWidth;
                $scaleY = $ocrImageHeight / $previewHeight;
                
                // Check for significant mismatch (>10%)
                $scaleDifference = abs($scaleX - $scaleY) / max($scaleX, $scaleY);
                
                if ($scaleDifference > 0.1) {
                    \Log::warning("Coordinate mismatch — scaling factor may be inaccurate", [
                        'preview_dimensions' => ['width' => $previewWidth, 'height' => $previewHeight],
                        'ocr_image_dimensions' => ['width' => $ocrImageWidth, 'height' => $ocrImageHeight],
                        'scale_x' => round($scaleX, 3),
                        'scale_y' => round($scaleY, 3),
                        'scale_difference_percent' => round($scaleDifference * 100, 2),
                        'page' => $this->currentPage,
                        'rotation' => $rotation
                    ]);
                }
                
                \Log::info("Resolution detection completed", [
                    'preview_dimensions' => ['width' => $previewWidth, 'height' => $previewHeight],
                    'ocr_image_dimensions' => ['width' => $ocrImageWidth, 'height' => $ocrImageHeight],
                    'scale_factors' => ['x' => round($scaleX, 3), 'y' => round($scaleY, 3)],
                    'aspect_ratio_match' => $scaleDifference <= 0.1 ? 'good' : 'poor'
                ]);
            } else {
                \Log::warning("Preview dimensions not available for resolution detection", [
                    'ocr_image_dimensions' => ['width' => $ocrImageWidth, 'height' => $ocrImageHeight],
                    'page' => $this->currentPage
                ]);
            }

            foreach ($this->regions as $region) {
                // ADDED: Scale coordinates from preview to OCR image dimensions
                $scaledRegion = $this->scaleCoordinates($region, $ocrImageWidth, $ocrImageHeight);

                // ADDED: Apply coordinate rotation if image was rotated
                if ($rotation > 0) {
                    $scaledRegion = $this->rotateCoordinates($scaledRegion, $ocrImageWidth, $ocrImageHeight, $rotation);
                }

                \Log::info("Final coordinates for cropping", [
                    'original_region' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $region['x'], $region['y'], $region['width'], $region['height']),
                    'scaled_region' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $scaledRegion['x'], $scaledRegion['y'], $scaledRegion['width'], $scaledRegion['height']),
                    'rotation_applied' => $rotation,
                    'region_id' => $region['id'],
                    'page' => $this->currentPage
                ]);

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
                
                \Log::info("Cropped image saved", [
                    'path' => "storage/app/public/{$croppedImagePath}",
                    'region_id' => $region['id'],
                    'page' => $this->currentPage,
                    'dimensions' => ['width' => $scaledRegion['width'], 'height' => $scaledRegion['height']]
                ]);
                
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
                    
                    // ADDED: Parse TSV output to maintain table structure
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
                        // ADDED: Fallback 1: PSM 4 (single column of text of variable sizes)
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

                // UPDATED: Add page information to results with scaled coordinates
                $results[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage, // ADDED: Page information
                    'coordinates' => [
                        'x' => $scaledRegion['x'], // UPDATED: Use scaled coordinates
                        'y' => $scaledRegion['y'], // UPDATED: Use scaled coordinates
                        'width' => $scaledRegion['width'], // UPDATED: Use scaled coordinates
                        'height' => $scaledRegion['height'] // UPDATED: Use scaled coordinates
                    ],
                    'original_coordinates' => [ // ADDED: Keep original preview coordinates for reference
                        'x' => $region['x'],
                        'y' => $region['y'],
                        'width' => $region['width'],
                        'height' => $region['height']
                    ],
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

    // UPDATED: Helper method to scale coordinates from preview to OCR image dimensions
    private function scaleCoordinates(array $region, int $ocrImageWidth, int $ocrImageHeight): array
    {
        // If no preview dimensions provided, return original coordinates (fallback)
        if (!$this->previewDimensions || !isset($this->previewDimensions['width']) || !isset($this->previewDimensions['height'])) {
            \Log::warning("No preview dimensions provided, using original coordinates", [
                'region_id' => $region['id'] ?? 'unknown',
                'ocr_result_id' => $this->ocrResultId
            ]);
            return $region;
        }

        $previewWidth = $this->previewDimensions['width'];
        $previewHeight = $this->previewDimensions['height'];

        // Calculate scaling factors
        $scaleX = $ocrImageWidth / $previewWidth;
        $scaleY = $ocrImageHeight / $previewHeight;

        // ADDED: Check for significant scaling factor mismatch (>10% difference)
        $scaleDifference = abs($scaleX - $scaleY) / max($scaleX, $scaleY);
        if ($scaleDifference > 0.1) {
            \Log::warning("Coordinate mismatch — scaling factor may be inaccurate", [
                'scale_x' => $scaleX,
                'scale_y' => $scaleY,
                'difference_percent' => round($scaleDifference * 100, 2),
                'preview_dimensions' => $this->previewDimensions,
                'ocr_dimensions' => ['width' => $ocrImageWidth, 'height' => $ocrImageHeight],
                'region_id' => $region['id'] ?? 'unknown'
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
            'original' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $region['x'], $region['y'], $region['width'], $region['height']),
            'scaled' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $scaledRegion['x'], $scaledRegion['y'], $scaledRegion['width'], $scaledRegion['height']),
            'scale_factors' => ['x' => $scaleX, 'y' => $scaleY],
            'region_id' => $region['id'] ?? 'unknown'
        ]);

        return $scaledRegion;
    }

    // ADDED: Helper method to rotate coordinates based on image rotation
    private function rotateCoordinates(array $region, int $imageWidth, int $imageHeight, int $rotation): array
    {
        // Normalize rotation to 0-359 degrees
        $rotation = $rotation % 360;
        if ($rotation < 0) $rotation += 360;

        // If no rotation, return original coordinates
        if ($rotation === 0) {
            return $region;
        }

        $x = $region['x'];
        $y = $region['y'];
        $width = $region['width'];
        $height = $region['height'];

        $rotatedRegion = $region; // Start with original region

        switch ($rotation) {
            case 90:
                // 90° clockwise rotation: (x,y) -> (y, imageWidth - x - width)
                $rotatedRegion['x'] = $y;
                $rotatedRegion['y'] = $imageWidth - $x - $width;
                $rotatedRegion['width'] = $height;
                $rotatedRegion['height'] = $width;
                break;

            case 180:
                // 180° rotation: (x,y) -> (imageWidth - x - width, imageHeight - y - height)
                $rotatedRegion['x'] = $imageWidth - $x - $width;
                $rotatedRegion['y'] = $imageHeight - $y - $height;
                // Width and height remain the same
                break;

            case 270:
                // 270° clockwise rotation: (x,y) -> (imageHeight - y - height, x)
                $rotatedRegion['x'] = $imageHeight - $y - $height;
                $rotatedRegion['y'] = $x;
                $rotatedRegion['width'] = $height;
                $rotatedRegion['height'] = $width;
                break;

            default:
                \Log::warning("Unsupported rotation angle", [
                    'rotation' => $rotation,
                    'region_id' => $region['id'] ?? 'unknown'
                ]);
                return $region; // Return original if unsupported rotation
        }

        \Log::info("Applying rotation {$rotation}° -> adjusted coordinates", [
            'original' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $x, $y, $width, $height),
            'rotated' => sprintf("(x=%d, y=%d, w=%d, h=%d)", $rotatedRegion['x'], $rotatedRegion['y'], $rotatedRegion['width'], $rotatedRegion['height']),
            'rotation' => $rotation,
            'image_dimensions' => ['width' => $imageWidth, 'height' => $imageHeight],
            'region_id' => $region['id'] ?? 'unknown'
        ]);

        return $rotatedRegion;
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