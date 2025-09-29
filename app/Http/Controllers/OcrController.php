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
                'current_page' => 'sometimes|integer|min:1', // ADDED: Validate current page
            ]);

            $ocrResult = OcrResult::findOrFail($id);
            
            // UPDATED: Dispatch job with current page parameter
            $currentPage = $request->input('current_page', 1);
            ProcessRegions::dispatch($ocrResult->id, $request->regions, $currentPage);

            return response()->json([
                'message' => 'Processing selected regions',
                'status' => 'processing'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error processing regions: ' . $e->getMessage(),
                'status' => 'error'
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
}
