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
        'image_paths', // ADDED: Store multiple image paths for multi-page PDFs
        'ocr_results',
    ];

    // ADDED: Helper method to get all image paths
    public function getImagePathsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    // ADDED: Helper method to get total page count
    public function getPageCountAttribute()
    {
        $imagePaths = $this->getImagePathsAttribute($this->attributes['image_paths'] ?? null);
        return count($imagePaths);
    }
}