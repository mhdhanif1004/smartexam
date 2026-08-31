<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\GuruMapel;
use App\Models\Question;
use App\Models\Subject;
use App\Models\TeacherSubjectClassAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionTargetBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Kelas-count grade di-cache di properti static per-request. Supaya
        // tiap test menghitung ulang dari DB sendiri (yang di-rollback antar
        // test), cache dibersihkan di sini via refleksi.
        $ref = new \ReflectionProperty(Classroom::class, 'gradeCountsCache');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }

    private function makeGuru(): array
    {
        $guru = GuruMapel::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        TeacherSubjectClassAssignment::create([
            'guru_mapel_id' => $guru->id,
            'subject_id' => $subject->id,
        ]);

        return [$guru, $subject];
    }

    private function makeQuestion(GuruMapel $guru, int $subjectId, array $classroomIds): Question
    {
        $question = Question::query()->create([
            'subject_id' => $subjectId,
            'type' => Question::TYPE_SINGLE_CHOICE,
            'question_text' => 'Soal target kelas',
            'options' => ['A' => 'Satu', 'B' => 'Dua'],
            'answer_key' => 'A',
            'score_weight' => 10,
            'created_by_user_id' => $guru->user->id,
        ]);
        $question->classrooms()->sync($classroomIds);

        return $question;
    }

    public function test_full_level_renders_single_compact_badge(): void
    {
        [$guru, $subject] = $this->makeGuru();

        $xii = [];
        foreach (range(1, 16) as $i) {
            $xii[] = Classroom::factory()->create(['name' => "XII AKL {$i}"])->id;
        }
        $this->makeQuestion($guru, $subject->id, $xii);

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('XII (Semua Kelas)', $html);
        $this->assertStringNotContainsString('XII AKL 1</span>', $html);
        $this->assertStringNotContainsString('XII AKL 16</span>', $html);
        $this->assertSame(1, substr_count($html, 'XII (Semua Kelas)'));
    }

    public function test_partial_level_keeps_individual_badges(): void
    {
        [$guru, $subject] = $this->makeGuru();

        $xii = [];
        foreach (range(1, 16) as $i) {
            $xii[] = Classroom::factory()->create(['name' => "XII AKL {$i}"])->id;
        }
        $partial = array_slice($xii, 0, 5);
        $this->makeQuestion($guru, $subject->id, $partial);

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('XII (Semua Kelas)', $html);
        $this->assertStringContainsString('XII AKL 1', $html);
        $this->assertStringContainsString('XII AKL 5', $html);
    }

    public function test_mixed_levels_combine_compact_and_individual(): void
    {
        [$guru, $subject] = $this->makeGuru();

        $xi = [];
        foreach (range(1, 8) as $i) {
            $xi[] = Classroom::factory()->create(['name' => "XI RPL {$i}"])->id;
        }
        $xiiA = Classroom::factory()->create(['name' => 'XII AKL 1'])->id;
        $xiiB = Classroom::factory()->create(['name' => 'XII AKL 2'])->id;
        // Sempurnakan XII supaya lengkap seluruh tingkatnya.
        foreach (range(3, 16) as $i) {
            Classroom::factory()->create(['name' => "XII AKL {$i}"]);
        }

        $this->makeQuestion($guru, $subject->id, array_merge($xi, [$xiiA, $xiiB]));

        $html = $this->actingAs($guru->user)
            ->get(route('guru_mapel.questions.index'))
            ->assertOk()
            ->getContent();

        // XI lengkap → ringkas. XII cuma sebagian → individual.
        $this->assertStringContainsString('XI (Semua Kelas)', $html);
        $this->assertStringContainsString('XII AKL 1', $html);
        $this->assertStringContainsString('XII AKL 2', $html);
        $this->assertStringNotContainsString('XII (Semua Kelas)', $html);
    }

    public function test_summarize_targets_output_unchanged(): void
    {
        $x = Classroom::factory()->create(['name' => 'X A'])->id;
        $xi1 = Classroom::factory()->create(['name' => 'XI RPL 1'])->id;
        $xi2 = Classroom::factory()->create(['name' => 'XI RPL 2'])->id;
        // Kelas XI lain yang TIDAK ditarget → XI jadi cuma sebagian.
        Classroom::factory()->create(['name' => 'XI TKJ 1']);
        Classroom::factory()->create(['name' => 'XI TKJ 2']);

        // X (satu-satunya kelas level X) lengkap → "X"; XI cuma sebagian
        // (2 dari 4) → tetap nama individual, urutan masuknya.
        $this->assertSame('X, XI RPL 1, XI RPL 2', Classroom::summarizeTargets([$x, $xi1, $xi2]));
    }
}
