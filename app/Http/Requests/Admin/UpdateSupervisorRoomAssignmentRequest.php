<?php

namespace App\Http\Requests\Admin;

use App\Models\ExamPeriod;
use App\Models\Supervisor;
use App\Models\SupervisorRoomAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSupervisorRoomAssignmentRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $newSupervisorId = (int) $this->input('supervisor_id');
                /** @var SupervisorRoomAssignment $assignment */
                $assignment = $this->route('supervisorRoomAssignment');
                /** @var ExamPeriod $examPeriod */
                $examPeriod = $this->route('examPeriod');

                if (! $validator->errors()->isEmpty()) {
                    return;
                }

                $newSupervisor = Supervisor::query()->with('user')->find($newSupervisorId);
                if (! $newSupervisor || ! $newSupervisor->user?->is_active) {
                    $validator->errors()->add('supervisor_id', 'Pengawas yang dipilih tidak aktif.');

                    return;
                }

                if ($newSupervisorId === (int) $assignment->supervisor_id) {
                    return;
                }

                $alreadyAssigned = SupervisorRoomAssignment::query()
                    ->where('exam_period_id', $examPeriod->id)
                    ->where('supervisor_id', $newSupervisorId)
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
                    ->where('supervisor_id', $newSupervisorId)
                    ->where('exam_period_id', '!=', $examPeriod->id)
                    ->pluck('room_id')
                    ->all();

                if (in_array($assignment->room_id, $roomHistory, true)) {
                    $validator->errors()->add(
                        'supervisor_id',
                        "Pengawas {$newSupervisor->user->name} sudah bertugas di ruangan yang sama pada tanggal yang sama di sesi lain."
                    );
                }
            },
        ];
    }
}
