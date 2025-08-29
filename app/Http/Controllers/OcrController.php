<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOcr; // ADDED
use App\Models\OcrResult; // ADDED
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OcrController extends Controller
{
    public function index()
    {
        $ocrResults = OcrResult::orderBy('created_at', 'desc')->get(); // ADDED
        return view('ocr.upload', ['ocrResults' => $ocrResults]); // UPDATED
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

        // Dispatch job ke antrian
        ProcessOcr::dispatch($ocrResult->id, Storage::path($path));

        return redirect()->route('ocr.result', ['id' => $ocrResult->id])->with('success', 'File Anda sedang diproses. Silakan cek hasilnya sebentar lagi.');
    }

    public function showResult($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        return view('ocr.result', ['ocrResult' => $ocrResult]);
    }
}
