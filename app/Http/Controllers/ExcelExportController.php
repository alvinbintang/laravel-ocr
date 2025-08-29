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
    /**
     * Controller utama yang sekarang mendukung pembuatan multi-sheet.
     */
    public function exportToExcel($id)
    {
        $ocrResult = OcrResult::findOrFail($id);
        
        if ($ocrResult->status !== 'done' || empty($ocrResult->text)) {
            return redirect()->back()->with('error', 'Data OCR belum selesai diproses atau tidak ada hasil.');
        }

        // V3 Parser akan mengembalikan array dari setiap RAB yang ditemukan
        $allRabs = $this->parseOcrDataV3($ocrResult->text); 
        
        if (empty($allRabs)) {
            return redirect()->back()->with('error', 'Tidak ada data RAB yang dapat dikenali dari dokumen.');
        }

        $spreadsheet = new Spreadsheet();
        
        foreach ($allRabs as $index => $rabData) {
            if ($index > 0) {
                $spreadsheet->createSheet();
            }
            $sheet = $spreadsheet->getSheet($index);

            // Beri nama sheet dari nama kegiatan (dipersingkat agar valid)
            $sheetTitle = 'RAB ' . ($index + 1);
            if (!empty($rabData['kegiatan'])) {
                // Ambil beberapa kata pertama dari kegiatan untuk nama sheet
                 $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $rabData['kegiatan']);
                 $sheetTitle = substr($cleanTitle, 0, 25); // Batasi panjang nama sheet
            }
            $sheet->setTitle($sheetTitle);

            // Panggil fungsi helper untuk mengisi data ke sheet
            $this->populateSheet($sheet, $rabData);
        }

        // Aktifkan sheet pertama saat file dibuka
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'RAB_Lengkap_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function cleanNumber($string) {
        $string = str_replace('.', '', $string);
        $string = str_replace(',', '.', $string);
        return preg_replace('/[^0-9.]/', '', $string);
    }

    /**
     * Template kosong untuk struktur data satu RAB.
     */
    private function getEmptyRabStructure() {
        return [
            'title'        => 'RENCANA ANGGARAN BIAYA (RAB)',
            'organization' => '', 'year' => '', 'type' => '',
            'bidang'       => '', 'sub_bidang' => '', 'kegiatan' => '',
            'waktu_pelaksanaan' => '', 'output' => '',
            'items'        => []
        ];
    }
    
    /**
     * ========================================================================
     * FUNGSI PARSING V3 - Mendukung Multi-Dokumen & Logika Kode/Uraian yang Benar
     * ========================================================================
     */
    private function parseOcrDataV3($text)
    {
        $lines = explode("\n", $text);
        $allRabs = [];
        $currentRab = null;
        $inItemSection = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Trigger untuk memulai dokumen RAB baru
            if (strpos($line, 'RENCANA ANGGARAN BIAYA') !== false) {
                if ($currentRab !== null) {
                    $allRabs[] = $currentRab; // Simpan RAB sebelumnya jika ada
                }
                $currentRab = $this->getEmptyRabStructure();
                $inItemSection = false;
                continue;
            }

            if ($currentRab === null || empty($line) || strpos(strtolower($line), 'halaman') !== false) {
                continue;
            }

            // Trigger untuk memulai bagian tabel item
            if (preg_match('/KODE\s+URAIAN/i', $line) || preg_match('/^\d\.\s+BELANJA/i', $line)) {
                $inItemSection = true;
            }

            if (!$inItemSection) {
                // --- Parsing Header ---
                if (strpos($line, 'PEMERINTAH DESA') !== false) $currentRab['organization'] = $line;
                elseif (strpos($line, 'TAHUN ANGGARAN') !== false) $currentRab['year'] = $line;
                else {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $key = strtolower(trim($parts[0]));
                        $value = trim($parts[1]);

                        if (strpos($key, 'jenis apbdes') !== false) $currentRab['type'] = $value;
                        elseif (strpos($key, 'bidang') !== false && strpos($key, 'sub') === false) $currentRab['bidang'] = $value;
                        elseif (strpos($key, 'sub bidang') !== false) $currentRab['sub_bidang'] = $value;
                        elseif (strpos($key, 'kegiatan') !== false) $currentRab['kegiatan'] = $value;
                        elseif (strpos($key, 'waktu pel') !== false) $currentRab['waktu_pelaksanaan'] = $value;
                        elseif (strpos($key, 'output') !== false || strpos($key, 'keluaran') !== false) $currentRab['output'] = $value;
                    }
                }
            } else {
                 // --- Parsing Tabel Item (Logika V3) ---
                 
                // Pola 1: Baris Rincian (dimulai dengan '01.', '02.' dll. dan diakhiri 2 angka)
                if (preg_match('/^(\d{2}\.)\s+(.*?)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $full_description = trim($matches[1] . ' ' . $matches[2]);
                    // cari volume di dalam deskripsi
                    preg_match('/(.*?)\s+((?:\d[\d\s.,]*)\s*[\w\s()x]+)$/', $full_description, $desc_matches);
                    
                    $uraian = $full_description;
                    $volume = '';
                    if(isset($desc_matches[2])){
                        $uraian = trim($desc_matches[1]);
                        $volume = trim($desc_matches[2]);
                    }
                    
                    $currentRab['items'][] = [
                        'code' => '', // KODE KOSONG UNTUK RINCIAN
                        'description' => $uraian,
                        'volume' => $volume,
                        'unit_price' => $this->cleanNumber($matches[3]),
                        'amount' => $this->cleanNumber($matches[4]),
                        'is_detail' => true
                    ];
                // Pola 2: Baris Kategori (dimulai dengan kode rekening dan diakhiri 1 angka)
                } elseif (preg_match('/^(\d[\d\.]*\.?)\s+(.*?)\s+([\d.,]+)$/', $line, $matches)) {
                     $code = trim($matches[1]);
                     $description = trim($matches[2]);
                     if (in_array(strtoupper($description), ['URAIAN', 'JUMLAH (RP)'])) continue;

                     $currentRab['items'][] = [
                         'code' => $code,
                         'description' => $description,
                         'volume' => '', 'unit_price' => '',
                         'amount' => $this->cleanNumber($matches[3]),
                         'is_detail' => false
                     ];
                }
            }
        }
        
        // Jangan lupa simpan RAB terakhir setelah loop selesai
        if ($currentRab !== null) {
            $allRabs[] = $currentRab;
        }

        return $allRabs;
    }

    /**
     * Fungsi helper untuk mengisi data ke sebuah worksheet.
     * Menerapkan logika Kode/Uraian yang benar.
     */
    private function populateSheet($sheet, $data)
    {
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(55);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        $row = 1;
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['title'])->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
        
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['organization'])->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['year'])->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row+=2;
        
        $infoStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Bidang')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['bidang'])->mergeCells('C'.$row.':E'.$row); $row++;
        $sheet->setCellValue('A'.$row, 'Sub Bidang')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['sub_bidang'])->mergeCells('C'.$row.':E'.$row); $row++;
        $sheet->setCellValue('A'.$row, 'Kegiatan')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['kegiatan'])->mergeCells('C'.$row.':E'.$row); $row++;
        $sheet->setCellValue('A'.$row, 'Waktu Pelaksanaan')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['waktu_pelaksanaan'])->mergeCells('C'.$row.':E'.$row); $row++;
        $sheet->setCellValue('A'.$row, 'Output/Keluaran')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['output'])->mergeCells('C'.$row.':E'.$row); $row++;
        $sheet->getStyle('A'.$infoStartRow.':E'.($row-1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        $row++;

        $tableStartRow = $row;
        $headerStyle = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $sheet->mergeCells('C'.$row.':E'.$row)->setCellValue('C'.$row, 'ANGGARAN');
        $sheet->setCellValue('A'.$row, 'KODE')->mergeCells('A'.$row.':A'.($row+1));
        $sheet->setCellValue('B'.$row, 'URAIAN')->mergeCells('B'.$row.':B'.($row+1));
        $row++;
        $sheet->setCellValue('C'.$row, 'VOLUME')->setCellValue('D'.$row, 'HARGA SATUAN')->setCellValue('E'.$row, 'JUMLAH');
        $sheet->getStyle('A'.$tableStartRow.':E'.$row)->applyFromArray($headerStyle);
        $row++;
        $sheet->setCellValue('A'.$row, '1')->setCellValue('B'.$row, '2')->setCellValue('C'.$row, '3')->setCellValue('D'.$row, '4')->setCellValue('E'.$row, '5');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        foreach ($data['items'] as $item) {
            // --- INI ADALAH LOGIKA KUNCI YANG DIPERBAIKI ---
            if ($item['is_detail']) {
                $sheet->setCellValue('A' . $row, ''); // Kolom KODE dikosongkan
                $sheet->setCellValue('B' . $row, $item['description']); // Uraian lengkap termasuk '01.'
            } else {
                $sheet->setCellValue('A' . $row, $item['code']); // Kode Rekening
                $sheet->setCellValue('B' . $row, $item['description']);
            }
            
            $sheet->setCellValue('C' . $row, $item['volume']);
            if (!empty($item['unit_price'])) $sheet->setCellValue('D' . $row, (float)$item['unit_price'])->getStyle('D'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            if (!empty($item['amount'])) $sheet->setCellValue('E' . $row, (float)$item['amount'])->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
            
            $sheet->getStyle('A'.$row.':E'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
            if (!$item['is_detail']) $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
             if ($item['is_detail'])  $sheet->getStyle('B'.$row)->getAlignment()->setIndent(2);
            $row++;
        }
        $sheet->getStyle('A'.$tableStartRow.':E'.($row-1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
    }
}