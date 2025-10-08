<?php

namespace App\Services\Shared;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    /**
     * Parse CSV file
     *
     * @param UploadedFile $file
     * @return array
     */
    public function parseCsv(UploadedFile $file): array
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');
        
        if ($handle !== false) {
            $headers = fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== false) {
                if (count($headers) === count($row)) {
                    $data[] = array_combine($headers, $row);
                }
            }
            
            fclose($handle);
        }
        
        return $data;
    }

    /**
     * Parse Excel file
     *
     * @param UploadedFile $file
     * @return array
     */
    public function parseXlsx(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = [];
            
            $headers = [];
            $firstRow = true;
            
            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                
                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }
                
                if ($firstRow) {
                    $headers = $rowData;
                    $firstRow = false;
                } else {
                    if (count($headers) === count($rowData)) {
                        $data[] = array_combine($headers, $rowData);
                    }
                }
            }
            
            return $data;
        } catch (\Exception $e) {
            \Log::error('Excel parsing failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse JSON file
     *
     * @param UploadedFile $file
     * @return array
     */
    public function parseJson(UploadedFile $file): array
    {
        try {
            $content = file_get_contents($file->getPathname());
            $data = json_decode($content, true);
            
            return is_array($data) ? $data : [];
        } catch (\Exception $e) {
            \Log::error('JSON parsing failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse PDF with OCR
     *
     * @param UploadedFile $file
     * @return array
     */
    public function parsePdfOcr(UploadedFile $file): array
    {
        // This would integrate with OCR service
        // For now, return empty array as placeholder
        return [];
    }
}