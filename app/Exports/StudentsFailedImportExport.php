<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsFailedImportExport implements FromArray, WithHeadings, WithTitle, WithColumnFormatting, WithEvents
{
    use WithSheetStyling;

    /**
     * @param  list<array{row: int, data: array<string, mixed>, errors: list<string>}>  $invalidRows
     */
    public function __construct(private readonly array $invalidRows) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(function (array $invalidRow): array {
            $data = $invalidRow['data'];

            return [
                $invalidRow['row'],
                $data['nisn'] ?? '-',
                $data['name'] ?? '-',
                $data['class_name'] ?? '-',
                implode(' ', $invalidRow['errors']),
            ];
        }, $this->invalidRows);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Baris', 'NISN', 'Nama', 'Kelas', 'Keterangan'];
    }

    public function title(): string
    {
        return 'Baris Gagal';
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => '0',
            'B' => '@',
        ];
    }

    protected function themePrimaryColor(): string
    {
        return 'BA1A1A';
    }

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow > 1) {
            $sheet->getStyle('E2:E'.$highestRow)->getAlignment()->setWrapText(true);
        }
    }
}
