<?php

namespace App\Http\Requests\GuruMapel;

use App\Models\SubjectAttendance;
use App\Services\ExamGradingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGuruMapelEntriesRequest extends FormRequest
{
    /**
     * Judul sistem yang tidak boleh dipakai guru (ambigu dengan baris otomatis).
     */
    private const RESERVED_TITLES = [ExamGradingService::CBT_TITLE, SubjectAttendance::ATTENDANCE_TITLE];

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
            'subject_id' => ['required', 'integer'],
            'classroom_id' => ['required', 'integer'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],

            // Input nilai per jenis ujian: entries[student_id][type_code]
            'entries' => ['sometimes', 'array'],
            'entries.*' => ['array'],
            'entries.*.harian.title' => ['nullable', 'string', 'max:255'],
            'entries.*.harian.score' => ['nullable', 'numeric', 'between:0,100'],
            'entries.*.harian.note' => ['nullable', 'string', 'max:255'],
            'entries.*.uts.score' => ['nullable', 'numeric', 'between:0,100'],
            'entries.*.uts.note' => ['nullable', 'string', 'max:255'],
            'entries.*.uas.score' => ['nullable', 'numeric', 'between:0,100'],
            'entries.*.uas.note' => ['nullable', 'string', 'max:255'],

            // Input kehadiran per siswa
            'attendance' => ['sometimes', 'array'],
            'attendance.*.total_days' => ['required_with:attendance.*.present_days', 'nullable', 'integer', 'min:0', 'max:32767'],
            'attendance.*.present_days' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'attendance.*.absent_days' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // title manual tidak boleh memakai judul sistem ('CBT', 'Kehadiran')
            foreach ($this->input('entries', []) as $studentId => $entry) {
                $title = strtolower(trim((string) ($entry['harian']['title'] ?? '')));

                if ($title === '') {
                    continue;
                }

                foreach (self::RESERVED_TITLES as $reserved) {
                    if ($title === strtolower($reserved)) {
                        $validator->errors()->add(
                            "entries.{$studentId}.harian.title",
                            'Judul tidak boleh memakai nama sistem. Gunakan judul lain (contoh: "UH 1", "Remidi").'
                        );
                    }
                }
            }

            // present + absent tidak boleh melebihi total hari
            foreach ($this->input('attendance', []) as $studentId => $att) {
                $total = (int) ($att['total_days'] ?? 0);
                $sum = (int) ($att['present_days'] ?? 0) + (int) ($att['absent_days'] ?? 0);

                if ($sum > $total) {
                    $validator->errors()->add(
                        "attendance.{$studentId}.total_days",
                        'Jumlah hadir + tidak hadir tidak boleh melebihi total hari.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Mata pelajaran wajib dipilih.',
            'classroom_id.required' => 'Kelas wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'entries.*.harian.score.between' => 'Nilai harus antara 0-100.',
            'entries.*.uts.score.between' => 'Nilai harus antara 0-100.',
            'entries.*.uas.score.between' => 'Nilai harus antara 0-100.',
            'entries.*.harian.title.max' => 'Judul maksimal 255 karakter.',
        ];
    }
}
