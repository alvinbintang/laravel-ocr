<?php

namespace App\Services;

use App\Jobs\ProcessOcr;
use App\Jobs\ProcessRegions;
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
    public function processPdfUpload(UploadedFile $pdfFile, string $documentType = 'RAB'): array
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
            'document_type' => $documentType,
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
            'ocr_result' => $ocrResult
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
     * @return array
     * @throws \Exception
     */
    public function processRegions(int $id, array $regions, ?array $previewDimensions = null, ?int $pageRotation = null): array
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
     * Prepare CSV export data
     *
     * @param int $id
     * @return array
     */
    public function prepareCsvExportData(int $id): array
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        $csvData = [];
        foreach ($ocrResult->ocr_results as $result) {
            $csvData[] = [
                $result['page'] ?? 1,
                $result['region_id'] ?? '',
                $result['coordinates']['x'] ?? 0,
                $result['coordinates']['y'] ?? 0,
                $result['coordinates']['width'] ?? 0,
                $result['coordinates']['height'] ?? 0,
                $result['text'] ?? ''
            ];
        }

        return [
            'filename' => pathinfo($ocrResult->filename, PATHINFO_FILENAME) . '_ocr_results.csv',
            'headers' => ['Page', 'Region ID', 'X', 'Y', 'Width', 'Height', 'Text'],
            'data' => $csvData
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
}