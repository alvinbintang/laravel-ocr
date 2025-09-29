<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOcr;
use App\Jobs\ProcessRegions;
use App\Models\OcrResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OcrController extends Controller
{
    public function index()
    {
        $ocrResults = OcrResult::orderBy('created_at', 'desc')->get();
        return view('ocr.upload', ['ocrResults' => $ocrResults]);
    }

    public function extract(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:20480', // max 20MB
        ]);

        // Log upload information
        \Illuminate\Support\Facades\Log::info('PDF Upload Details', [
            'original_name' => $request->file('pdf')->getClientOriginalName(),
            'mime_type' => $request->file('pdf')->getMimeType(),
            'size' => $request->file('pdf')->getSize(),
            'error' => $request->file('pdf')->getError()
        ]);

        // Additional PDF validation
        $pdfContent = file_get_contents($request->file('pdf')->getRealPath());
        if (substr($pdfContent, 0, 4) !== '%PDF') {
            return back()->with('error', 'File bukan PDF yang valid. Pastikan file tidak corrupt.');
        }

        // Simpan PDF
        $path = $request->file('pdf')->store('ocr');

        // Simpan informasi ke database
        $ocrResult = OcrResult::create([
            'filename' => basename($path),
            'status' => 'pending',
        ]);

        // Dispatch job ke antrian untuk konversi PDF ke image
        ProcessOcr::dispatch($ocrResult->id, Storage::path($path));

        return redirect()->route('ocr.preview', ['id' => $ocrResult->id])
            ->with('success', 'File sedang dikonversi. Silakan tunggu sebentar untuk memilih area.');
    }

    public function preview($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        
        // If still processing or error, redirect to status page
        if ($ocrResult->status === 'pending' || $ocrResult->status === 'processing') {
            return redirect()->route('ocr.status', ['id' => $id])
                ->with('info', 'File masih dalam proses konversi. Mohon tunggu sebentar.');
        }
        
        if ($ocrResult->status === 'error') {
            return redirect()->route('ocr.status', ['id' => $id])
                ->with('error', 'Terjadi kesalahan dalam memproses file.');
        }

        return view('ocr.preview', ['ocrResult' => $ocrResult]);
    }

    public function status($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        return view('ocr.status', ['ocrResult' => $ocrResult]);
    }

    // ADDED: API endpoint for status checking
    public function statusCheck($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        return response()->json([
            'status' => $ocrResult->status,
            'filename' => $ocrResult->filename
        ]);
    }

    public function processRegions(Request $request, $id)
    {
        try {
            $request->validate([
                'regions' => 'required|array',
                'regions.*.id' => 'required|integer',
                'regions.*.x' => 'required|numeric',
                'regions.*.y' => 'required|numeric',
                'regions.*.width' => 'required|numeric',
                'regions.*.height' => 'required|numeric',
                'regions.*.page' => 'required|integer|min:1', // UPDATED: Validate page for each region
            ]);

            $ocrResult = OcrResult::findOrFail($id);
            
            // Update status to processing
            $ocrResult->update(['status' => 'processing']);
            
            // Group regions by page
            $regionsByPage = collect($request->regions)->groupBy('page')->toArray();
            
            // Process each page's regions separately
            foreach ($regionsByPage as $page => $regions) {
                ProcessRegions::dispatch($ocrResult->id, $regions, (int)$page);
            }

            return response()->json([
                'message' => 'Processing selected regions',
                'status' => 'processing',
                'success' => true
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'success' => false
            ], 422);
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
        $ocrResult = OcrResult::findOrFail($id);
        
        if ($ocrResult->status === 'done') {
            return response()->json([
                'status' => 'success',
                'results' => $ocrResult->ocr_results
            ]);
        }

        return response()->json([
            'status' => $ocrResult->status,
            'message' => $ocrResult->status === 'error' 
                ? 'Error processing regions' 
                : 'Still processing'
        ]);
    }
    
    /**
     * Export OCR results to JSON format
     */
    public function exportJson($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        
        if ($ocrResult->status !== 'done') {
            return redirect()->back()->with('error', 'OCR belum selesai diproses.');
        }
        
        $filename = pathinfo($ocrResult->filename, PATHINFO_FILENAME) . '_ocr_results.json';
        
        $data = [
            'document' => [
                'filename' => $ocrResult->filename,
                'processed_at' => $ocrResult->updated_at->toIso8601String(),
                'total_pages' => $ocrResult->getTotalPages(),
            ],
            'ocr_results' => $ocrResult->ocr_results
        ];
        
        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Type', 'application/json');
    }
    
    /**
     * Export OCR results to CSV format
     */
    public function exportCsv($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        
        if ($ocrResult->status !== 'done') {
            return redirect()->back()->with('error', 'OCR belum selesai diproses.');
        }
        
        $filename = pathinfo($ocrResult->filename, PATHINFO_FILENAME) . '_ocr_results.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];
        
        $callback = function() use ($ocrResult) {
            $file = fopen('php://output', 'w');
            
            // Add CSV header
            fputcsv($file, ['Page', 'Region ID', 'X', 'Y', 'Width', 'Height', 'Text']);
            
            // Add data rows
            foreach ($ocrResult->ocr_results as $result) {
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
