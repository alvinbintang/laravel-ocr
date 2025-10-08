<?php

namespace App\Exporters;

use App\Services\Shared\ExportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel;

abstract class BaseExporter
{
    protected $logCallback;

    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Set log callback function
     *
     * @param callable $callback
     * @return $this
     */
    public function setLogCallback(callable $callback): self
    {
        $this->logCallback = $callback;
        return $this;
    }

    /**
     * Log activity using the callback
     *
     * @param string $format
     * @param string $fileName
     * @return void
     */
    protected function log(string $format, string $fileName): void
    {
        if ($this->logCallback) {
            call_user_func($this->logCallback, $format, $fileName);
        }
    }

    /**
     * Export to Excel format
     *
     * @param string $fileName
     * @param string $format
     * @return mixed
     */
    public function exportToExcel(string $fileName, string $format = Excel::XLSX)
    {
        $result = $this->exportService->exportToExcel(
            data: $this->getData(),
            fileName: $fileName,
            headings: $this->getHeadings(),
            mapper: $this->getMapper(),
            format: $format
        );

        $this->log(strtolower($format), $fileName);
        return $result;
    }

    /**
     * Export to PDF format
     *
     * @param string $fileName
     * @param string $view
     * @return mixed
     */
    public function exportToPdf(string $fileName, string $view)
    {
        $result = $this->exportService->exportToPdf(
            data: $this->getData(),
            fileName: $fileName,
            view: $view
        );

        $this->log('pdf', $fileName);
        return $result;
    }

    /**
     * Export to Word format
     *
     * @param string $fileName
     * @return mixed
     */
    public function exportToDocx(string $fileName)
    {
        $result = $this->exportService->exportToWord(
            data: $this->getData(),
            fileName: $fileName,
            headings: $this->getHeadings()
        );

        $this->log('docx', $fileName);
        return $result;
    }

    /**
     * Export to JSON format
     *
     * @param string $fileName
     * @return mixed
     */
    public function exportToJson(string $fileName)
    {
        $result = $this->exportService->exportToJson(
            data: $this->getData(),
            fileName: $fileName
        );

        $this->log('json', $fileName);
        return $result;
    }

    /**
     * Get data to export
     *
     * @return Collection
     */
    abstract public function getData(): Collection;

    /**
     * Get headings for export
     *
     * @return array
     */
    abstract public function getHeadings(): array;

    /**
     * Get mapper function for data transformation
     *
     * @return \Closure|null
     */
    abstract public function getMapper(): ?\Closure;
}