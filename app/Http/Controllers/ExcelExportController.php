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
        
        // Set column widths to match original RAB format
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        
        $row = 1;
        
        // Header section with thick border
        $headerStartRow = $row;
        
        $sheet->setCellValue('A' . $row, $data['title']);
        $sheet->mergeCells('A' . $row . ':E' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
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
        
        // Add thick border around header
        $headerRange = 'A' . $headerStartRow . ':E' . ($row - 1);
        $sheet->getStyle($headerRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $row++;
        
        // Information section with thick border
        $infoStartRow = $row;
        
        $sheet->setCellValue('A' . $row, 'Jenis APBDes :');
        $sheet->setCellValue('B' . $row, $data['type']);
        $sheet->mergeCells('B' . $row . ':E' . $row);
        $row++;
        
        $row++; // Empty row
        
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
        
        // Add thick border around info section
        $infoRange = 'A' . $infoStartRow . ':E' . ($row - 1);
        $sheet->getStyle($infoRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        $row++;
        
        // Table section with thick border
        $tableStartRow = $row;
        
        // Table headers - simple format like original
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
        
        // Style headers with borders
        for ($i = $tableStartRow; $i < $row; $i++) {
            $headerRange = 'A' . $i . ':E' . $i;
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Data rows with proper formatting
        foreach ($data['items'] as $item) {
            $sheet->setCellValue('A' . $row, $item['code']);
            $sheet->setCellValue('B' . $row, $item['description']);
            $sheet->setCellValue('C' . $row, $item['volume']);
            $sheet->setCellValue('D' . $row, is_numeric($item['unit_price']) && $item['unit_price'] > 0 ? number_format($item['unit_price'], 2, ',', '.') : '');
            $sheet->setCellValue('E' . $row, is_numeric($item['amount']) && $item['amount'] > 0 ? number_format($item['amount'], 2, ',', '.') : $item['amount']);
            
            // Style data rows
            $dataRange = 'A' . $row . ':E' . $row;
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Bold for main categories (codes like 5, 2.02.02, 5.2.1.)
            if (preg_match('/^\d+$/', $item['code']) || preg_match('/^\d+\.\d+\.\d+$/', $item['code']) || preg_match('/^\d+\.\d+\.\d+\.$/', $item['code'])) {
                $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            }
            
            $row++;
        }
        
        // Add thick border around entire table
        $tableRange = 'A' . $tableStartRow . ':E' . ($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THICK);
        
        return $spreadsheet;
    }
}
