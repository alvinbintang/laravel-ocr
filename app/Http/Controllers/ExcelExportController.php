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

        $parsedData = $this->parseOcrData($ocrResult->text);
        $spreadsheet = $this->createExcelFile($parsedData);
        
        $filename = 'RAB_' . pathinfo($ocrResult->filename, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Set headers untuk download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Fungsi parseOcrData yang telah diperbaiki menjadi lebih fleksibel dan cerdas.
     * Mampu menangani format header dinamis dan item tabel yang bervariasi.
     */
    private function parseOcrData($text)
    {
        $lines = explode("\n", $text);
        $data = [
            'title' => 'RENCANA ANGGARAN BIAYA (RAB)',
            'organization' => '',
            'year' => '',
            'type' => 'APBDes Awal', // Default value, bisa di-override
            'bidang' => '',
            'sub_bidang' => '',
            'kegiatan' => '',
            'waktu_pelaksanaan' => '',
            'output' => '',
            'items' => []
        ];
        
        $inItemSection = false;
        $lastKey = null; // Variabel state untuk parsing header

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=== Halaman ===') !== false || preg_match('/^\d{2}\/\d{2}\/\d{4}/', $line)) continue;

            // Stop parsing header jika sudah masuk bagian tabel
            if (preg_match('/KODE\s+URAIAN/', $line)) {
                $inItemSection = true;
                continue;
            }

            if (!$inItemSection) {
                // Parsing Header Utama
                if (strpos($line, 'PEMERINTAH DESA') !== false) {
                    $data['organization'] = $line;
                } elseif (strpos($line, 'TAHUN ANGGARAN') !== false) {
                    $data['year'] = $line;
                } elseif (strpos($line, 'Jenis APBDes') !== false) {
                    $parts = explode(':', $line);
                    $data['type'] = trim($parts[1] ?? 'APBDes Awal');
                }
                // --- Logika Parsing Header Dinamis ---
                elseif (stripos($line, 'Bidang') !== false && stripos($line, 'Sub') === false) {
                    $parts = preg_split('/:\s*/', $line, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) $data['bidang'] = trim($parts[1]);
                    else $lastKey = 'bidang';
                } elseif (stripos($line, 'Sub Bidang') !== false) {
                    $parts = preg_split('/:\s*/', $line, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) $data['sub_bidang'] = trim($parts[1]);
                    else $lastKey = 'sub_bidang';
                } elseif (stripos($line, 'Kegiatan') !== false) {
                    $parts = preg_split('/:\s*/', $line, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) $data['kegiatan'] = trim($parts[1]);
                    else $lastKey = 'kegiatan';
                } elseif (stripos($line, 'Waktu Pel') !== false) {
                    $parts = preg_split('/:\s*/', $line, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) $data['waktu_pelaksanaan'] = trim($parts[1]);
                    else $lastKey = 'waktu_pelaksanaan';
                } elseif (stripos($line, 'Output') !== false || stripos($line, 'Keluaran') !== false) {
                     $parts = preg_split('/:\s*/', $line, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) $data['output'] = trim($parts[1]);
                    else $lastKey = 'output';
                }
                // Jika $lastKey sudah di-set, baris ini adalah nilainya
                elseif ($lastKey !== null) {
                    $data[$lastKey] = $line;
                    $lastKey = null; // Reset setelah mendapatkan nilai
                }

            } else {
                // --- Logika Parsing Item Tabel ---

                // Hapus noise OCR
                $cleanedLine = preg_replace('/\s+(DDS|pps|\|)\s+/', ' ', $line);

                // Pola 1: Baris rincian lengkap (Kode, Uraian, Volume, Harga Satuan, Jumlah)
                // Contoh: 01. Buku folio besar 9 buah 20.000,00 180.000,00
                // Contoh: 01. Honor kader... (13 org x 12 bulan) 156 OK 50.000,00 7.800.000,00
                if (preg_match('/^(\d{2}\.|\d+\.)\s+(.*?)\s+([\d,]+\s+\w+.*?)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $data['items'][] = [
                        'code' => trim($matches[1]),
                        'description' => trim($matches[2]),
                        'volume' => trim($matches[3]),
                        'unit_price' => str_replace(['.', ','], ['', '.'], $matches[4]),
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[5])
                    ];
                }
                // Pola 2: Baris Kategori/Sub-total (Kode, Uraian, Jumlah)
                // Contoh: 5.2.1. Belanja Barang Perlengkapan 64.822.600,00
                elseif (preg_match('/^([\d\.]+\.?) \s+ (.*?) \s+ ([\d.,]+)$/', $line, $matches)) {
                    $description = trim($matches[2]);
                    // Jangan masukkan baris header tabel lagi
                    if ($description === 'URAIAN') continue;

                    $data['items'][] = [
                        'code' => trim($matches[1]),
                        'description' => $description,
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[3])
                    ];
                }
            }
        }
        
        return $data;
    }
    
    private function createExcelFile($data)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set column widths agar sesuai dengan format yang diinginkan
        $sheet->getColumnDimension('A')->setWidth(12); // Kode
        $sheet->getColumnDimension('B')->setWidth(55); // Uraian
        $sheet->getColumnDimension('C')->setWidth(20); // Volume
        $sheet->getColumnDimension('D')->setWidth(20); // Harga Satuan
        $sheet->getColumnDimension('E')->setWidth(20); // Jumlah
        
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
        $row++;
        
        // --- Informasi Kegiatan ---
        $row++; // Spasi
        $infoStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Jenis APBDes');
        $sheet->setCellValue('B'.$row, ': ' . $data['type']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row+=2; // Spasi
        
        $sheet->setCellValue('A'.$row, 'Bidang');
        $sheet->setCellValue('B'.$row, ': ' . $data['bidang']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Sub Bidang');
        $sheet->setCellValue('B'.$row, ': ' . $data['sub_bidang']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Kegiatan');
        $sheet->setCellValue('B'.$row, ': ' . $data['kegiatan']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Waktu Pelaksanaan');
        $sheet->setCellValue('B'.$row, ': ' . $data['waktu_pelaksanaan']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row++;
        
        $sheet->setCellValue('A'.$row, 'Output/Keluaran');
        $sheet->setCellValue('B'.$row, ': ' . $data['output']);
        $sheet->mergeCells('B'.$row.':E'.$row);
        $row++;
        
        // Border untuk seksi informasi
        $infoRange = 'A' . $infoStartRow . ':E' . ($row - 1);
        $styleArray = ['borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM,],],];
        $sheet->getStyle($infoRange)->applyFromArray($styleArray);
        $row++;

        // --- Header Tabel Data ---
        $tableStartRow = $row;
        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,],]
        ];
        
        $sheet->mergeCells('C'.$row.':E'.$row)->setCellValue('C'.$row, 'ANGGARAN');
        $sheet->setCellValue('A'.$row, 'KODE')->mergeCells('A'.$row.':A'.($row+1));
        $sheet->setCellValue('B'.$row, 'URAIAN')->mergeCells('B'.$row.':B'.($row+1));
        $row++;
        $sheet->setCellValue('C'.$row, 'VOLUME');
        $sheet->setCellValue('D'.$row, 'HARGA SATUAN');
        $sheet->setCellValue('E'.$row, 'JUMLAH');
        $sheet->getStyle('A'.$tableStartRow.':E'.$row)->applyFromArray($headerStyle);
        $row++;

        $sheet->setCellValue('A'.$row, '1');
        $sheet->setCellValue('B'.$row, '2');
        $sheet->setCellValue('C'.$row, '3');
        $sheet->setCellValue('D'.$row, '4');
        $sheet->setCellValue('E'.$row, '5');
        $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($headerStyle);
        $row++;

        // --- Isi Tabel Data ---
        $itemStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,],],];
        
        foreach ($data['items'] as $item) {
            $isCategory = empty($item['volume']) && empty($item['unit_price']);
            
            $sheet->setCellValue('A' . $row, $item['code']);
            
            // Indentasi untuk rincian item (kode dimulai dengan '01.', '02.', dst.)
            if (preg_match('/^\d{2}\./', $item['code'])) {
                 $sheet->setCellValue('B' . $row, '  ' . $item['description']); // Tambah 2 spasi
            } else {
                 $sheet->setCellValue('B' . $row, $item['description']);
            }
           
            $sheet->setCellValue('C' . $row, $item['volume']);
            
            // Formatting Angka
            if (!empty($item['unit_price'])) {
                $sheet->setCellValue('D' . $row, (float)$item['unit_price']);
                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            if (!empty($item['amount'])) {
                $sheet->setCellValue('E' . $row, (float)$item['amount']);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            
            // Styling
            $sheet->getStyle('A'.$row.':E'.$row)->applyFromArray($itemStyle);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
            $sheet->getStyle('C'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D'.$row.':E'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Bold untuk kategori
            if ($isCategory) {
                $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
            }
            
            $row++;
        }
        
        // Border luar untuk seluruh tabel
        $tableRange = 'A' . $tableStartRow . ':E' . ($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        
        return $spreadsheet;
    }
}