<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Tautkan soal buatan admin (created_by_user_id null) ke guru mapel yang
 * mengampu mapel + SEMUA kelas target soal tersebut.
 *
 * Aturan keselamatan: soal HANYA di-link bila seluruh kelas targetnya
 * dipegang oleh SATU guru yang sama. Bila kelas target terpecah ke beberapa
 * guru berbeda (atau tidak ada guru yang mengampu seluruh kelas), soal
 * dibiarkan milik admin dan dicatat untuk ditinjau manual.
 */
class LinkAdminQuestionsToGuru extends Command
{
    protected $signature = 'exam:link-admin-questions {--dry-run : tampilkan rencana tanpa mengubah data}';

    protected $description = 'Tautkan soal buatan admin (created_by_user_id null) ke guru mapel yang mengampu seluruh kelas target soal. Idempotent: hanya memproses soal yang masih null.';

    public function handle(): int
    {
        $questions = Question::query()
            ->whereNull('created_by_user_id')
            ->with('classrooms')
            ->get();

        // Assignment di-load sekali lalu di-group per subject (kelas target
        // hanya dihitung dari baris dengan classroom_id eksplisit — konsisten
        // dengan GuruMapel::ampuClassroomIds).
        $assignments = TeacherSubjectClassAssignment::query()
            ->with('guruMapel')
            ->get()
            ->groupBy('subject_id');

        $linked = 0;
        $split = 0;
        $noMatch = 0;
        $splitDetails = [];

        foreach ($questions as $question) {
            /** @var Collection<array-key, int> $classroomIds */
            $classroomIds = $question->classrooms->pluck('id');

            if ($classroomIds->isEmpty()) {
                $noMatch++;

                continue;
            }

            // Guru (users.id) pemilik tiap kelas target pada mapel soal.
            $gurusByClass = $classroomIds->mapWithKeys(function (int $classroomId) use ($assignments, $question) {
                $guruUserIds = ($assignments[$question->subject_id] ?? collect())
                    ->where('classroom_id', $classroomId)
                    ->map(fn ($assignment) => $assignment->guruMapel?->user_id)
                    ->filter()
                    ->unique()
                    ->values();

                return [$classroomId => $guruUserIds];
            });

            // Ada kelas target tanpa pemilik sama sekali → tidak bisa di-link.
            if ($gurusByClass->contains(fn (Collection $ids) => $ids->isEmpty())) {
                $noMatch++;

                continue;
            }

            $distinctGurus = $gurusByClass->flatten()->unique()->values();

            if ($distinctGurus->count() !== 1) {
                $split++;
                $splitDetails[] = 'Soal #'.$question->id.' (mapel '.$question->subject->name.') kelas ['.implode(', ', $question->classrooms->pluck('name')->all()).'] → '.$distinctGurus->count().' guru berbeda';

                continue;
            }

            $ownerUserId = (int) $distinctGurus->first();
            $linked++;

            if ($this->option('dry-run')) {
                $this->line("  [rencana] Soal #{$question->id} → user_id {$ownerUserId} (kelas: ".implode(', ', $question->classrooms->pluck('name')->all()).')');

                continue;
            }

            $question->update(['created_by_user_id' => $ownerUserId]);
            $this->line("  [ok] Soal #{$question->id} → user_id {$ownerUserId} (kelas: ".implode(', ', $question->classrooms->pluck('name')->all()).')');
        }

        $this->info("Selesai: {$linked} soal ter-link, {$split} di-skip (kelas terpecah ke beberapa guru), {$noMatch} di-skip (tidak ada guru yang mengampu seluruh kelas target).");

        foreach ($splitDetails as $detail) {
            $this->warn('  [tinjau manual] '.$detail);
        }

        return self::SUCCESS;
    }
}
