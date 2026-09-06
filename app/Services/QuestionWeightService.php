<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\DB;

class QuestionWeightService
{
    /**
     * Total bobot soal aktif untuk satu kombinasi mapel+kelas.
     * Hanya is_active=true dihitung, essay ikut. Pakai SUM di DB.
     */
    public function totalFor(int $subjectId, int $classroomId): float
    {
        $sum = Question::query()
            ->where('subject_id', $subjectId)
            ->where('is_active', true)
            ->targetingClassroom($classroomId)
            ->sum('score_weight');

        return round((float) ($sum ?? 0), 2);
    }

    /**
     * Map [classroomId => total] untuk semua kelas yang punya soal di subject tersebut.
     * Satu query via join + groupBy, filter is_active=true.
     *
     * @return array<int, float>
     */
    public function totalsForSubject(int $subjectId): array
    {
        $rows = DB::table('questions')
            ->join('question_classroom', 'questions.id', '=', 'question_classroom.question_id')
            ->where('questions.subject_id', $subjectId)
            ->where('questions.is_active', true)
            ->groupBy('question_classroom.classroom_id')
            ->selectRaw('question_classroom.classroom_id as classroom_id, SUM(questions.score_weight) as total')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->classroom_id] = round((float) $row->total, 2);
        }

        return $result;
    }

    /**
     * @return array{total: float, status: 'over'|'under'|'ok', delta: float}
     */
    public function check(int $subjectId, int $classroomId): array
    {
        $total = $this->totalFor($subjectId, $classroomId);
        $delta = round(abs($total - 100), 2);

        if (abs($total - 100) < 0.01) {
            return ['total' => $total, 'status' => 'ok', 'delta' => 0.0];
        }

        if ($total > 100) {
            return ['total' => $total, 'status' => 'over', 'delta' => $delta];
        }

        return ['total' => $total, 'status' => 'under', 'delta' => $delta];
    }

    /**
     * Cek banyak kombinasi sekaligus.
     *
     * @param  array<int, array{subject_id:int, classroom_id:int}|array{0:int,1:int}>  $pairs
     * @return array<string, array{total: float, status: string, delta: float}>
     */
    public function checkMany(array $pairs): array
    {
        $result = [];
        foreach ($pairs as $pair) {
            if (isset($pair['subject_id']) && isset($pair['classroom_id'])) {
                $sid = (int) $pair['subject_id'];
                $cid = (int) $pair['classroom_id'];
            } elseif (isset($pair[0]) && isset($pair[1])) {
                $sid = (int) $pair[0];
                $cid = (int) $pair[1];
            } else {
                continue;
            }

            $result["{$sid}:{$cid}"] = $this->check($sid, $cid);
        }

        return $result;
    }
}
