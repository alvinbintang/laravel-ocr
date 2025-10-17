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
                'file_size' => filesize($imagePath)
            ]);
            
            // Initialize array for cropped images
            $croppedImages = [];

            $image = Image::read($imagePath);
            
            // Get rotation for this page - use 0 if no rotation (for backend-rotated images)
            $rotation = $this->pageRotation ?? 0;
            
            // FIXED: For backend-rotated images, coordinates are already correct, no transformation needed
            // The image path already points to the rotated image, so we don't need coordinate transformation
            \Log::info("Processing crop regions", [
                'page' => $this->currentPage,
                'rotation' => $rotation,
                'note' => 'Using rotated image directly, no coordinate transformation needed'
            ]);

            // Get actual image dimensions for coordinate scaling
            $imageWidth = $image->width();
            $imageHeight = $image->height();

            foreach ($this->regions as $region) {
                // FIXED: Since we're using the rotated image directly, no coordinate transformation needed
                // The coordinates from the frontend are already correct for the rotated image
                $scaledRegion = $this->scaleCoordinates($region, $imageWidth, $imageHeight);

                // Crop the region from the image
                $croppedImage = $image->crop($scaledRegion['width'], $scaledRegion['height'], $scaledRegion['x'], $scaledRegion['y']);
                
                // FIXED: No additional rotation needed since we're cropping from the already rotated image
                \Log::info("Cropped region from rotated image", [
                    'page' => $this->currentPage,
                    'region_id' => $region['id'],
                    'coordinates' => $scaledRegion,
                    'note' => 'No additional rotation applied - using rotated image directly'
                ]);
                
                // Save cropped image
                $croppedImageName = "cropped_page_{$this->currentPage}_region_{$region['id']}_" . time() . ".png";
                $croppedImagePath = "ocr_results/{$this->ocrResultId}/cropped/{$croppedImageName}";
                
                // Ensure directory exists
                Storage::disk('public')->makeDirectory(dirname($croppedImagePath));
                
                // Save the cropped image
                Storage::disk('public')->put($croppedImagePath, $croppedImage->encode());
                
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