<?php

namespace App\Exports\QuestionTemplates;

class SingleChoiceTemplateSheet extends BaseQuestionTemplateSheet
{
    public function title(): string
    {
        return 'Data Pilihan Ganda';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Kunci Jawaban', 'Bobot'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleRow(): array
    {
        return [
            'Mata Pelajaran' => 'Matematika',
            'Pertanyaan' => 'CONTOH: Berapa hasil dari 2 + 2?',
            'Opsi A' => '3',
            'Opsi B' => '4',
            'Opsi C' => '5',
            'Opsi D' => '6',
            'Opsi E' => '',
            'Kunci Jawaban' => 'B',
            'Bobot' => 10,
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
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 15,
            'G' => 15,
            'H' => 14,
            'I' => 10,
        ];
    }

    protected function answerValidationRange(): ?string
    {
        return 'H2:H1001';
    }

    protected function answerValidationOptions(): array
    {
        return ['A', 'B', 'C', 'D', 'E'];
    }
}
