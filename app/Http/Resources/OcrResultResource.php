<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OcrResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'document_type' => $this->document_type,
            'status' => $this->status,
            'text' => $this->text,
            'image_path' => $this->image_path,
            'image_paths' => $this->image_paths,
            'page_count' => $this->page_count,
            'ocr_results' => $this->ocr_results,
            'selected_regions' => $this->selected_regions,
            'cropped_images' => $this->cropped_images,
            'page_rotations' => $this->page_rotations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}