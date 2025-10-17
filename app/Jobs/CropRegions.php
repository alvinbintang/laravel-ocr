<?php

namespace App\Jobs;

use App\Models\OcrResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CropRegions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ocrResultId;
    protected $regions;
    protected $currentPage;
    protected $previewDimensions;
    protected $pageRotation;

    public function __construct($ocrResultId, array $regions, int $currentPage = 1, array $previewDimensions = null, ?int $pageRotation = null)
    {
        $this->ocrResultId = $ocrResultId;
        $this->regions = $regions;
        $this->currentPage = $currentPage;
        $this->previewDimensions = $previewDimensions;
        $this->pageRotation = $pageRotation;
    }

    public function handle(): void
    {
        try {
            $ocrResult = OcrResult::findOrFail($this->ocrResultId);
            
            \Log::info("CropRegions started", [
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

            $imageRelativePath = $imagePaths[$this->currentPage - 1];
            $imagePath = storage_path('app/public/' . $imageRelativePath);
            
            // FIXED: Check if image file exists, log for debugging
            if (!file_exists($imagePath)) {
                \Log::error("Image file not found", [
                    'page' => $this->currentPage,
                    'relative_path' => $imageRelativePath,
                    'full_path' => $imagePath,
                    'file_exists' => false
                ]);
                throw new \Exception("Image file not found: {$imagePath}");
            }
            
            \Log::info("Using image for cropping", [
                'page' => $this->currentPage,
                'relative_path' => $imageRelativePath,
                'full_path' => $imagePath,
                'file_exists' => true,
                'file_size' => filesize($imagePath),
                'is_rotated_image' => strpos($imageRelativePath, 'rotated') !== false // ADDED: Check if using rotated image
            ]);
            
            // Initialize array for cropped images
            $croppedImages = [];

            $image = Image::read($imagePath);
            
            // Get rotation for this page - use 0 if no rotation (for backend-rotated images)
            $rotation = $this->pageRotation ?? 0;
            
            // FIXED: Check if we're using a rotated image file or need to apply rotation to cropped result
            $isUsingRotatedImage = strpos($imageRelativePath, 'rotated') !== false;
            
            \Log::info("Processing crop regions", [
                'page' => $this->currentPage,
                'rotation' => $rotation,
                'is_using_rotated_image' => $isUsingRotatedImage,
                'image_path' => $imageRelativePath,
                'note' => $isUsingRotatedImage ? 'Using rotated image directly' : 'Using original image, will apply rotation to crops'
            ]);

            // Get actual image dimensions for coordinate scaling
            $imageWidth = $image->width();
            $imageHeight = $image->height();

            foreach ($this->regions as $region) {
                // FIXED: Handle coordinate transformation based on whether we're using rotated image
                if ($isUsingRotatedImage) {
                    // If using rotated image file, coordinates are already correct for the rotated image
                    $scaledRegion = $this->scaleCoordinates($region, $imageWidth, $imageHeight);
                } else {
                    // If using original image, transform coordinates from rotated view back to original coordinates
                    if ($rotation > 0) {
                        $transformedRegion = $this->transformCoordinatesFromRotatedView($region, $rotation);
                        $scaledRegion = $this->scaleCoordinates($transformedRegion, $imageWidth, $imageHeight);
                    } else {
                        $scaledRegion = $this->scaleCoordinates($region, $imageWidth, $imageHeight);
                    }
                }

                // Crop the region from the image
                $croppedImage = $image->crop($scaledRegion['width'], $scaledRegion['height'], $scaledRegion['x'], $scaledRegion['y']);
                
                // FIXED: Apply rotation to cropped image if we're using original image
                if (!$isUsingRotatedImage && $rotation > 0) {
                    $croppedImage->rotate($rotation); // FIXED: Use positive rotation for preview display to match user's rotation direction
                    \Log::info("Applied rotation to cropped image", [
                        'page' => $this->currentPage,
                        'region_id' => $region['id'],
                        'rotation' => $rotation,
                        'applied_rotation' => $rotation,
                        'note' => 'Rotated cropped image to match user rotation direction for preview'
                    ]);
                } else {
                    \Log::info("No rotation applied to cropped image", [
                        'page' => $this->currentPage,
                        'region_id' => $region['id'],
                        'is_using_rotated_image' => $isUsingRotatedImage,
                        'rotation' => $rotation,
                        'note' => $isUsingRotatedImage ? 'Already using rotated image' : 'No rotation needed'
                    ]);
                }
                
                // Save cropped image
                $croppedImageName = "cropped_page_{$this->currentPage}_region_{$region['id']}_" . time() . ".png";
                $croppedImagePath = "ocr_results/{$this->ocrResultId}/cropped/{$croppedImageName}";
                
                // Ensure directory exists
                Storage::disk('public')->makeDirectory(dirname($croppedImagePath));
                
                // FIXED: Add logging and error handling for image saving
                try {
                    // FIXED: Use save() method directly instead of encode() to avoid EncoderInterface error
                    $storagePath = Storage::disk('public')->path($croppedImagePath);
                    $storageDir = dirname($storagePath);
                    
                    \Log::info("Attempting to save cropped image", [
                        'page' => $this->currentPage,
                        'region_id' => $region['id'],
                        'relative_path' => $croppedImagePath,
                        'full_path' => $storagePath,
                        'directory' => $storageDir,
                        'directory_exists' => file_exists($storageDir)
                    ]);
                    
                    // FIXED: Ensure directory exists using native PHP
                    if (!file_exists($storageDir)) {
                        mkdir($storageDir, 0755, true);
                        \Log::info("Created directory", ['path' => $storageDir]);
                    }
                    
                    // FIXED: Use save() method directly like in ProcessRegions.php
                    $croppedImage->save($storagePath);
                    
                    // Verify file was actually saved
                    if (!file_exists($storagePath)) {
                        throw new \Exception("File verification failed - cropped image not found after save");
                    }
                    
                    \Log::info("Cropped image saved successfully", [
                        'page' => $this->currentPage,
                        'region_id' => $region['id'],
                        'relative_path' => $croppedImagePath,
                        'full_path' => $storagePath,
                        'file_size' => filesize($storagePath)
                    ]);
                    
                } catch (\Exception $saveException) {
                    \Log::error("Failed to save cropped image", [
                        'page' => $this->currentPage,
                        'region_id' => $region['id'],
                        'path' => $croppedImagePath,
                        'error' => $saveException->getMessage(),
                        'directory_exists' => Storage::disk('public')->exists(dirname($croppedImagePath))
                    ]);
                    throw $saveException;
                }
                
                $croppedImages[] = [
                    'region_id' => $region['id'],
                    'page' => $this->currentPage,
                    'image_path' => $croppedImagePath,
                    'coordinates' => [
                        'x' => $scaledRegion['x'],
                        'y' => $scaledRegion['y'],
                        'width' => $scaledRegion['width'],
                        'height' => $scaledRegion['height']
                    ]
                ];
            }

            // Update OCR result with cropped images info and set status to awaiting_confirmation
            $existingCroppedImages = $ocrResult->cropped_images ?? [];
            $allCroppedImages = array_merge($existingCroppedImages, $croppedImages);
            
            $ocrResult->update([
                'cropped_images' => $allCroppedImages,
                'status' => 'awaiting_confirmation'
            ]);

            \Log::info("CropRegions completed successfully", [
                'ocr_result_id' => $this->ocrResultId,
                'cropped_images_count' => count($croppedImages)
            ]);

        } catch (\Exception $e) {
            \Log::error("CropRegions failed", [
                'ocr_result_id' => $this->ocrResultId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update status to error
            $ocrResult = OcrResult::find($this->ocrResultId);
            if ($ocrResult) {
                $ocrResult->update([
                    'status' => 'error',
                    'text' => 'Error cropping regions: ' . $e->getMessage()
                ]);
            }

            throw $e;
        }
    }

    /**
     * Transform coordinates from rotated view back to original image coordinates
     */
    private function transformCoordinatesFromRotatedView(array $region, int $rotation): array
    {
        $previewWidth = $this->previewDimensions['width'] ?? 800;
        $previewHeight = $this->previewDimensions['height'] ?? 600;
        
        $x = $region['x'];
        $y = $region['y'];
        $width = $region['width'];
        $height = $region['height'];
        
        switch ($rotation) {
            case 90:
                return [
                    'x' => $y,
                    'y' => $previewWidth - $x - $width,
                    'width' => $height,
                    'height' => $width
                ];
            case 180:
                return [
                    'x' => $previewWidth - $x - $width,
                    'y' => $previewHeight - $y - $height,
                    'width' => $width,
                    'height' => $height
                ];
            case 270:
                return [
                    'x' => $previewHeight - $y - $height,
                    'y' => $x,
                    'width' => $height,
                    'height' => $width
                ];
            default:
                return $region;
        }
    }

    /**
     * Scale coordinates from preview dimensions to actual image dimensions
     */
    private function scaleCoordinates(array $region, int $imageWidth, int $imageHeight): array
    {
        if (!$this->previewDimensions) {
            return $region;
        }

        $previewWidth = $this->previewDimensions['width'];
        $previewHeight = $this->previewDimensions['height'];
        
        $scaleX = $imageWidth / $previewWidth;
        $scaleY = $imageHeight / $previewHeight;

        return [
            'x' => (int)round($region['x'] * $scaleX),
            'y' => (int)round($region['y'] * $scaleY),
            'width' => (int)round($region['width'] * $scaleX),
            'height' => (int)round($region['height'] * $scaleY)
        ];
    }
}