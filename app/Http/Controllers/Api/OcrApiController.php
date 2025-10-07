<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\OcrExtractRequest;
use App\Http\Requests\ProcessRegionsRequest;
use App\Services\OcrService;
use App\Services\ExcelExportService;
use App\Http\Resources\OcrResultResource; // ADDED: OCR result resource
use App\Http\Resources\OcrResultCollection; // ADDED: OCR result collection
use Illuminate\Support\Facades\Validator;

class OcrApiController extends Controller
{
    protected $ocrService;
    protected $excelExportService;

    public function __construct(OcrService $ocrService, ExcelExportService $excelExportService)
    {
        $this->ocrService = $ocrService;
        $this->excelExportService = $excelExportService;
    }

    /**
     * Get all OCR results
     */
    public function index(): JsonResponse
    {
        try {
            $ocrResults = $this->ocrService->getAllOcrResults();
            
            return response()->json([
                'success' => true,
                'message' => 'OCR results retrieved successfully',
                'data' => new OcrResultCollection(OcrResultResource::collection($ocrResults)) // UPDATED: Use resource collection
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve OCR results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload and extract PDF
     */
    public function extract(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
            'document_type' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->ocrService->processPdfUpload(
                $request->file('pdf'), 
                $request->input('document_type')
            );
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'ocr_result_id' => $result['ocr_result_id'],
                    'status' => 'processing'
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process PDF upload',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get OCR result by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $statusData = $this->ocrService->getStatusInfo($id);
            
            return response()->json([
                'success' => true,
                'message' => 'OCR result retrieved successfully',
                'data' => new OcrResultResource($statusData['ocr_result']) // UPDATED: Use OcrResultResource
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OCR result not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get preview data
     */
    public function preview($id): JsonResponse
    {
        try {
            $previewData = $this->ocrService->checkPreviewStatus($id);
            
            return response()->json([
                'success' => true,
                'message' => $previewData['message'],
                'data' => [
                    'can_preview' => $previewData['can_preview'],
                    'ocr_result' => $previewData['ocr_result'] ? new OcrResultResource($previewData['ocr_result']) : null // UPDATED: Use OcrResultResource
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get preview data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status
     */
    public function status($id): JsonResponse
    {
        try {
            $statusData = $this->ocrService->getStatusInfo($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $statusData['status'],
                    'filename' => $statusData['filename'],
                    'ocr_result' => new OcrResultResource($statusData['ocr_result']) // UPDATED: Use OcrResultResource
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process regions
     */
    public function processRegions(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'regions' => 'required|array',
            'regions.*.x' => 'required|numeric',
            'regions.*.y' => 'required|numeric',
            'regions.*.width' => 'required|numeric',
            'regions.*.height' => 'required|numeric',
            'previewDimensions' => 'nullable|array',
            'pageRotation' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->ocrService->processRegions(
                $id, 
                $request->regions, 
                $request->input('previewDimensions'),
                $request->input('pageRotation')
            );

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'data' => [
                    'status' => $result['status']
                ]
            ], $result['success'] ? 200 : 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process regions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get processing result
     */
    public function result($id): JsonResponse
    {
        try {
            $resultData = $this->ocrService->getOcrResult($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $resultData['status'],
                    'message' => $resultData['message'] ?? null,
                    'results' => $resultData['results'] ?? null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get processing result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to JSON
     */
    public function exportJson($id): JsonResponse
    {
        try {
            $this->ocrService->isReadyForExport($id);
            $exportData = $this->ocrService->prepareJsonExportData($id);
            
            return response()->json([
                'success' => true,
                'message' => 'JSON export data retrieved successfully',
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export JSON',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to CSV
     */
    public function exportCsv($id): JsonResponse
    {
        try {
            $this->ocrService->isReadyForExport($id);
            $exportData = $this->ocrService->prepareCsvExportData($id);
            
            return response()->json([
                'success' => true,
                'message' => 'CSV export data retrieved successfully',
                'data' => [
                    'filename' => $exportData['filename'],
                    'headers' => $exportData['headers'],
                    'data' => $exportData['data']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export CSV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export to Excel
     */
    public function exportExcel($id): JsonResponse
    {
        try {
            $this->ocrService->isReadyForExport($id);
            
            // Get OCR result for Excel export
            $ocrResult = $this->ocrService->getOcrResultById($id);
            
            if (!$ocrResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'OCR result not found'
                ], 404);
            }

            // Generate Excel file and get download URL or base64
            $excelData = $this->excelExportService->exportToExcel($ocrResult);
            
            return response()->json([
                'success' => true,
                'message' => 'Excel export generated successfully',
                'data' => $excelData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save rotations
     */
    public function saveRotations(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rotations' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->ocrService->savePageRotations($id, $request->input('rotations'));
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save rotations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}