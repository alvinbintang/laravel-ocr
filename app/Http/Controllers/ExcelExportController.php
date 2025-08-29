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
    
    private function parseOcrData($text)
    {
        $lines = explode("\n", $text);
        $data = [
            'title' => 'RENCANA ANGGARAN BIAYA (RAB)',
            'organization' => '',
            'year' => '',
            'type' => '',
            'bidang' => '',
            'sub_bidang' => '',
            'kegiatan' => '',
            'waktu_pelaksanaan' => '',
            'output' => '',
            'total_amount' => 0,
            'items' => []
        ];
        
        $inItemSection = false;
        $currentMainCode = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse header information
            if (strpos($line, 'PEMERINTAH DESA') !== false) {
                $data['organization'] = $line;
            } elseif (strpos($line, 'TAHUN ANGGARAN') !== false) {
                $data['year'] = $line;
            } elseif (strpos($line, 'APBDes') !== false && strpos($line, 'Jenis') !== false) {
                $data['type'] = $line;
            } elseif (preg_match('/^\d+\.\s*BIDANG/', $line)) {
                $data['bidang'] = $line;
            } elseif (preg_match('/^\d+\.\d+\.\s*Sub Bidang/', $line)) {
                $data['sub_bidang'] = $line;
            } elseif (preg_match('/^\d+\.\d+\.\d+/', $line) && strpos($line, 'Penyelenggaraan') !== false) {
                $data['kegiatan'] = $line;
            } elseif (strpos($line, 'Bulan') !== false && preg_match('/\d+\s*Bulan/', $line)) {
                $data['waktu_pelaksanaan'] = $line;
            } elseif (strpos($line, 'Terselenggaranya') !== false) {
                $data['output'] = $line;
            }
            
            // Detect start of items section
            if (strpos($line, 'BELANJA') !== false || preg_match('/^\d+\s+BELANJA/', $line)) {
                $inItemSection = true;
                
                // Parse main BELANJA line
                if (preg_match('/(\d+)\s+BELANJA\s+([\d.,]+)/', $line, $matches)) {
                    $data['items'][] = [
                        'code' => $matches[1],
                        'description' => 'BELANJA',
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[2])
                    ];
                }
                continue;
            }
            
            if ($inItemSection) {
                // Parse main category codes (like 2.02.02, 5.2.1., etc.)
                if (preg_match('/^(\d+\.\d+\.\d+)\s+(.+?)\s+([\d.,]+)$/', $line, $matches)) {
                    $currentMainCode = $matches[1];
                    $data['items'][] = [
                        'code' => $matches[1],
                        'description' => trim($matches[2]),
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[3])
                    ];
                }
                // Parse sub-category codes (like 5.2.1., 521.01, etc.)
                elseif (preg_match('/^(\d+\.\d+\.\d+\.|\d+\.\d+)\s+(.+?)\s+([\d.,]+)$/', $line, $matches)) {
                    $data['items'][] = [
                        'code' => $matches[1],
                        'description' => trim($matches[2]),
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[3])
                    ];
                }
                // Parse detailed items with volume and unit price
                elseif (preg_match('/^(\d+\.)\s*(.+?)\s+(\w+)\s+(\d+)\s+(\w+)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $data['items'][] = [
                        'code' => $matches[1],
                        'description' => trim($matches[2]),
                        'volume' => $matches[4] . ' ' . $matches[5],
                        'unit_price' => str_replace(['.', ','], ['', '.'], $matches[6]),
                        'amount' => str_replace(['.', ','], ['', '.'], $matches[7])
                    ];
                }
                // Parse items with just description, volume, unit, unit price, and amount
                elseif (preg_match('/^(.+?)\s+(\d+)\s+(\w+)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $description = trim($matches[1]);
                    // Skip if description starts with number (likely a code we already processed)
                    if (!preg_match('/^\d/', $description)) {
                        $data['items'][] = [
                            'code' => '',
                            'description' => $description,
                            'volume' => $matches[2] . ' ' . $matches[3],
                            'unit_price' => str_replace(['.', ','], ['', '.'], $matches[4]),
                            'amount' => str_replace(['.', ','], ['', '.'], $matches[5])
                        ];
                    }
                }
                // Parse category headers without amounts
                elseif (preg_match('/^(\d+)\s+(.+)$/', $line, $matches) && !preg_match('/[\d.,]+$/', $line)) {
                    $data['items'][] = [
                        'code' => $matches[1],
                        'description' => trim($matches[2]),
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => ''
                    ];
                }
            }
            
            // Parse total amount
            if (preg_match('/JUMLAH.*?([\d.,]+)/', $line, $matches)) {
                $totalStr = str_replace(['.', ','], ['', '.'], $matches[1]);
                if (is_numeric($totalStr)) {
                    $data['total_amount'] = floatval($totalStr);
                }
            }
        }
        
        return $data;
    }
    
    private function createExcelFile($data)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set column widths to match RAB format
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(60);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        $row = 1;
        
        // Create bordered header section like RAB format
        $headerStartRow = $row;
        
        // Title section with border
        $sheet->setCellValue('A' . $row, $data['title']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row++;
        
        $sheet->setCellValue('A' . $row, $data['organization']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        $sheet->setCellValue('A' . $row, $data['year']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        
        // Add border around header section
        $headerRange = 'A' . $headerStartRow . ':E' . ($row - 1);
        $sheet->getStyle($headerRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $row++;
        
        // Information section in bordered table format
        $infoStartRow = $row;
        
        // Jenis APBDes row
        $sheet->setCellValue('A' . $row, 'Jenis APBDes :');
        $sheet->setCellValue('B' . $row, $data['type']);
        $sheet->mergeCells('B' . $row . ':E' . $row);
        $row++;
        
        // Empty row for spacing
        $row++;
        
        // Bidang, Sub Bidang, Kegiatan section
        $sheet->setCellValue('A' . $row, 'Bidang');
        $sheet->setCellValue('B' . $row, ':');
        $sheet->setCellValue('C' . $row, $data['bidang']);
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Sub Bidang');
        $sheet->setCellValue('B' . $row, ':');
        $sheet->setCellValue('C' . $row, $data['sub_bidang']);
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Kegiatan');
        $sheet->setCellValue('B' . $row, ':');
        $sheet->setCellValue('C' . $row, $data['kegiatan']);
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Waktu Pelaksanaan');
        $sheet->setCellValue('B' . $row, ':');
        $sheet->setCellValue('C' . $row, $data['waktu_pelaksanaan']);
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Output/Keluaran');
        $sheet->setCellValue('B' . $row, ':');
        $sheet->setCellValue('C' . $row, $data['output']);
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        // Add border around info section
        $infoRange = 'A' . $infoStartRow . ':E' . ($row - 1);
        $sheet->getStyle($infoRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $row++;
        
        // ANGGARAN section
        $anggaranStartRow = $row;
        
        // Table headers with proper RAB format
        $sheet->setCellValue('A' . $row, 'KODE');
        $sheet->setCellValue('B' . $row, 'URAIAN');
        $sheet->setCellValue('C' . $row, 'ANGGARAN');
        $sheet->mergeCells('C' . $row . ':E' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, '1');
        $sheet->setCellValue('B' . $row, '2');
        $sheet->setCellValue('C' . $row, 'VOLUME');
        $sheet->setCellValue('D' . $row, 'HARGA SATUAN');
        $sheet->setCellValue('E' . $row, 'JUMLAH');
        $row++;
        
        $sheet->setCellValue('A' . $row, '');
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('C' . $row, '3');
        $sheet->setCellValue('D' . $row, '4');
        $sheet->setCellValue('E' . $row, '5');
        $row++;
        
        // Style table headers
        $headerRange1 = 'A' . ($row - 3) . ':E' . ($row - 3);
        $headerRange2 = 'A' . ($row - 2) . ':E' . ($row - 2);
        $headerRange3 = 'A' . ($row - 1) . ':E' . ($row - 1);
        
        $sheet->getStyle($headerRange1)->getFont()->setBold(true);
        $sheet->getStyle($headerRange1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange1)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $sheet->getStyle($headerRange2)->getFont()->setBold(true);
        $sheet->getStyle($headerRange2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange2)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        $sheet->getStyle($headerRange3)->getFont()->setBold(true);
        $sheet->getStyle($headerRange3)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange3)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
        // Data rows
        foreach ($data['items'] as $item) {
            $sheet->setCellValue('A' . $row, $item['code']);
            $sheet->setCellValue('B' . $row, $item['description']);
            $sheet->setCellValue('C' . $row, $item['volume']);
            $sheet->setCellValue('D' . $row, is_numeric($item['unit_price']) ? number_format($item['unit_price'], 2, ',', '.') : $item['unit_price']);
            $sheet->setCellValue('E' . $row, is_numeric($item['amount']) ? number_format($item['amount'], 2, ',', '.') : $item['amount']);
            
            // Style data rows
            $dataRange = 'A' . $row . ':E' . $row;
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('D' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }
        
        // Add border around the entire ANGGARAN section
        $anggaranRange = 'A' . $anggaranStartRow . ':E' . ($row - 1);
        $sheet->getStyle($anggaranRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $row++;
        
        // JUMLAH (Rp) section
        $sheet->setCellValue('A' . $row, 'JUMLAH (Rp)');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, is_numeric($data['total_amount']) ? number_format($data['total_amount'], 2, ',', '.') : $data['total_amount']);
        
        $totalRange = 'A' . $row . ':E' . $row;
        $sheet->getStyle($totalRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $row += 2;
        
        // Signature section
        $sheet->setCellValue('A' . $row, 'Disetujui,');
        $sheet->setCellValue('C' . $row, 'Telah Diverifikasi');
        $sheet->setCellValue('E' . $row, 'LUWORO, 31 Desember 2024');
        $row++;
        
        $sheet->setCellValue('A' . $row, 'KEPALA DESA');
        $sheet->setCellValue('C' . $row, 'SEKRETARIS DESA');
        $sheet->setCellValue('E' . $row, 'Pelaksana Kegiatan Anggaran,');
        $row += 3;
        
        $sheet->setCellValue('A' . $row, 'IFFAN RIFAI FATUMULOH');
        $sheet->setCellValue('C' . $row, 'DARMANTO');
        $sheet->setCellValue('E' . $row, 'MARDJI');
        
        return $spreadsheet;
    }
}
