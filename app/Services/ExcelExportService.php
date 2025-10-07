<?php

namespace App\Services;

use App\Repositories\Contracts\OcrResultRepositoryInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelExportService
{
    protected $ocrResultRepository;

    public function __construct(OcrResultRepositoryInterface $ocrResultRepository)
    {
        $this->ocrResultRepository = $ocrResultRepository;
    }

    /**
     * Export OCR result to Excel
     *
     * @param int $id
     * @return void
     * @throws \Exception
     */
    public function exportToExcel(int $id): void
    {
        $ocrResult = $this->ocrResultRepository->findById($id);
        
        if ($ocrResult->status !== 'done' || empty($ocrResult->text)) {
            throw new \Exception('Data OCR belum selesai diproses atau tidak ada hasil.');
        }

        $allRabs = $this->parseOcrDataV4($ocrResult->text);
        if (empty($allRabs)) {
            throw new \Exception('Tidak ada data RAB yang dapat dikenali dari dokumen.');
        }

        $spreadsheet = new Spreadsheet();
        foreach ($allRabs as $index => $rabData) {
            if (empty($rabData['items'])) continue; // Lewati jika tidak ada item belanja sama sekali
            
            if ($index > 0) $spreadsheet->createSheet();
            $sheet = $spreadsheet->getSheet($index);

            $sheetTitle = 'RAB ' . ($index + 1);
            if (!empty($rabData['kegiatan'])) {
                 $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]/', '', $rabData['kegiatan']);
                 $sheetTitle = substr($cleanTitle, 0, 25);
            }
            $sheet->setTitle($sheetTitle);

            $this->populateSheet($sheet, $rabData);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $filename = 'RAB_Lengkap_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Clean number string for processing
     *
     * @param string $string
     * @return string
     */
    private function cleanNumber($string): string
    {
        $string = str_replace('.', '', $string);
        $string = str_replace(',', '.', $string);
        return preg_replace('/[^0-9.]/', '', $string);
    }

    /**
     * Get empty RAB structure template
     *
     * @return array
     */
    private function getEmptyRabStructure(): array
    {
        return [
            'title' => 'RENCANA ANGGARAN BIAYA (RAB)', 
            'organization' => '', 
            'year' => '', 
            'type' => '',
            'bidang' => '', 
            'sub_bidang' => '', 
            'kegiatan' => '', 
            'waktu_pelaksanaan' => '', 
            'output' => '',
            'items' => [], 
            'total_amount' => '', 
            'lokasi_tanggal' => '', 
            'disetujui_jabatan' => 'KEPALA DESA',
            'disetujui_nama' => '', 
            'diverifikasi_jabatan' => 'SEKRETARIS DESA', 
            'diverifikasi_nama' => '',
            'pelaksana_jabatan' => 'Pelaksana Kegiatan Anggaran,', 
            'pelaksana_nama' => '',
        ];
    }
    
    /**
     * Parse OCR data V4 - Menggabungkan halaman data dan halaman signature
     *
     * @param string $text
     * @return array
     */
    private function parseOcrDataV4($text): array
    {
        $lines = explode("\n", $text);
        $allRabs = [];
        $currentRab = $this->getEmptyRabStructure();
        $inItemSection = false;
        $signatureState = null; // Untuk menangkap nama di bawah jabatan

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos(strtolower($line), 'printed by') !== false) continue;

            // Trigger untuk memulai RAB BARU adalah adanya baris 'Bidang'
            if (strpos($line, 'Bidang') === 0) {
                // Jika RAB saat ini sudah berisi item, simpan dan reset
                if (!empty($currentRab['items'])) {
                    $allRabs[] = $currentRab;
                    $currentRab = $this->getEmptyRabStructure();
                }
                $inItemSection = false;
            }
            
            // --- PARSING ---
            if (strpos($line, 'PEMERINTAH DESA') !== false) $currentRab['organization'] = $line;
            elseif (strpos($line, 'TAHUN ANGGARAN') !== false) $currentRab['year'] = $line;
            
            // Parsing Header
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                if (strpos($key, 'bidang') !== false && strpos($key, 'sub') === false) $currentRab['bidang'] = $value;
                elseif (strpos($key, 'sub bidang') !== false) $currentRab['sub_bidang'] = $value;
                elseif (strpos($key, 'kegiatan') !== false) $currentRab['kegiatan'] = $value;
                elseif (strpos($key, 'waktu pel') !== false) $currentRab['waktu_pelaksanaan'] = $value;
                elseif (strpos($key, 'output') !== false) $currentRab['output'] = $value;
            }

            if (preg_match('/KODE\s+URAIAN/i', $line)) $inItemSection = true;

            // Parsing Tabel Item
            if ($inItemSection) {
                 if (preg_match('/^(\d{2}\.)\s+(.*?)\s+((?:\d[\d\s.,]*)\s*[\w\s()x]+)\s+([\d.,]+)\s+([\d.,]+)$/', $line, $matches)) {
                    $currentRab['items'][] = [
                        'code' => '', 
                        'description' => trim($matches[1] . ' ' . $matches[2]),
                        'volume' => trim($matches[3]), 
                        'unit_price' => $this->cleanNumber($matches[4]),
                        'amount' => $this->cleanNumber($matches[5]), 
                        'is_detail' => true
                    ];
                } elseif (preg_match('/^(\d[\d\.]*\.?)\s+(.*?)\s+([\d.,]+)$/', $line, $matches)) {
                     if (strpos($line, 'JUMLAH (Rp)') !== false) {
                        $currentRab['total_amount'] = $this->cleanNumber($matches[2]);
                     } else {
                        $code = trim($matches[1]);
                        $description = trim($matches[2]);
                        if (in_array(strtoupper($description), ['URAIAN'])) continue;
                        $currentRab['items'][] = [
                            'code' => $code, 
                            'description' => $description, 
                            'volume' => '', 
                            'unit_price' => '',
                            'amount' => $this->cleanNumber($matches[3]), 
                            'is_detail' => false
                        ];
                     }
                }
            }

            // Parsing Blok Signature
            if (preg_match('/(\w+),\s+(\d+\s+\w+\s+\d{4})/', $line, $matches)) $currentRab['lokasi_tanggal'] = $line;
            elseif (strpos($line, 'KEPALA DESA') !== false) $signatureState = 'disetujui_nama';
            elseif (strpos($line, 'SEKRETARIS DESA') !== false) $signatureState = 'diverifikasi_nama';
            elseif (strpos(strtolower($line), 'pelaksana kegiatan') !== false) $signatureState = 'pelaksana_nama';
            elseif ($signatureState && !empty($line)) {
                $currentRab[$signatureState] = $line;
                $signatureState = null; // Reset state
            }
        }
        
        if (!empty($currentRab['items'])) $allRabs[] = $currentRab;
        return $allRabs;
    }

    /**
     * Populate Excel sheet with RAB data
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
     * @param array $data
     * @return void
     */
    private function populateSheet($sheet, $data): void
    {
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(12); 
        $sheet->getColumnDimension('B')->setWidth(55);
        $sheet->getColumnDimension('C')->setWidth(20); 
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        
        $row = 1;
        
        // Header
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['title'])->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $row++;
        
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['organization'])->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $row++;
        
        $sheet->mergeCells('A'.$row.':E'.$row)->setCellValue('A'.$row, $data['year'])->getStyle('A'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
        $row+=2;
        
        // Information section
        $infoStartRow = $row;
        $sheet->setCellValue('A'.$row, 'Bidang')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['bidang'])->mergeCells('C'.$row.':E'.$row); 
        $row++;
        $sheet->setCellValue('A'.$row, 'Sub Bidang')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['sub_bidang'])->mergeCells('C'.$row.':E'.$row); 
        $row++;
        $sheet->setCellValue('A'.$row, 'Kegiatan')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['kegiatan'])->mergeCells('C'.$row.':E'.$row); 
        $row++;
        $sheet->setCellValue('A'.$row, 'Waktu Pelaksanaan')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['waktu_pelaksanaan'])->mergeCells('C'.$row.':E'.$row); 
        $row++;
        $sheet->setCellValue('A'.$row, 'Output/Keluaran')->setCellValue('B'.$row, ':')->setCellValue('C'.$row, $data['output'])->mergeCells('C'.$row.':E'.$row); 
        $row++;
        
        $sheet->getStyle('A'.$infoStartRow.':E'.($row-1))->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM); 
        $row++;
        
        // Table header
        $tableStartRow = $row;
        $headerStyle = [
            'font' => ['bold' => true], 
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, 
                'vertical' => Alignment::VERTICAL_CENTER
            ], 
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        
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
        
        // Table data
        foreach ($data['items'] as $item) {
             if ($item['is_detail']) {
                $sheet->setCellValue('A' . $row, '')->setCellValue('B' . $row, $item['description']);
             } else {
                $sheet->setCellValue('A' . $row, $item['code'])->setCellValue('B' . $row, $item['description']);
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

        // Total row
        $sheet->mergeCells('A'.$row.':D'.$row)->setCellValue('A'.$row, 'JUMLAH (Rp)');
        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        if (!empty($data['total_amount'])) {
            $sheet->setCellValue('E'.$row, (float)$data['total_amount'])->getStyle('E'.$row)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle('A'.$row.':E'.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':E'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.$tableStartRow.':E'.$row)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        $row+=2;

        // Signature section
        if (!empty($data['disetujui_nama'])) {
            $sheet->mergeCells('D'.$row.':E'.$row)->setCellValue('D'.$row, $data['lokasi_tanggal'])->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            
            $sigStartRow = $row;
            $sheet->setCellValue('A'.$row, 'Disetujui,')->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('B'.$row.':C'.$row)->setCellValue('B'.$row, 'Telah Diverifikasi')->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('D'.$row.':E'.$row)->setCellValue('D'.$row, $data['pelaksana_jabatan'])->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
            $sheet->setCellValue('A'.$row, $data['disetujui_jabatan'])->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('B'.$row.':C'.$row)->setCellValue('B'.$row, $data['diverifikasi_jabatan'])->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $row+=3; // Spasi untuk TTD
            
            $sheet->setCellValue('A'.$row, $data['disetujui_nama'])->getStyle('A'.$row)->getFont()->setBold(true)->setUnderline(true);
            $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->mergeCells('B'.$row.':C'.$row)->setCellValue('B'.$row, $data['diverifikasi_nama'])->getStyle('B'.$row)->getFont()->setBold(true)->setUnderline(true);
            $sheet->getStyle('B'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            $sheet->mergeCells('D'.$row.':E'.$row)->setCellValue('D'.$row, $data['pelaksana_nama'])->getStyle('D'.$row)->getFont()->setBold(true)->setUnderline(true);
            $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }
}