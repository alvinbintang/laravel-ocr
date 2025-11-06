<?php

namespace App\Services\Admin;

use App\Jobs\ProcessOcr;
use App\Jobs\ProcessRegions;
use App\Models\OcrResult;
use App\Repositories\Contracts\OcrResultRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    protected $ocrResultRepository;

    public function __construct(OcrResultRepositoryInterface $ocrResultRepository)
    {
        $this->ocrResultRepository = $ocrResultRepository;
    }

    /**
     * Get all OCR results
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllOcrResults()
    {
        return $this->ocrResultRepository->getAllOrderedByCreatedAt();
    }

    /**
     * Process PDF upload and create OCR result
     *
     * @param UploadedFile $pdfFile
     * @param string $documentType
     * @return array
     * @throws \Exception
     */
    public function processPdfUpload(UploadedFile $pdfFile, ?string $documentType = 'RAB'): array // UPDATED: Made documentType nullable with default value
    {
        // Log upload information
        Log::info('PDF Upload Details', [
            'original_name' => $pdfFile->getClientOriginalName(),
            'mime_type' => $pdfFile->getMimeType(),
            'size' => $pdfFile->getSize(),
            'error' => $pdfFile->getError(),
            'document_type' => $documentType
        ]);

        // Additional PDF validation
        $pdfContent = file_get_contents($pdfFile->getRealPath());
        if (substr($pdfContent, 0, 4) !== '%PDF') {
            throw new \Exception('File bukan PDF yang valid. Pastikan file tidak corrupt.');
        }

        // Simpan PDF
        $path = $pdfFile->store('ocr');

        // Simpan informasi ke database
        $ocrResult = $this->ocrResultRepository->create([
            'filename' => basename($path),
            'document_type' => $documentType ?? 'RAB', // UPDATED: Ensure document_type is never null
            'status' => 'pending',
            'page_rotations' => json_encode([]),
        ]);

        // Dispatch job ke antrian untuk konversi PDF ke image
        ProcessOcr::dispatch($ocrResult->id, Storage::path($path));

        return [
            'success' => true,
            'ocr_result_id' => $ocrResult->id,
            'message' => 'File sedang dikonversi. Silakan tunggu sebentar untuk memilih area.'
        ];
    }

    /**
     * Get OCR result by ID
     *
     * @param int $id
     * @return \App\Models\OcrResult
     */
    public function getOcrResultById(int $id)
    {
        return $this->ocrResultRepository->findById($id);
    }

    /**
     * Check if OCR result can be previewed
     *
     * @param int $id
     * @return array
     */
    public function checkPreviewStatus(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if ($ocrResult->status === 'pending' || $ocrResult->status === 'processing') {
            return [
                'can_preview' => false,
                'redirect_to_status' => true,
                'message' => 'File masih dalam proses konversi. Mohon tunggu sebentar.'
            ];
        }
        
        if ($ocrResult->status === 'error') {
            return [
                'can_preview' => false,
                'redirect_to_status' => true,
                'message' => 'Terjadi kesalahan dalam memproses file.'
            ];
        }

        return [
            'can_preview' => true,
            'ocr_result' => $ocrResult,
            'message' => 'Preview data retrieved successfully' // ADDED: Missing message key
        ];
    }

    /**
     * Get status information for OCR result
     *
     * @param int $id
     * @return array
     */
    public function getStatusInfo(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        return [
            'status' => $ocrResult->status,
            'filename' => $ocrResult->filename,
            'ocr_result' => $ocrResult
        ];
    }

    /**
     * Process regions for OCR
     *
     * @param int $id
     * @param array $regions
     * @param array|null $previewDimensions
     * @param array|null $pageRotations // UPDATED: Changed to accept array of rotations
     * @return array
     * @throws \Exception
     */
    public function processRegions(int $id, array $regions, ?array $previewDimensions = null, ?array $pageRotations = null): array
    {
        try {
            $ocrResult = $this->ocrResultRepository->findById($id);
            
            // Update status to processing
            $this->ocrResultRepository->updateStatus($id, 'processing');
            
            // Group regions by page
            $regionsByPage = collect($regions)->groupBy('page')->toArray();
            
            // Process each page's regions separately
            foreach ($regionsByPage as $page => $pageRegions) {
                ProcessRegions::dispatch($id, $pageRegions, (int)$page, $previewDimensions);
            }

            return [
                'success' => true,
                'message' => 'Processing selected regions',
                'status' => 'processing'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing regions: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Get OCR processing result
     *
     * @param int $id
     * @return array
     */
    public function getOcrResult(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if ($ocrResult->status === 'done') {
            return [
                'status' => 'success',
                'results' => $ocrResult->ocr_results
            ];
        }

        return [
            'status' => $ocrResult->status,
            'message' => $ocrResult->status === 'error' 
                ? 'Error processing regions' 
                : 'Still processing'
        ];
    }

    /**
     * Check if OCR result is ready for export
     *
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function isReadyForExport(int $id): bool
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if ($ocrResult->status !== 'done') {
            throw new \Exception('OCR belum selesai diproses.');
        }

        return true;
    }

    /**
     * Prepare JSON export data
     *
     * @param int $id
     * @return array
     */
    public function prepareJsonExportData(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        return [
            'document' => [
                'filename' => $ocrResult->filename,
                'processed_at' => $ocrResult->updated_at->toIso8601String(),
                'total_pages' => $ocrResult->getTotalPages(),
            ],
            'ocr_results' => $ocrResult->ocr_results
        ];
    }

    
    /**
     * Save page rotations for OCR result
     *
     * @param int $id
     * @param array $rotations
     * @return array
     */
    public function savePageRotations(int $id, array $rotations): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if (!$ocrResult) {
            return [
                'success' => false,
                'message' => 'OCR result not found'
            ];
        }
        
        try {
            $this->ocrResultRepository->update($id, [
                'page_rotations' => json_encode($rotations)
            ]);
            
            return [
                'success' => true,
                'message' => 'Page rotations saved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Error saving page rotations: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error saving page rotations'
            ];
        }
    }

    /**
     * Apply rotation to image file and return new image URL
     * ADDED: New method for actual image rotation in backend
     *
     * @param int $id
     * @param int $pageNumber
     * @param int $rotationDegree
     * @return array
     */
    public function applyRotation(int $id, int $pageNumber, int $rotationDegree): array
    {
        try {
            $ocrResult = $this->ocrResultRepository->findById($id);
            
            if (!$ocrResult) {
                return [
                    'success' => false,
                    'message' => 'OCR result not found'
                ];
            }

            // Get the original image path for the specific page
            $imagePaths = $ocrResult->image_paths ?? [];
            if (!isset($imagePaths[$pageNumber - 1])) {
                return [
                    'success' => false,
                    'message' => "Image not found for page {$pageNumber}"
                ];
            }

            $originalImagePath = $imagePaths[$pageNumber - 1];
            $fullOriginalPath = storage_path('app/public/' . $originalImagePath);

            if (!file_exists($fullOriginalPath)) {
                return [
                    'success' => false,
                    'message' => 'Original image file not found'
                ];
            }

            // Normalize rotation degree
            $rotationDegree = $rotationDegree % 360;
            if ($rotationDegree < 0) $rotationDegree += 360;

            // If rotation is 0, no need to rotate
            if ($rotationDegree === 0) {
                return [
                    'success' => true,
                    'message' => 'No rotation needed',
                    'rotated_image_url' => Storage::url($originalImagePath),
                    'rotation_applied' => 0
                ];
            }

            // UPDATED: Use shared folder for all rotated images instead of per-ID folders
            $rotatedDir = "ocr/rotated";
            
            // Storage facade will create directory automatically, no mkdir needed
            Storage::disk('public')->makeDirectory($rotatedDir);
            $rotatedDirPath = Storage::disk('public')->path($rotatedDir);

            // Generate rotated image filename with unique naming in shared folder
            $pathInfo = pathinfo($originalImagePath);
            $originalFileName = $pathInfo['filename']; // e.g., "ocr_{id}_page-000"
            $rotatedImageName = $originalFileName . "_rotated_{$rotationDegree}deg_" . time() . "." . $pathInfo['extension'];
            $rotatedImagePath = $rotatedDir . '/' . $rotatedImageName;

            // Load and rotate the image using Intervention Image
            $image = \Intervention\Image\Laravel\Facades\Image::read($fullOriginalPath);
            
            // Apply rotation (negative because Intervention Image rotates counter-clockwise)
            $image->rotate(-$rotationDegree);
            
            // UPDATED: Save the rotated image with better error handling
            try {
                // UPDATED: Use correct Intervention Image encoding for Laravel
                $fullRotatedPath = Storage::disk('public')->path($rotatedImagePath);
                $image->save($fullRotatedPath, 90, 'png');
                
                // Verify file was actually saved
                if (!file_exists($fullRotatedPath)) {
                    throw new \Exception('File verification failed - file does not exist after save');
                }
                
                Log::info('Rotated image saved successfully', [
                    'path' => $rotatedImagePath,
                    'full_path' => $fullRotatedPath,
                    'file_size' => filesize($fullRotatedPath)
                ]);
                
            } catch (\Exception $saveException) {
                Log::error('Failed to save rotated image', [
                    'error' => $saveException->getMessage(),
                    'path' => $rotatedImagePath,
                    'full_path' => $fullRotatedPath ?? 'N/A',
                    'directory_exists' => file_exists($rotatedDirPath),
                    'directory_path' => $rotatedDirPath,
                    'directory_writable' => is_writable($rotatedDirPath)
                ]);
                throw new \Exception("Error applying rotation: " . $saveException->getMessage());
            }

            // Update the image path in the database for this page
            $updatedImagePaths = $imagePaths;
            $updatedImagePaths[$pageNumber - 1] = $rotatedImagePath;

            // Also update page rotations to reflect the applied rotation
            $currentRotations = $ocrResult->page_rotations ?? [];
            $currentRotations[$pageNumber] = $rotationDegree;

            $this->ocrResultRepository->update($id, [
                'image_paths' => $updatedImagePaths,
                'page_rotations' => $currentRotations
            ]);

            return [
                'success' => true,
                'message' => 'Image rotated successfully',
                'rotated_image_url' => Storage::url($rotatedImagePath),
                'rotation_applied' => $rotationDegree,
                'page_number' => $pageNumber
            ];

        } catch (\Exception $e) {
            Log::error('Error applying rotation: ' . $e->getMessage(), [
                'ocr_result_id' => $id,
                'page_number' => $pageNumber,
                'rotation_degree' => $rotationDegree,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error applying rotation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Crop regions only (without OCR processing)
     *
     * @param int $id
     * @param array $regions
     * @param array|null $previewDimensions
     * @param array|null $pageRotations
     * @return array
     * @throws \Exception
     */
    public function cropRegions(int $id, array $regions, ?array $previewDimensions = null, ?array $pageRotations = null): array
    {
        try {
            $ocrResult = $this->ocrResultRepository->findById($id);
            
            // Update status to processing
            $this->ocrResultRepository->updateStatus($id, 'processing');
            
            // Group regions by page
            $regionsByPage = collect($regions)->groupBy('page')->toArray();
            
            // Process each page's regions separately (crop only)
            foreach ($regionsByPage as $page => $pageRegions) {
                // Get specific rotation for this page
                $pageRotation = isset($pageRotations[$page]) ? (int)$pageRotations[$page] : 0;
                
                \App\Jobs\CropRegions::dispatch($id, $pageRegions, (int)$page, $previewDimensions, $pageRotation);
            }

            return [
                'success' => true,
                'message' => 'Cropping selected regions',
                'status' => 'processing'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error cropping regions: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Get crop preview data
     *
     * @param int $id
     * @return array
     */
    public function getCropPreview(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if ($ocrResult->status !== 'awaiting_confirmation') {
            return [
                'success' => false,
                'message' => 'Crop preview not available',
                'status' => $ocrResult->status
            ];
        }

        return [
            'success' => true,
            'ocr_result' => $ocrResult,
            'cropped_images' => $ocrResult->cropped_images ?? [],
            'message' => 'Crop preview ready'
        ];
    }

    /**
     * Confirm crop and proceed with OCR processing
     *
     * @param int $id
     * @return array
     */
    public function confirmCrop(int $id): array
    {
        try {
            \Log::info('OcrService confirmCrop started for ID: ' . $id);
            
            // Use database transaction with row locking to prevent race condition
            return \DB::transaction(function () use ($id) {
                // Lock the row for update to prevent concurrent modifications
                $ocrResult = OcrResult::where('id', $id)->lockForUpdate()->first();
                
                if (!$ocrResult) {
                    \Log::error('OCR Result not found for ID: ' . $id);
                    return [
                        'success' => false,
                        'message' => 'OCR result not found',
                        'status' => 'error'
                    ];
                }
                
                \Log::info('OCR Result found: ', [
                    'id' => $ocrResult->id,
                    'status' => $ocrResult->status,
                    'cropped_images_count' => count($ocrResult->cropped_images ?? [])
                ]);
                
                if ($ocrResult->status !== 'awaiting_confirmation') {
                    \Log::warning('Cannot confirm crop - wrong status: ' . $ocrResult->status);
                    return [
                        'success' => false,
                        'message' => 'Cannot confirm crop at this stage',
                        'status' => $ocrResult->status
                    ];
                }

                // Update status to processing for OCR
                $ocrResult->update(['status' => 'processing']);
                \Log::info('Status updated to processing');

                // Get cropped images and process them with OCR
                $croppedImages = $ocrResult->cropped_images ?? [];
                \Log::info('Cropped images found: ' . count($croppedImages));
                
                // Group cropped images by page for OCR processing
                $imagesByPage = collect($croppedImages)->groupBy('page')->toArray();
                \Log::info('Images grouped by page: ', array_keys($imagesByPage));
                
                foreach ($imagesByPage as $page => $pageImages) {
                    \Log::info('Processing page: ' . $page . ' with ' . count($pageImages) . ' images');
                    
                    // Convert cropped images back to region format for ProcessRegions job
                    $regions = [];
                    foreach ($pageImages as $image) {
                        $regions[] = [
                            'id' => $image['region_id'],
                            'x' => $image['coordinates']['x'],
                            'y' => $image['coordinates']['y'],
                            'width' => $image['coordinates']['width'],
                            'height' => $image['coordinates']['height'],
                            'page' => $image['page']
                        ];
                    }
                    
                    \Log::info('Regions prepared for page ' . $page . ': ', $regions);
                    
                    // UPDATED: Removed page rotation logic since rotation is already handled in previous functions
                    
                    // Dispatch OCR processing job
                    \Log::info('Dispatching ProcessRegions job for page: ' . $page);
                    ProcessRegions::dispatch($id, $regions, (int)$page, null);
                }

                \Log::info('All ProcessRegions jobs dispatched successfully');

                return [
                    'success' => true,
                    'message' => 'Processing OCR on confirmed crops',
                    'status' => 'processing'
                ];
            }); // End of DB transaction
        } catch (\Exception $e) {
            \Log::error('Error in confirmCrop: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error confirming crop: ' . $e->getMessage(),
                'status' => 'error'
            ];
        }
    }
}