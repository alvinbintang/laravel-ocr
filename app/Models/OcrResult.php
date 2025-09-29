<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OcrResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'text',
        'status',
        'image_path',
        'image_paths', // Store multiple image paths for multi-page PDFs
        'ocr_results',
        'selected_regions', // ADDED: Store selected regions data
    ];

    // Helper method to get all image paths
    public function getImagePathsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
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
}