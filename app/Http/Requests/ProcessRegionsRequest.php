<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessRegionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'regions' => 'required|array',
            'regions.*.id' => 'required|integer',
            'regions.*.x' => 'required|numeric',
            'regions.*.y' => 'required|numeric',
            'regions.*.width' => 'required|numeric',
            'regions.*.height' => 'required|numeric',
            'regions.*.page' => 'required|integer|min:1',
            'previewDimensions' => 'nullable|array',
            'previewDimensions.width' => 'nullable|numeric|min:1',
            'previewDimensions.height' => 'nullable|numeric|min:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'regions.required' => 'Data regions wajib diisi.',
            'regions.array' => 'Data regions harus berupa array.',
            'regions.*.id.required' => 'ID region wajib diisi.',
            'regions.*.id.integer' => 'ID region harus berupa angka.',
            'regions.*.x.required' => 'Koordinat X region wajib diisi.',
            'regions.*.x.numeric' => 'Koordinat X region harus berupa angka.',
            'regions.*.y.required' => 'Koordinat Y region wajib diisi.',
            'regions.*.y.numeric' => 'Koordinat Y region harus berupa angka.',
            'regions.*.width.required' => 'Lebar region wajib diisi.',
            'regions.*.width.numeric' => 'Lebar region harus berupa angka.',
            'regions.*.height.required' => 'Tinggi region wajib diisi.',
            'regions.*.height.numeric' => 'Tinggi region harus berupa angka.',
            'regions.*.page.required' => 'Nomor halaman region wajib diisi.',
            'regions.*.page.integer' => 'Nomor halaman region harus berupa angka.',
            'regions.*.page.min' => 'Nomor halaman region minimal 1.',
            'previewDimensions.array' => 'Preview dimensions harus berupa array.',
            'previewDimensions.width.numeric' => 'Lebar preview dimensions harus berupa angka.',
            'previewDimensions.width.min' => 'Lebar preview dimensions minimal 1.',
            'previewDimensions.height.numeric' => 'Tinggi preview dimensions harus berupa angka.',
            'previewDimensions.height.min' => 'Tinggi preview dimensions minimal 1.',
        ];
    }
}