<?php

namespace App\Repositories\Admin;

use App\Models\OcrResult;
use App\Repositories\Contracts\OcrResultRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class OcrResultRepository implements OcrResultRepositoryInterface
{
    /**
     * Get all OCR results ordered by created_at desc
     *
     * @return Collection
     */
    public function getAllOrderedByCreatedAt(): Collection
    {
        return OcrResult::orderBy('created_at', 'desc')->get();
    }

    /**
     * Find OCR result by ID
     *
     * @param int $id
     * @return OcrResult
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findById(int $id): OcrResult
    {
        return OcrResult::findOrFail($id);
    }

    /**
     * Create new OCR result
     *
     * @param array $data
     * @return OcrResult
     */
    public function create(array $data): OcrResult
    {
        return OcrResult::create($data);
    }

    /**
     * Update OCR result
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $ocrResult = $this->findById($id);
        return $ocrResult->update($data);
    }

    /**
     * Update OCR result status
     *
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Check if OCR result is done
     *
     * @param int $id
     * @return bool
     */
    public function isDone(int $id): bool
    {
        $ocrResult = $this->findById($id);
        return $ocrResult->status === 'done';
    }

    /**
     * Get OCR result with specific status
     *
     * @param int $id
     * @param string $status
     * @return OcrResult|null
     */
    public function findByIdAndStatus(int $id, string $status): ?OcrResult
    {
        return OcrResult::where('id', $id)->where('status', $status)->first();
    }
}