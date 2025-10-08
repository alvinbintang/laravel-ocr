<?php

namespace App\Services\Shared;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ExportService
{
    /**
     * Export data to Excel
     *
     * @param Collection $data
     * @param array $headings
     * @param string $fileName
     * @param string $format
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToExcel(Collection $data, array $headings, string $fileName, string $format = 'xlsx')
    {
        return Excel::download(new class($data, $headings) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $data;
            private $headings;

            public function __construct($data, $headings)
            {
                $this->data = $data;
                $this->headings = $headings;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        }, $fileName, $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Export data to PDF
     *
     * @param string $view
     * @param array $data
     * @param string $fileName
     * @return \Illuminate\Http\Response
     */
    public function exportToPdf(string $view, array $data, string $fileName)
    {
        $pdf = Pdf::loadView($view, $data);
        return $pdf->download($fileName);
    }

    /**
     * Export data to Word document
     *
     * @param Collection $data
     * @param array $headings
     * @param string $fileName
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToDocx(Collection $data, array $headings, string $fileName)
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // Add table
        $table = $section->addTable();
        
        // Add header row
        $table->addRow();
        foreach ($headings as $heading) {
            $table->addCell(2000)->addText($heading);
        }
        
        // Add data rows
        foreach ($data as $row) {
            $table->addRow();
            foreach ($row as $cell) {
                $table->addCell(2000)->addText($cell);
            }
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'phpword');
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);
        
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Export data to JSON
     *
     * @param Collection $data
     * @param string $fileName
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportToJson(Collection $data, string $fileName)
    {
        $json = $data->toJson(JSON_PRETTY_PRINT);
        $tempFile = tempnam(sys_get_temp_dir(), 'json_export');
        file_put_contents($tempFile, $json);
        
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}