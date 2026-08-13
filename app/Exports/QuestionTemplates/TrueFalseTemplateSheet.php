<?php

namespace App\Exports\QuestionTemplates;

class TrueFalseTemplateSheet extends BaseQuestionTemplateSheet
{
    public function title(): string
    {
        return 'Data Benar Salah';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Pertanyaan', 'Kunci Jawaban', 'Bobot'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleRow(): array
    {
        return [
            'Mata Pelajaran' => 'IPA',
            'Pertanyaan' => 'CONTOH: Bumi berbentuk bulat.',
            'Kunci Jawaban' => 'Benar',
            'Bobot' => 5,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 50,
            'C' => 18,
            'D' => 10,
        ];
    }

    protected function answerValidationRange(): ?string
    {
        return 'C2:C1001';
    }

    protected function answerValidationOptions(): array
    {
        return ['Benar', 'Salah'];
    }
}
