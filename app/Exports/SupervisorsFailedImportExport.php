<?php

namespace App\Exports;

use App\Exports\Concerns\WithSheetStyling;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SupervisorsFailedImportExport implements FromArray, WithEvents, WithHeadings, WithTitle
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
                $data['name'] ?? '-',
                $data['email'] ?? '-',
                implode(' ', $invalidRow['errors']),
            ];
        }, $this->invalidRows);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Baris', 'Nama', 'Email', 'Keterangan'];
    }

    public function title(): string
    {
        return 'Baris Gagal';
    }

    protected function themePrimaryColor(): string
    {
        return 'BA1A1A';
    }

    protected function afterSheetStyling(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow > 1) {
            $sheet->getStyle('D2:D'.$highestRow)->getAlignment()->setWrapText(true);
        }
    }
}
