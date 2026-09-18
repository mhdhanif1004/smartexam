<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\ExamSession;
use App\Models\ExamSessionQuestionOrder;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class QuestionOrderService
{
    /**
     * Get valid questions for an exam session sorted by randomized (and persisted) order.
     *
     * @return Collection<int, Question>
     */
    public function orderedQuestionsFor(ExamSession $session, ExamSchedule $schedule, int $classroomId): Collection
    {
        $validQuestions = $schedule->subject->questions()
            ->where('is_active', true)
            ->targetingClassroom($classroomId)
            ->get();

        if ($validQuestions->isEmpty()) {
            return new Collection;
        }

        // Jika randomization nonaktif, return urutan by id tanpa menyimpan ke DB
        if (! $schedule->is_random_question_order) {
            return $validQuestions->sortBy('id')->values();
        }

        $existingOrders = ExamSessionQuestionOrder::query()
            ->where('exam_session_id', $session->id)
            ->pluck('urutan', 'question_id');

        if ($existingOrders->isEmpty()) {
            $shuffledIds = $validQuestions->pluck('id')->shuffle()->values();

            $insertData = [];
            $now = now();
            foreach ($shuffledIds as $index => $qId) {
                $insertData[] = [
                    'exam_session_id' => $session->id,
                    'question_id' => $qId,
                    'urutan' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            try {
                DB::transaction(function () use ($insertData) {
                    ExamSessionQuestionOrder::insert($insertData);
                });
                $orders = $shuffledIds->flip()->map(fn ($idx) => $idx + 1)->all();
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'Duplicate entry')) {
                    throw $e;
                }

                // Race: request lain baru saja meng-insert urutan untuk sesi ini.
                // Pakai urutan pemenang alih-alih melempar error ke peserta.
                $orders = ExamSessionQuestionOrder::query()
                    ->where('exam_session_id', $session->id)
                    ->pluck('urutan', 'question_id')
                    ->all();
            }
        } else {
            $orders = $existingOrders->toArray();

            $missingQuestionIds = $validQuestions->pluck('id')->diff(array_keys($orders))->values();

            if ($missingQuestionIds->isNotEmpty()) {
                $maxUrutan = empty($orders) ? 0 : max($orders);
                $newInsertData = [];
                $now = now();

                foreach ($missingQuestionIds as $offset => $qId) {
                    $nextUrutan = $maxUrutan + $offset + 1;
                    $orders[$qId] = $nextUrutan;
                    $newInsertData[] = [
                        'exam_session_id' => $session->id,
                        'question_id' => $qId,
                        'urutan' => $nextUrutan,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                try {
                    DB::transaction(function () use ($newInsertData) {
                        ExamSessionQuestionOrder::insert($newInsertData);
                    });
                } catch (QueryException $e) {
                    if (! str_contains($e->getMessage(), 'Duplicate entry')) {
                        throw $e;
                    }

                    // Race: request lain sudah append soal yang sama. Re-fetch
                    // dan pakai urutan tersimpan agar tidak menabrak unique.
                    $orders = ExamSessionQuestionOrder::query()
                        ->where('exam_session_id', $session->id)
                        ->pluck('urutan', 'question_id')
                        ->all();
                }
            }
        }

        return $validQuestions->sortBy(fn (Question $q) => $orders[$q->id] ?? PHP_INT_MAX)->values();
    }
}
