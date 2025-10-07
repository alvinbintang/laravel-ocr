<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OcrExtractRequest extends FormRequest
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
            'pdf' => 'required|mimes:pdf|max:20480', // max 20MB
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
            'pdf.required' => 'File PDF wajib diupload.',
            'pdf.mimes' => 'File harus berformat PDF.',
            'pdf.max' => 'Ukuran file maksimal 20MB.',
        ];
    }
}