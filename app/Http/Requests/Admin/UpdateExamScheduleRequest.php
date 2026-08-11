<?php

namespace App\Http\Requests\Admin;

use App\Models\ExamSchedule;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExamScheduleRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'class_name' => ['required', 'string', 'max:100'],
            'exam_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'status' => ['required', Rule::in(array_keys(ExamSchedule::STATUSES))],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $start = Carbon::createFromFormat('H:i', (string) $this->input('start_time'));
                $end = $start->copy()->addMinutes((int) $this->input('duration_minutes'));

                if ($end->format('H:i') <= $start->format('H:i')) {
                    $validator->errors()->add('duration_minutes', 'Waktu selesai ujian melebihi pukul 24:00. Periksa kembali durasi.');
                }

                $this->validateNoRoomConflict($validator, $start);
                $this->validateSubjectHasActiveQuestions($validator);
            },
        ];
    }

    /**
     * Cegah jadwal dialihkan ke mata pelajaran yang belum punya soal aktif,
     * supaya tidak ada ujian yang berjalan tanpa soal. Edit biasa yang tidak
     * mengganti mata pelajaran tidak diblokir.
     */
    private function validateSubjectHasActiveQuestions(Validator $validator): void
    {
        $subjectId = (int) $this->input('subject_id');
        $current = $this->route('exam_schedule');

        if ($current instanceof ExamSchedule && $current->subject_id === $subjectId) {
            return;
        }

        $subject = Subject::query()
            ->withCount(['questions as active_count' => fn ($query) => $query->where('is_active', true)])
            ->find($subjectId);

        if ($subject === null || (int) $subject->active_count === 0) {
            $validator->errors()->add('subject_id', 'Mata pelajaran ini belum memiliki soal aktif. Tambahkan soal terlebih dahulu di Bank Soal sebelum mengubah jadwal.');
        }
    }

    private function validateNoRoomConflict(Validator $validator, Carbon $start): void
    {
        $current = $this->route('exam_schedule');
        $currentId = $current instanceof ExamSchedule ? $current->id : $current;

        $startMinutes = (int) $start->format('H') * 60 + (int) $start->format('i');
        $endMinutes = $startMinutes + (int) $this->input('duration_minutes');

        $conflict = ExamSchedule::findConflicting(
            roomId: (int) $this->input('room_id'),
            examDate: (string) $this->input('exam_date'),
            startMinutes: $startMinutes,
            endMinutes: $endMinutes,
            excludeId: $currentId,
        );

        if ($conflict !== null) {
            $validator->errors()->add('room_id', $this->conflictMessage($conflict));
        }
    }

    private function conflictMessage(ExamSchedule $conflict): string
    {
        return 'Ruangan ini bentrok dengan ujian '
            .($conflict->subject?->name ?? 'tanpa nama')
            .' pukul '
            .$conflict->startLabel()
            .'–'
            .$conflict->endLabel()
            .'.';
    }
}
