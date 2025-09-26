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

    public function processRegions(Request $request, $id)
    {
        $request->validate([
            'regions' => 'required|array',
            'regions.*.id' => 'required|string',
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
