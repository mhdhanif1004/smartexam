<?php

namespace App\Http\Requests\Admin;

use App\Models\ExamPeriod;
use App\Models\Room;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupervisorRoomAssignmentRequest extends FormRequest
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
            'supervisor_id' => ['required', 'integer', Rule::exists('supervisors', 'id')],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ExamPeriod $examPeriod */
                $examPeriod = $this->route('examPeriod');

                if (! $validator->errors()->isEmpty()) {
                    return;
                }

                $room = Room::query()->find((int) $this->input('room_id'));
                if (! $room) {
                    $validator->errors()->add('room_id', 'Ruangan tidak ditemukan.');

                    return;
                }

                if (! $examPeriod->schedules()->where('room_id', $room->id)->exists()) {
                    $validator->errors()->add('room_id', 'Ruangan ini tidak terdaftar pada sesi ini.');

                    return;
                }

                $filled = SupervisorRoomAssignment::query()
                    ->where('exam_period_id', $examPeriod->id)
                    ->where('room_id', $room->id)
                    ->count();

                if ($filled >= max(1, (int) $room->supervisor_count)) {
                    $validator->errors()->add('supervisor_id', 'Slot pengawas untuk ruangan ini sudah penuh.');

                    return;
                }

                $newSupervisor = Supervisor::query()->with('user')->find((int) $this->input('supervisor_id'));
                if (! $newSupervisor || ! $newSupervisor->user?->is_active) {
                    $validator->errors()->add('supervisor_id', 'Pengawas yang dipilih tidak aktif.');

                    return;
                }

                $alreadyAssigned = SupervisorRoomAssignment::query()
                    ->where('exam_period_id', $examPeriod->id)
                    ->where('supervisor_id', $newSupervisor->id)
                    ->first();

                if ($alreadyAssigned) {
                    $roomName = $alreadyAssigned->room?->display_name ?? 'Ruang #'.$alreadyAssigned->room_id;
                    $validator->errors()->add(
                        'supervisor_id',
                        "Pengawas {$newSupervisor->user->name} sudah bertugas di {$roomName} pada sesi ini."
                    );

                    return;
                }

                $date = $examPeriod->exam_date->toDateString();
                $roomHistory = SupervisorRoomAssignment::query()
                    ->where('exam_date', $date)
                    ->where('supervisor_id', $newSupervisor->id)
                    ->where('exam_period_id', '!=', $examPeriod->id)
                    ->pluck('room_id')
                    ->all();

                if (in_array($room->id, $roomHistory, true)) {
                    $validator->errors()->add(
                        'supervisor_id',
                        "Pengawas {$newSupervisor->user->name} sudah bertugas di ruangan yang sama pada tanggal yang sama di sesi lain."
                    );
                }
            },
        ];
    }
}
