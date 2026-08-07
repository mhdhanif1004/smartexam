<?php

namespace App\Http\Requests\Admin;

use App\Models\ExamSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamScheduleRequest extends FormRequest
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

                $this->validateNoRoomConflict($validator, $start, $end);
            },
        ];
    }

    private function validateNoRoomConflict(Validator $validator, Carbon $start, Carbon $end): void
    {
        $roomId = (int) $this->input('room_id');
        $examDate = (string) $this->input('exam_date');

        $conflict = ExamSchedule::query()
            ->where('room_id', $roomId)
            ->where('exam_date', $examDate)
            ->where(function ($query) use ($start, $end) {
                $query->where('start_time', '<', $end->format('H:i:s'))
                    ->where('end_time', '>', $start->format('H:i:s'));
            })
            ->exists();

        if ($conflict) {
            $validator->errors()->add('room_id', 'Ruangan ini sudah dipakai jadwal ujian lain pada waktu yang bertabrakan.');
        }
    }
}
