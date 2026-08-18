<?php

namespace App\Exports\QuestionTemplates;

use App\Exports\Concerns\WithSheetStyling;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet data template impor soal. Header baris 1 terkunci, baris 2 adalah
 * contoh (otomatis dilewati saat impor), data diisi mulai baris 3.
 */
abstract class BaseQuestionTemplateSheet implements FromCollection, WithColumnWidths, WithEvents, WithHeadings, WithMapping, WithTitle
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, Subject>  $subjects
     */
    public function __construct(
        protected readonly Collection $subjects,
    ) {}

    abstract public function title(): string;

    /**
     * @return array<int, string>
     */
    abstract public function headings(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function exampleRow(): array;

    /**
     * @return array<string, int>
     */
    abstract public function columnWidths(): array;

    /**
     * Kolom yang teksnya boleh memuat banyak baris (wrap text).
     *
     * @return list<string>
     */
    protected function wrapColumns(): array
    {
        return [];
    }

    /**
     * Kolom "Kelas Target" tidak lagi dipakai di template — target kelas dipilih
     * melalui multi-select di modal impor, bukan diisi pada file Excel.
     */

    /**
     * Rentang sel untuk drop-down kunci jawaban, null jika tidak ada.
     */
    protected function answerValidationRange(): ?string
    {
        return null;
    }

    /**
     * Catatan (cell comment) pada header kolom, key = alamat sel (contoh 'C1').
     * Muncul sebagai segitiga merah saat kolom di-hover — pengganti sheet
     * "Petunjuk" terpisah untuk instruksi khusus jenis soal.
     *
     * @return array<string, string>
     */
    protected function headerComments(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function answerValidationOptions(): array
    {
        return [];
    }

    public function collection(): Collection
    {
        return new Collection([$this->exampleRow()]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return array_values($row);
    }

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        $lastColumn = $sheet->getHighestDataColumn();

        // Baris contoh ditandai warna kuning agar jelas bukan data.
        $sheet->getStyle('A2:'.$lastColumn.'2')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFF3CD');

        // Header baris 1 dikunci; sel data tetap bisa diedit.
        $sheet->getStyle('A1:'.$lastColumn.'1')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $sheet->getStyle('A2:'.$lastColumn.'1000')->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getProtection()->setSheet(true);

        foreach ($this->wrapColumns() as $column) {
            $sheet->getStyle($column.'2:'.$column.'1000')->getAlignment()->setWrapText(true);
        }

        $subjectNames = $this->subjects
            ->map(fn ($subject) => (string) $subject->name)
            ->values()
            ->all();

        if (! empty($subjectNames)) {
            $validation = $sheet->getDataValidation('A2:A1001');
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1('"'.implode(',', $subjectNames).'"');
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Mata Pelajaran Tidak Valid');
            $validation->setError('Mata pelajaran harus dipilih dari daftar yang tersedia.');
        }

        $answerRange = $this->answerValidationRange();
        $answerOptions = $this->answerValidationOptions();

        if ($answerRange !== null && ! empty($answerOptions)) {
            $validation = $sheet->getDataValidation($answerRange);
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setFormula1('"'.implode(',', $answerOptions).'"');
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Kunci Jawaban Tidak Valid');
            $validation->setError('Pilih kunci jawaban dari daftar yang tersedia.');
        }

        foreach ($this->headerComments() as $cell => $text) {
            $comment = $sheet->getComment($cell);
            $comment->setAuthor('SmartExam');
            $comment->setWidth('320pt');
            $comment->setHeight('140pt');
            $comment->getText()->createTextRun($text);
        }
    }
}
