<?php

namespace App\Http\Requests\GuruMapel;

use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validator import soal bagi Guru Mapel. Tidak memuat pilihan kelas target
 * (kelas target ditentukan otomatis dari cakupan kelas yang di-assign guru
 * untuk mapel masing-masing baris).
 */
class ImportGuruMapelQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Question::TYPES))],
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Pilih jenis soal terlebih dahulu.',
            'type.in' => 'Jenis soal tidak valid.',
            'file.required' => 'Pilih file Excel/CSV terlebih dahulu.',
            'file.extensions' => 'Format file harus xlsx, xls, atau csv.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
