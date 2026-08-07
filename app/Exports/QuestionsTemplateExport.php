<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Question;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class QuestionsTemplateExport implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Subject>  $subjects
     */
    public function __construct(private readonly Collection $subjects) {}

    public function collection(): Collection
    {
        return new Collection([
            ['subject' => 'Matematika', 'type' => 'Pilihan Ganda', 'question' => 'Berapa hasil dari 2 + 2?', 'option_a' => '3', 'option_b' => '4', 'option_c' => '5', 'option_d' => '6', 'option_e' => '', 'answer' => 'B', 'weight' => 10, 'left' => '', 'right' => ''],
            ['subject' => 'Matematika', 'type' => 'Pilihan Ganda Banyak', 'question' => 'Manakah yang termasuk bilangan prima?', 'option_a' => '2', 'option_b' => '4', 'option_c' => '6', 'option_d' => '7', 'option_e' => '9', 'answer' => 'A,D', 'weight' => 10, 'left' => '', 'right' => ''],
            ['subject' => 'IPA', 'type' => 'Benar/Salah', 'question' => 'Bumi itu bulat.', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'option_e' => '', 'answer' => 'Benar', 'weight' => 5, 'left' => '', 'right' => ''],
            ['subject' => 'IPA', 'type' => 'Menjodohkan', 'question' => 'Jodohkan hewan dengan suaranya.', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'option_e' => '', 'answer' => '', 'weight' => 10, 'left' => "Kucing\nAnjing\nAyam", 'right' => "Meong\nGuk\nKukuruyuk"],
            ['subject' => 'Bahasa Indonesia', 'type' => 'Essay', 'question' => 'Jelaskan dengan bahasamu sendiri tentang pentingnya membaca.', 'option_a' => '', 'option_b' => '', 'option_c' => '', 'option_d' => '', 'option_e' => '', 'answer' => 'Kunci jawaban untuk koreksi manual (boleh dikosongkan).', 'weight' => 20, 'left' => '', 'right' => ''],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Jenis', 'Pertanyaan', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Opsi E', 'Jawaban', 'Bobot', 'Kiri', 'Kanan'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row['subject'],
            $row['type'],
            $row['question'],
            $row['option_a'],
            $row['option_b'],
            $row['option_c'],
            $row['option_d'],
            $row['option_e'],
            $row['answer'],
            $row['weight'],
            $row['left'],
            $row['right'],
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

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        $typeNames = array_values([
            Question::TYPE_SINGLE_CHOICE => 'Pilihan Ganda',
            Question::TYPE_MULTIPLE_CHOICE => 'Pilihan Ganda Banyak',
            Question::TYPE_TRUE_FALSE => 'Benar/Salah',
            Question::TYPE_MATCHING => 'Menjodohkan',
            Question::TYPE_ESSAY => 'Essay',
        ]);

        $validation = $sheet->getDataValidation('B2:B1001');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setFormula1('"'.implode(',', $typeNames).'"');
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Jenis Tidak Valid');
        $validation->setError('Jenis harus dipilih dari daftar yang tersedia.');
    }
}
