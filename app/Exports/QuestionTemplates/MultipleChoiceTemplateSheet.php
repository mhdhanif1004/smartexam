<?php

namespace App\Exports\QuestionTemplates;

class MultipleChoiceTemplateSheet extends BaseQuestionTemplateSheet
{
    public function title(): string
    {
        return 'Data Pilihan Ganda Banyak';
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
            'Pertanyaan' => 'CONTOH: Manakah yang termasuk bilangan prima?',
            'Opsi A' => '2',
            'Opsi B' => '4',
            'Opsi C' => '6',
            'Opsi D' => '7',
            'Opsi E' => '9',
            'Kunci Jawaban' => 'A,D',
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
}
