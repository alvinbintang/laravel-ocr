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
        
        $currentItem = null;
        $inItemSection = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Parse header information
            if (strpos($line, 'PEMERINTAH DESA') !== false) {
                $data['organization'] = $line;
            } elseif (strpos($line, 'TAHUN ANGGARAN') !== false) {
                $data['year'] = $line;
            } elseif (strpos($line, 'APBDes') !== false) {
                $data['type'] = $line;
            } elseif (strpos($line, 'BIDANG') !== false && strpos($line, 'Sub Bidang') === false) {
                $data['bidang'] = $line;
            } elseif (strpos($line, 'Sub Bidang') !== false) {
                $data['sub_bidang'] = $line;
            } elseif (strpos($line, 'Kegiatan') !== false) {
                $data['kegiatan'] = $line;
            } elseif (strpos($line, 'Waktu Pelaksanaan') !== false) {
                $data['waktu_pelaksanaan'] = $line;
            } elseif (strpos($line, 'Output/Keluaran') !== false) {
                $data['output'] = $line;
            }
            
            // Parse items section
            if (preg_match('/^\d+/', $line) || strpos($line, 'BELANJA') !== false) {
                $inItemSection = true;
            }
            
            if ($inItemSection && !empty($line)) {
                // Parse item details
                if (preg_match('/(\d+\.?\d*\.?\d*)\s+(.+?)\s+(\d+[.,]\d+[.,]\d+)$/', $line, $matches)) {
                    $code = $matches[1];
                    $description = trim($matches[2]);
                    $amount = str_replace(['.', ','], ['', '.'], $matches[3]);
                    
                    $data['items'][] = [
                        'code' => $code,
                        'description' => $description,
                        'volume' => '',
                        'unit_price' => '',
                        'amount' => floatval($amount)
                    ];
                } elseif (preg_match('/(.+?)\s+(\d+)\s+(\w+)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $description = trim($matches[1]);
                    $volume = $matches[2];
                    $unit = $matches[3];
                    $unitPrice = str_replace(['.', ','], ['', '.'], $matches[4]);
                    $amount = str_replace(['.', ','], ['', '.'], $matches[5]);
                    
                    $data['items'][] = [
                        'code' => '',
                        'description' => $description,
                        'volume' => $volume . ' ' . $unit,
                        'unit_price' => floatval($unitPrice),
                        'amount' => floatval($amount)
                    ];
                }
            }
            
            // Parse total amount
            if (preg_match('/JUMLAH.*?([\d.,]+)/', $line, $matches)) {
                $data['total_amount'] = floatval(str_replace(['.', ','], ['', '.'], $matches[1]));
            }
        }
        
        return $data;
    }
    
    private function createExcelFile($data)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        $row = 1;
        
        // Header
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
        $row += 2;
        
        // Information section
        $sheet->setCellValue('A' . $row, 'Jenis APBDes:');
        $sheet->setCellValue('B' . $row, $data['type']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Bidang:');
        $sheet->setCellValue('B' . $row, $data['bidang']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Sub Bidang:');
        $sheet->setCellValue('B' . $row, $data['sub_bidang']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Kegiatan:');
        $sheet->setCellValue('B' . $row, $data['kegiatan']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Waktu Pelaksanaan:');
        $sheet->setCellValue('B' . $row, $data['waktu_pelaksanaan']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Output/Keluaran:');
        $sheet->setCellValue('B' . $row, $data['output']);
        $row += 2;
        
        // Table headers
        $sheet->setCellValue('A' . $row, 'KODE');
        $sheet->setCellValue('B' . $row, 'URAIAN');
        $sheet->setCellValue('C' . $row, 'VOLUME');
        $sheet->setCellValue('D' . $row, 'HARGA SATUAN');
        $sheet->setCellValue('E' . $row, 'JUMLAH');
        
        // Style table headers
        $headerRange = 'A' . $row . ':E' . $row;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
        $sheet->getStyle($headerRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $row++;
        
        // Data rows
        foreach ($data['items'] as $item) {
            $sheet->setCellValue('A' . $row, $item['code']);
            $sheet->setCellValue('B' . $row, $item['description']);
            $sheet->setCellValue('C' . $row, $item['volume']);
            $sheet->setCellValue('D' . $row, number_format($item['unit_price'], 2, ',', '.'));
            $sheet->setCellValue('E' . $row, number_format($item['amount'], 2, ',', '.'));
            
            // Style data rows
            $dataRange = 'A' . $row . ':E' . $row;
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('D' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }
        
        // Total row
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->setCellValue('E' . $row, number_format($data['total_amount'], 2, ',', '.'));
        
        $totalRange = 'A' . $row . ':E' . $row;
        $sheet->getStyle($totalRange)->getFont()->setBold(true);
        $sheet->getStyle($totalRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THICK);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        return $spreadsheet;
    }
}
