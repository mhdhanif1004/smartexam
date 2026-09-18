<?php

namespace App\Console\Commands;

use App\Models\Question;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Tautkan soal ke guru mapel pemilik (teacher_guru_mapel_id) berdasarkan
 * penugasan mapel-kelas.
 *
 * Aturan pencocokan:
 * - Assignment dgn classroom_id eksplisit hanya mencocokkan soal yang
 *   menargetkan kelas itu.
 * - Soal HANYA di-link bila seluruh kelas targetnya dipegang oleh SATU
 *   guru yang sama (klaim guru berbeda pada kelas mana pun → ditinjau
 *   manual). Soal tanpa match dibiarkan null (bucket "Belum Ada Guru").
 *
 * Selain match, command juga backfill teacher_guru_mapel_id dari
 * created_by_user_id yang sudah terisi (soal legacy buatan guru), agar
 * kolom kepemilikan baru konsisten.
 */
class LinkAdminQuestionsToGuru extends Command
{
    protected $signature = 'exam:link-admin-questions {--dry-run : tampilkan rencana tanpa mengubah data}';

    protected $description = 'Tautkan soal ke guru mapel pemilik (teacher_guru_mapel_id) via penugasan mapel-kelas; assignment tanpa kelas (classroom_id null) berlaku wildcard per mapel. Idempotent.';

    public function handle(): int
    {
        $questions = Question::query()
            ->with('classrooms', 'creator.guruMapel')
            ->get();

        // Assignment di-load sekali lalu di-group per subject. Kelas eksplisit
        // dan baris wildcard (classroom_id null) keduanya diambil.
        $assignments = TeacherSubjectClassAssignment::query()
            ->with('guruMapel')
            ->get()
            ->groupBy('subject_id');

        $linked = 0;
        $backfilled = 0;
        $split = 0;
        $noMatch = 0;
        $already = 0;
        $splitDetails = [];

        foreach ($questions as $question) {
            if ($question->teacher_guru_mapel_id !== null) {
                $already++;

                continue;
            }

            // Backfill dari creator lama (kolom legacy), tanpa perlu match.
            if ($question->created_by_user_id !== null) {
                $legacyGuru = $question->creator?->guruMapel;

                if ($legacyGuru !== null) {
                    $backfilled++;
                    $this->line("  [backfill] Soal #{$question->id} -> guru_mapel_id {$legacyGuru->id} (dari creator user_id {$question->created_by_user_id})");

                    if (! $this->option('dry-run')) {
                        $question->update(['teacher_guru_mapel_id' => $legacyGuru->id]);
                    }

                    continue;
                }

                // Creator tanpa profil guru mapel — perlakukan seperti tanpa owner.
            }

            /** @var Collection<array-key, int> $classroomIds */
            $classroomIds = $question->classrooms->pluck('id');

            if ($classroomIds->isEmpty()) {
                $noMatch++;

                continue;
            }

            // Guru pemilik tiap kelas target pada mapel soal. Hanya assignment
            // eksplisit (classroom_id terisi) yang mencocokkan soal.
            $gurusByClass = $classroomIds->mapWithKeys(function (int $classroomId) use ($assignments, $question) {
                $guruIds = ($assignments[$question->subject_id] ?? collect())
                    ->filter(fn (TeacherSubjectClassAssignment $assignment) => $assignment->classroom_id === $classroomId)
                    ->pluck('guru_mapel_id')
                    ->unique()
                    ->values();

                return [$classroomId => $guruIds];
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

            $ownerGuruMapelId = (int) $distinctGurus->first();
            $ownerUserId = $assignments[$question->subject_id]
                ->firstWhere('guru_mapel_id', $ownerGuruMapelId)?->guruMapel?->user_id;
            $linked++;

            if ($this->option('dry-run')) {
                $this->line("  [rencana] Soal #{$question->id} -> guru_mapel_id {$ownerGuruMapelId} (kelas: ".implode(', ', $question->classrooms->pluck('name')->all()).')');

                continue;
            }

            $question->update([
                'teacher_guru_mapel_id' => $ownerGuruMapelId,
                'created_by_user_id' => $ownerUserId,
            ]);
            $this->line("  [ok] Soal #{$question->id} -> guru_mapel_id {$ownerGuruMapelId} (kelas: ".implode(', ', $question->classrooms->pluck('name')->all()).')');
        }

        $this->info("Selesai: {$linked} soal ter-link, {$backfilled} backfill dari creator, {$already} sudah terisi, {$split} di-skip (kelas terpecah ke beberapa guru), {$noMatch} di-skip (tidak ada guru yang mengampu seluruh kelas target).");

        foreach ($splitDetails as $detail) {
            $this->warn('  [tinjau manual] '.$detail);
        }

        return self::SUCCESS;
    }
}
