<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\OcrExtractRequest;
use App\Http\Requests\ProcessRegionsRequest;
use App\Services\OcrService;

class OcrController extends Controller
{
    protected $ocrService;

    public function __construct(OcrService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    public function index()
    {
        $ocrResults = $this->ocrService->getAllResults();
        return view('ocr.upload', ['ocrResults' => $ocrResults]);
    }

    public function extract(OcrExtractRequest $request)
    {
        $ocrResult = $this->ocrService->processPdfUpload($request->file('pdf'));
        
        return redirect()->route('ocr.preview', ['id' => $ocrResult->id])
            ->with('success', 'File sedang dikonversi. Silakan tunggu sebentar untuk memilih area.');
    }

    public function preview($id)
    {
        $previewData = $this->ocrService->getPreviewStatus($id);
        
        // If still processing or error, redirect to status page
        if ($previewData['status'] === 'pending' || $previewData['status'] === 'processing') {
            return redirect()->route('ocr.status', ['id' => $id])
                ->with('info', 'File masih dalam proses konversi. Mohon tunggu sebentar.');
        }
        
        if ($previewData['status'] === 'error') {
            return redirect()->route('ocr.status', ['id' => $id])
                ->with('error', 'Terjadi kesalahan dalam memproses file.');
        }

        return view('ocr.preview', ['ocrResult' => $previewData['result']]);
    }

    public function status($id)
    {
        $ocrResult = $this->ocrService->getStatusInfo($id);
        return view('ocr.status', ['ocrResult' => $ocrResult]);
    }

    // ADDED: API endpoint for status checking
    public function statusCheck($id)
    {
        $statusData = $this->ocrService->getStatusInfo($id);
        return response()->json([
            'status' => $statusData->status,
            'filename' => $statusData->filename
        ]);
    }

    public function processRegions(ProcessRegionsRequest $request, $id)
    {
        try {
            $this->ocrService->processSelectedRegions(
                $id, 
                $request->regions, 
                $request->input('previewDimensions')
            );

            return response()->json([
                'message' => 'Processing selected regions',
                'status' => 'processing',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing regions: ' . $e->getMessage(),
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function showResult($id)
    {
        $resultData = $this->ocrService->getProcessingResult($id);
        
        if ($resultData['status'] === 'done') {
            return response()->json([
                'status' => 'success',
                'results' => $resultData['results']
            ]);
        }

        return response()->json([
            'status' => $resultData['status'],
            'message' => $resultData['status'] === 'error' 
                ? 'Error processing regions' 
                : 'Still processing'
        ]);
    }
    
    /**
     * Export OCR results to JSON format
     */
    public function exportJson($id)
    {
        if (!$this->ocrService->isReadyForExport($id)) {
            return redirect()->back()->with('error', 'OCR belum selesai diproses.');
        }
        
        $exportData = $this->ocrService->prepareJsonExport($id);
        $filename = $exportData['filename'];
        
        return response()->json($exportData['data'])
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Type', 'application/json');
    }
    
    /**
     * Export OCR results to CSV format
     */
    public function exportCsv($id)
    {
        if (!$this->ocrService->isReadyForExport($id)) {
            return redirect()->back()->with('error', 'OCR belum selesai diproses.');
        }
        
        $exportData = $this->ocrService->prepareCsvExport($id);
        $filename = $exportData['filename'];
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($exportData) {
            $file = fopen('php://output', 'w');
            
            // Add CSV header
            fputcsv($file, ['Page', 'Region ID', 'X', 'Y', 'Width', 'Height', 'Text']);
            
            // Add data rows
            foreach ($exportData['data'] as $result) {
                fputcsv($file, [
                    $result['page'] ?? 1,
                    $result['region_id'] ?? '',
                    $result['coordinates']['x'] ?? 0,
                    $result['coordinates']['y'] ?? 0,
                    $result['coordinates']['width'] ?? 0,
                    $result['coordinates']['height'] ?? 0,
                    $result['text'] ?? ''
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}
