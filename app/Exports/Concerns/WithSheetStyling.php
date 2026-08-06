<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait WithSheetStyling
{
    /**
     * Warna latar header (Material blue primary).
     */
    protected function themePrimaryColor(): string
    {
        return '00288E';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->getSheet()->getDelegate();
                $this->applySheetStyling($sheet);
                $this->afterSheetStyling($sheet);
            },
        ];
    }

    protected function applySheetStyling(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        $sheet->getStyle('A1:'.$highestColumn.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $this->themePrimaryColor()],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        if ($highestRow > 1) {
            $sheet->getStyle('A1:'.$highestColumn.$highestRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'D1D5DB'],
                    ],
                ],
            ]);

            for ($row = 2; $row <= $highestRow; $row += 2) {
                $sheet->getStyle('A'.$row.':'.$highestColumn.$row)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                ]);
            }
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$highestColumn.$highestRow);
    }

    /**
     * Hook tambahan setelah styling dasar (bisa dioverride subclass).
     */
    protected function afterSheetStyling(Worksheet $sheet): void
    {
    }
}
