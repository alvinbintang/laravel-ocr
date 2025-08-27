<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Symfony\Component\Process\Process;

class OcrController extends Controller
{
    public function index()
    {
        return view('ocr.upload');
    }

    public function extract(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:20480', // max 20MB
        ]);

        // 1. Simpan PDF
        $path = $request->file('pdf')->store('ocr');
        $pdfPath = Storage::path($path);

        // 2. Konversi PDF ke Image (per halaman)
        $outputPrefix = Storage::path('ocr/tmp_'.uniqid());
        $process = new Process(['pdftoppm', '-png', $pdfPath, $outputPrefix]);
        $process->run();

        if (!$process->isSuccessful()) {
            return response()->json(['error' => $process->getErrorOutput()], 500);
        }

        // 3. Ambil semua gambar hasil konversi
        $files = glob($outputPrefix.'-*.png');
        $resultText = '';

        // 4. OCR setiap gambar
        foreach ($files as $img) {
            $text = (new TesseractOCR($img))
                ->lang('ind+eng') // bahasa Indonesia + Inggris
                ->run();

            $resultText .= "=== Halaman ===\n".$text."\n\n";
        }

        // 5. Return hasil teks
        return view('ocr.result', [
            'text' => $resultText
        ]);
    }
}
