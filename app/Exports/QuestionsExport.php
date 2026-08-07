<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Question;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class QuestionsExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Question>  $rows
     */
    public function __construct(private readonly Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Jenis', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Jawaban', 'Bobot', 'Kiri', 'Kanan'];
    }

    /**
     * @param  Question  $question
     * @return array<int, mixed>
     */
    public function map($question): array
    {
        $options = collect($question->options ?? []);
        $letters = ['A', 'B', 'C', 'D', 'E'];
        $cells = [];

        foreach ($letters as $letter) {
            $cells[] = (string) $options->get($letter, '');
        }

        $answer = match ($question->type) {
            Question::TYPE_SINGLE_CHOICE => (string) ($question->answer_key ?? ''),
            Question::TYPE_MULTIPLE_CHOICE => is_array($question->answer_key) ? implode(',', $question->answer_key) : '',
            Question::TYPE_TRUE_FALSE => $question->answer_key ? 'Benar' : 'Salah',
            Question::TYPE_MATCHING => '',
            Question::TYPE_ESSAY => (string) ($question->answer_key ?? ''),
            default => '',
        };

        [$left, $right] = $question->type === Question::TYPE_MATCHING
            ? [implode("\n", $question->options['left'] ?? []), implode("\n", $question->options['right'] ?? [])]
            : ['', ''];

        return [
            $question->subject?->name ?? '-',
            $question->typeLabel(),
            $question->question_text,
            $cells[0],
            $cells[1],
            $cells[2],
            $cells[3],
            $cells[4],
            $answer,
            (float) $question->score_weight,
            $left,
            $right,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 20,
            'C' => 45,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 15,
            'I' => 12,
            'J' => 8,
            'K' => 20,
            'L' => 20,
        ];
    }
}
