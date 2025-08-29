<?php

namespace App\Http\Controllers;

use App\Models\OcrResult;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelExportController extends Controller
{
    public function exportToExcel($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        
        if ($ocrResult->status !== 'done' || empty($ocrResult->text)) {
            return redirect()->back()->with('error', 'Data OCR belum selesai diproses atau tidak ada hasil.');
        }

        // --- Perubahan Kunci ada di sini ---
        $parsedData = $this->parseOcrDataV2($ocrResult->text); 
        
        $spreadsheet = $this->createExcelFile($parsedData);
        
        $filename = 'RAB_' . pathinfo($ocrResult->filename, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Membersihkan string angka dari format mata uang.
     * Contoh: "1.234.567,00" menjadi "1234567.00"
     */
    private function cleanNumber($string)
    {
        // Hapus semua titik pemisah ribuan
        $string = str_replace('.', '', $string);
        // Ubah koma desimal menjadi titik
        $string = str_replace(',', '.', $string);
        return preg_replace('/[^0-9.]/', '', $string);
    }

    /**
     * ========================================================================
     * FUNGSI PARSING BARU (V2) - Lebih Kuat dan Fleksibel
     * ========================================================================
     */
    private function parseOcrDataV2($text)
    {
        $lines = explode("\n", $text);
        $data = [
            'title'        => 'RENCANA ANGGARAN BIAYA (RAB)',
            'organization' => '', 'year' => '', 'type' => '',
            'bidang'       => '', 'sub_bidang' => '', 'kegiatan' => '',
            'waktu_pelaksanaan' => '', 'output' => '',
            'items'        => []
        ];

        $inItemSection = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos(strtolower($line), 'halaman') !== false || strpos(strtolower($line), 'printed by') !== false) {
                continue;
            }

            // Trigger untuk memulai bagian tabel
            if (preg_match('/^\d\.\s+BELANJA/i', $line) || preg_match('/KODE\s+URAIAN/i', $line)) {
                $inItemSection = true;
            }
            
            if (!$inItemSection) {
                // --- Parsing Header ---
                if (strpos($line, 'PEMERINTAH DESA') !== false) $data['organization'] = $line;
                elseif (strpos($line, 'TAHUN ANGGARAN') !== false) $data['year'] = $line;
                else {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $key = strtolower(trim($parts[0]));
                        $value = trim($parts[1]);

                        if (strpos($key, 'jenis apbdes') !== false) $data['type'] = $value;
                        elseif (strpos($key, 'bidang') !== false && strpos($key, 'sub') === false) $data['bidang'] = $value;
                        elseif (strpos($key, 'sub bidang') !== false) $data['sub_bidang'] = $value;
                        elseif (strpos($key, 'kegiatan') !== false) $data['kegiatan'] = $value;
                        elseif (strpos($key, 'waktu pel') !== false) $data['waktu_pelaksanaan'] = $value;
                        elseif (strpos($key, 'output') !== false || strpos($key, 'keluaran') !== false) $data['output'] = $value;
                    }
                }
            } else {
                // --- Parsing Tabel Item (Logika Baru) ---

                // Pola 1: Baris Rincian (diakhiri 2 angka: Harga Satuan, Jumlah)
                if (preg_match('/^(.*?)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $frontPart = trim($matches[1]);
                    $unitPrice = $this->cleanNumber($matches[2]);
                    $amount = $this->cleanNumber($matches[3]);
                    
                    // Sekarang parse bagian depan untuk kode, uraian, dan volume
                    if (preg_match('/^(\d{2}\.|\d+\.)\s+(.*?)\s+((?:\d[\d\s.,]*)\s*[\w\s()x]+)$/', $frontPart, $frontMatches)) {
                        $code = trim($frontMatches[1]);
                        $description = trim(str_replace(['DDS', 'pps', '|'], '', $frontMatches[2]));
                        $volume = trim(str_replace(['DDS', 'pps', '|'], '', $frontMatches[3]));

                        $data['items'][] = [
                            'code' => $code,
                            'description' => $description,
                            'volume' => $volume,
                            'unit_price' => $unitPrice,
                            'amount' => $amount
                        ];
                    }
                // Pola 2: Baris Kategori (diakhiri 1 angka: Jumlah)
                } elseif (preg_match('/^(.*?)\s+([\d.,]+)$/', $line, $matches)) {
                     $frontPart = trim($matches[1]);
                     $amount = $this->cleanNumber($matches[2]);

                     // Parse bagian depan untuk kode dan uraian
                     if (preg_match('/^([\d\.]+\.?)\s+(.*)$/', $frontPart, $frontMatches)) {
                         $code = trim($frontMatches[1]);
                         $description = trim($frontMatches[2]);

                         // Hindari memasukkan header tabel
                         if (in_array(strtoupper($description), ['URAIAN', 'JUMLAH (RP)'])) continue;

                         $data['items'][] = [
                             'code' => $code,
                             'description' => $description,
                             'volume' => '',
                             'unit_price' => '',
                             'amount' => $amount
                         ];
                     }
                }
            }
        }
        return $data;
    }

    private function createExcelFile($data)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(55);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        // --- Header Utama ---
        $row = 1;
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['title']);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['organization']);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['year']);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row+=2;
        
        // --- Informasi Kegiatan (Layout Sesuai Contoh Awal) ---
        $infoStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Jenis APBDes');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['type']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row+=2;
        
        $sheet->setCellValue('A'.$row, 'Bidang');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['bidang']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Sub Bidang');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['sub_bidang']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Kegiatan');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['kegiatan']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Waktu Pelaksanaan');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['waktu_pelaksanaan']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Output/Keluaran');
        $sheet->setCellValue('B'.$row, ':');
        $sheet->setCellValue('C'.$row, $data['output']);
        $sheet->mergeCells('C'.$row.':E'.$row);
        $row++;
        
        $sheet->getStyle('A'.$infoStartRow.':E'.($row-1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        $row++;

        // --- Header Tabel Data ---
        $tableStartRow = $row;
        // Styles
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $numberStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        // Content
        $sheet->mergeCells('C'.$row.':E'.$row)->setCellValue('C'.$row, 'ANGGARAN');
        $sheet->setCellValue('A'.$row, 'KODE')->mergeCells('A'.$row.':A'.($row+1));
        $sheet->setCellValue('B'.$row, 'URAIAN')->mergeCells('B'.$row.':B'.($row+1));
        $row++;
        $sheet->setCellValue('C'.$row, 'VOLUME');
        $sheet->setCellValue('D'.$row, 'HARGA SATUAN');
        $sheet->setCellValue('E'.$row, 'JUMLAH');
        $sheet->getStyle('A'.$tableStartRow.':E'.$row)->applyFromArray($headerStyle);
        $row++;
        $sheet->setCellValue('A'.$row, '1')->setCellValue('B'.$row, '2')->setCellValue('C'.$row, '3')->setCellValue('D'.$row, '4')->setCellValue('E'.$row, '5');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($numberStyle)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        // --- Isi Tabel Data ---
        foreach ($data['items'] as $item) {
            $isCategory = empty($item['volume']) && empty($item['unit_price']);
            
            $sheet->setCellValue('A' . $row, $item['code']);
            $sheet->setCellValue('B' . $row, $item['description']);
            $sheet->setCellValue('C' . $row, $item['volume']);
            
            if (!empty($item['unit_price'])) {
                $sheet->setCellValue('D' . $row, (float)$item['unit_price']);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            if (!empty($item['amount'])) {
                $sheet->setCellValue('E' . $row, (float)$item['amount']);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            
            // Styling
            $sheet->getStyle('A'.$row.':E'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
            
            // Bold & Indentasi
            $codePrefix = explode('.', $item['code'])[0];
            $level = substr_count($item['code'], '.');
            if (strlen($codePrefix) > 1 && $level > 1) { // Kode seperti 5.2.1.01
                $sheet->getStyle('B'.$row)->getAlignment()->setIndent(2);
            } elseif(preg_match('/^\d{2}\./', $item['code'])) { // Kode seperti 01., 02.
                 $sheet->getStyle('B'.$row)->getAlignment()->setIndent(4);
            }
            
            if ($isCategory) {
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
            }
            
            $row++;
        }
        
        $sheet->getStyle('A'.$tableStartRow.':E'.($row-1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        
        return $spreadsheet;
    }
}