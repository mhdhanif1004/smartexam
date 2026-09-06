<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionClassroomIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_soal_mapel_sama_kelas_berbeda_tidak_saling_bocor(): void
    {
        // Setup: satu mapel yang sama untuk semua tingkat
        $subject = Subject::factory()->create(['name' => 'Bahasa Indonesia']);

        $kelasX = Classroom::factory()->create(['name' => 'X RPL 1']);
        $kelasXi = Classroom::factory()->create(['name' => 'XI RPL 1']);

        // Soal A hanya untuk kelas X, Soal B hanya untuk kelas XI
        $soalA = Question::factory()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Soal A khusus kelas X',
            'is_active' => true,
        ]);
        $soalA->classrooms()->sync([$kelasX->id]);

        $soalB = Question::factory()->create([
            'subject_id' => $subject->id,
            'question_text' => 'Soal B khusus kelas XI',
            'is_active' => true,
        ]);
        $soalB->classrooms()->sync([$kelasXi->id]);

        // Query yang dipakai Peserta\ExamController::work() — filter ganda subject + targetingClassroom
        $soalUntukX = Question::query()
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->targetingClassroom($kelasX->id)
            ->orderBy('id')
            ->get();

        $soalUntukXi = Question::query()
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->targetingClassroom($kelasXi->id)
            ->orderBy('id')
            ->get();

        // Assert: peserta kelas X hanya lihat soal A
        $this->assertCount(1, $soalUntukX, 'Kelas X seharusnya hanya melihat 1 soal');
        $this->assertTrue($soalUntukX->contains('id', $soalA->id));
        $this->assertFalse($soalUntukX->contains('id', $soalB->id));

        // Assert: peserta kelas XI hanya lihat soal B
        $this->assertCount(1, $soalUntukXi, 'Kelas XI seharusnya hanya melihat 1 soal');
        $this->assertTrue($soalUntukXi->contains('id', $soalB->id));
        $this->assertFalse($soalUntukXi->contains('id', $soalA->id));

        // Assert: tidak ada intersection
        $idsX = $soalUntukX->pluck('id')->all();
        $idsXi = $soalUntukXi->pluck('id')->all();
        $this->assertEmpty(array_intersect($idsX, $idsXi), 'Soal X dan XI tidak boleh beririsan');
    }

    public function test_scope_targeting_classroom_mengisolasi_per_kelas_walau_subject_sama(): void
    {
        $subject = Subject::factory()->create(['name' => 'Bahasa Indonesia']);

        $kelasX = Classroom::factory()->create(['name' => 'X RPL 1']);
        $kelasXi = Classroom::factory()->create(['name' => 'XI RPL 1']);
        $kelasXii = Classroom::factory()->create(['name' => 'XII RPL 1']);

        $soalX = Question::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);
        $soalX->classrooms()->sync([$kelasX->id]);

        $soalXi = Question::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);
        $soalXi->classrooms()->sync([$kelasXi->id]);

        $soalXii = Question::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);
        $soalXii->classrooms()->sync([$kelasXii->id]);

        $this->assertTrue(Question::targetingClassroom($kelasX->id)->whereKey($soalX->id)->exists());
        $this->assertFalse(Question::targetingClassroom($kelasX->id)->whereKey($soalXi->id)->exists());
        $this->assertFalse(Question::targetingClassroom($kelasX->id)->whereKey($soalXii->id)->exists());

        $this->assertTrue(Question::targetingClassroom($kelasXi->id)->whereKey($soalXi->id)->exists());
        $this->assertFalse(Question::targetingClassroom($kelasXi->id)->whereKey($soalX->id)->exists());

        // Soal tanpa pivot tidak muncul di kelas manapun
        $soalTanpaKelas = Question::factory()->create(['subject_id' => $subject->id, 'is_active' => true]);
        $this->assertFalse(Question::targetingClassroom($kelasX->id)->whereKey($soalTanpaKelas->id)->exists());
        $this->assertFalse(Question::targetingClassroom($kelasXi->id)->whereKey($soalTanpaKelas->id)->exists());
    }
}
