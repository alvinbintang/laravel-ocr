<?php

namespace App\Repositories\Contracts;

use App\Models\OcrResult;
use Illuminate\Database\Eloquent\Collection;

interface OcrResultRepositoryInterface
{
    /**
     * Get all OCR results ordered by created_at desc
     *
     * @return Collection
     */
    public function getAllOrderedByCreatedAt(): Collection;

    /**
     * Find OCR result by ID
     *
     * @param int $id
     * @return OcrResult
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): OcrResult;

    /**
     * Create new OCR result
     *
     * @param array $data
     * @return OcrResult
     */
    public function create(array $data): OcrResult;

    /**
     * Update OCR result
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;

    /**
     * Update OCR result status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool;

    /**
     * Check if OCR result is done
     *
     * @param int $id
     * @return bool
     */
    public function isDone(int $id): bool;

    /**
     * Get OCR result with specific status
     *
     * @param int $id
     * @param string $status
     * @return OcrResult|null
     */
    public function findByIdAndStatus(int $id, string $status): ?OcrResult;
}