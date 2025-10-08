<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\OcrExtractRequest;
use App\Http\Requests\ProcessRegionsRequest;
use App\Services\Admin\OcrService;

class OcrController extends Controller
{
    protected $ocrService;

    public function __construct(OcrService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    public function index()
    {
        $ocrResults = $this->ocrService->getAllOcrResults(); // UPDATED: changed from getAllResults()
        return view('ocr.upload', ['ocrResults' => $ocrResults]);
    }

    public function extract(OcrExtractRequest $request)
    {
        $result = $this->ocrService->processPdfUpload($request->file('pdf'), $request->input('document_type')); // UPDATED: pass document_type
        
        return redirect()->route('ocr.preview', ['id' => $result['ocr_result_id']]) // UPDATED: use correct key
            ->with('success', $result['message']); // UPDATED: use message from result
    }

    public function preview($id)
    {
        $previewData = $this->ocrService->checkPreviewStatus($id); // UPDATED: changed from getPreviewStatus()
        
        // If still processing or error, redirect to status page
        if (!$previewData['can_preview']) { // UPDATED: use correct key
            return redirect()->route('ocr.status', ['id' => $id])
                ->with('info', $previewData['message']); // UPDATED: use message from result
        }

        return view('ocr.preview', ['ocrResult' => $previewData['ocr_result']]); // UPDATED: use correct key
    }

    public function status($id)
    {
        $statusData = $this->ocrService->getStatusInfo($id); // UPDATED: get array result
        return view('ocr.status', ['ocrResult' => $statusData['ocr_result']]); // UPDATED: use correct key
    }

    // ADDED: API endpoint for status checking
    public function statusCheck($id)
    {
        $statusData = $this->ocrService->getStatusInfo($id);
        return response()->json([
            'status' => $statusData['status'], // UPDATED: access array key
            'filename' => $statusData['filename'] // UPDATED: access array key
        ]);
    }

    public function processRegions(ProcessRegionsRequest $request, $id)
    {
        $result = $this->ocrService->processRegions( // UPDATED: changed from processSelectedRegions()
            $id, 
            $request->regions, 
            $request->input('previewDimensions'),
            $request->input('pageRotation') // ADDED: Pass page rotation to service
        );

        if ($result['success']) { // UPDATED: check success from result
            return response()->json([
                'message' => $result['message'],
                'status' => $result['status'],
                'success' => true
            ]);
        } else {
            return response()->json([
                'message' => $result['message'],
                'status' => $result['status'],
                'success' => false
            ], 500);
        }
    }

    public function showResult($id)
    {
        $resultData = $this->ocrService->getOcrResult($id); // UPDATED: changed from getProcessingResult()
        
        if ($resultData['status'] === 'success') { // UPDATED: check for 'success' instead of 'done'
            return response()->json([
                'status' => 'success',
                'results' => $resultData['results']
            ]);
        }

        return response()->json([
            'status' => $resultData['status'],
            'message' => $resultData['message'] ?? ($resultData['status'] === 'error' 
                ? 'Error processing regions' 
                : 'Still processing') // UPDATED: use message from result or fallback
        ]);
    }
    
    /**
     * Export OCR results to JSON format
     */
    public function exportJson($id)
    {
        try {
            $this->ocrService->isReadyForExport($id); // UPDATED: this throws exception if not ready
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        
        $exportData = $this->ocrService->prepareJsonExportData($id); // UPDATED: changed from prepareJsonExport()
        $filename = pathinfo($exportData['document']['filename'], PATHINFO_FILENAME) . '_ocr_results.json'; // UPDATED: create filename
        
        return response()->json($exportData)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Type', 'application/json');
    }
    
    /**
     * Export OCR results to CSV format
     */
    public function exportCsv($id)
    {
        try {
            $this->ocrService->isReadyForExport($id); // UPDATED: this throws exception if not ready
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        
        $exportData = $this->ocrService->prepareCsvExportData($id); // UPDATED: changed from prepareCsvExport()
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $exportData['filename'] . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($exportData) {
            $file = fopen('php://output', 'w');
            
            // Add CSV header
            fputcsv($file, $exportData['headers']); // UPDATED: use headers from export data
            
            // Add data rows
            foreach ($exportData['data'] as $row) { // UPDATED: data is already formatted
                fputcsv($file, $row);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
    
    public function saveRotations(Request $request, $id)
    {
        $rotations = $request->input('rotations');
        $result = $this->ocrService->savePageRotations($id, $rotations);
        
        return response()->json([
            'success' => $result['success'],
            'message' => $result['message']
        ]);
    }
}
