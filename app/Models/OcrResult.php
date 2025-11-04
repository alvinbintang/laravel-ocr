<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OcrResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'document_type', // ADDED: Store document type (RAB or RKA)
        'text',
        'status',
        'image_path',
        'image_paths', // Store multiple image paths for multi-page PDFs
        'ocr_results',
        'selected_regions', // ADDED: Store selected regions data
        'cropped_images', // ADDED: Store cropped images paths
        'page_rotations', // ADDED: Store page rotation angles
        'crop_confirmed_at', // ADDED: Timestamp when crop was confirmed
        'crop_preview_data', // ADDED: Store crop preview data for confirmation
    ];

    // Helper method to get all image paths
    public function getImagePathsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Backward-compatible accessor to expose image_paths as images for views
    public function getImagesAttribute($value)
    {
        // Utilize existing accessor to ensure we always return an array
        return $this->image_paths ?? [];
    }

    // Helper method to get total page count
    public function getPageCountAttribute()
    {
        $imagePaths = $this->getImagePathsAttribute($this->attributes['image_paths'] ?? null);
        return count($imagePaths);
    }

    // ADDED: Helper method to get selected regions
    public function getSelectedRegionsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }
    
    // ADDED: Helper method to get page rotations
    public function getPageRotationsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Helper method to get image path for specific page
    public function getImagePathForPage($page)
    {
        $imagePaths = $this->getImagePathsAttribute($this->attributes['image_paths'] ?? null);
        if (empty($imagePaths) || $page < 1 || $page > count($imagePaths)) {
            return null;
        }
        
        $relativePath = $imagePaths[$page - 1];
        return asset('storage/' . $relativePath);
    }

    // ADDED: Helper method to get OCR results
    public function getOcrResultsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Helper method to get cropped images
    public function getCroppedImagesAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Helper method to get crop preview data
    public function getCropPreviewDataAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Helper method to check if crop has been confirmed
    public function isCropConfirmed()
    {
        return !is_null($this->crop_confirmed_at);
    }

    // ADDED: Helper method to mark crop as confirmed
    public function confirmCrop()
    {
        $this->crop_confirmed_at = now();
        $this->save();
    }
}