<?php

namespace App\Importers;

use App\Services\Shared\ImportService;
use Illuminate\Http\UploadedFile;

abstract class BaseImporter
{
    protected $logCallback;

    public function __construct(
        protected ImportService $importService
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
     * @param string $action
     * @param string $fileName
     * @return void
     */
    protected function log(string $action, string $fileName): void
    {
        if ($this->logCallback) {
            $format = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            call_user_func($this->logCallback, $format, $fileName);
        }
    }

    /**
     * Import data from file
     *
     * @param UploadedFile $file
     * @return array
     */
    abstract public function import(UploadedFile $file): array;
}