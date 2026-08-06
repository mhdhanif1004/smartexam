<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsTemplateExport implements FromCollection, WithHeadings, WithMapping, WithColumnFormatting, WithColumnWidths, WithEvents
{
    use WithSheetStyling;

    /**
     * @param  Collection<int, string>  $classNames
     */
    public function __construct(private readonly Collection $classNames) {}

    public function collection(): Collection
    {
        return new Collection([
            [
                'nisn' => '0012345678',
                'name' => 'Nama Contoh Siswa',
                'class_name' => 'XI RPL 1',
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['NISN', 'Nama', 'Kelas'];
    }

    /**
     * @param  array{nisn: string, name: string, class_name: string}  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [$row['nisn'], $row['name'], $row['class_name']];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => '@',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 30,
            'C' => 16,
        ];
    }

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        $classNames = $this->classNames
            ->map(fn (string $name): string => (string) $name)
            ->values()
            ->all();

        if (empty($classNames)) {
            return;
        }

        $validation = $sheet->getDataValidation('C2:C1001');
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setFormula1('"'.implode(',', $classNames).'"');
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Kelas Tidak Valid');
        $validation->setError('Kelas harus dipilih dari daftar yang tersedia.');
    }
}
