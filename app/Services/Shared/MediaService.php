<?php

namespace App\Services\Shared;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    /**
     * Upload media file
     *
     * @param UploadedFile $file
     * @param Model $model
     * @param string $usage
     * @param string $collection
     * @param string $disk
     * @return string|null
     */
    public function upload(
        UploadedFile $file,
        Model $model,
        string $usage = 'default',
        string $collection = 'default',
        string $disk = 'public'
    ): ?string {
        try {
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($collection, $filename, $disk);
            
            // Here you could save media information to database
            // For now, just return the path
            return $path;
        } catch (\Exception $e) {
            \Log::error('Media upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Remove media file
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public function remove(string $path, string $disk = 'public'): bool
    {
        try {
            return Storage::disk($disk)->delete($path);
        } catch (\Exception $e) {
            \Log::error('Media removal failed: ' . $e->getMessage());
            return false;
        }
    }
}