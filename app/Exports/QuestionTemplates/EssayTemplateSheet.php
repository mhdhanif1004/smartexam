<?php

namespace App\Exports\QuestionTemplates;

class EssayTemplateSheet extends BaseQuestionTemplateSheet
{
    public function title(): string
    {
        return 'Data Essay';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Pertanyaan', 'Kunci Jawaban / Rubrik', 'Bobot'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleRow(): array
    {
        return [
            'Mata Pelajaran' => 'Bahasa Indonesia',
            'Pertanyaan' => 'CONTOH: Jelaskan dengan bahasamu sendiri pentingnya membaca.',
            'Kunci Jawaban / Rubrik' => 'Kunci jawaban untuk koreksi manual (boleh dikosongkan).',
            'Bobot' => 20,
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
            'C' => 40,
            'D' => 10,
        ];
    }

    protected function wrapColumns(): array
    {
        return ['C'];
    }
}
