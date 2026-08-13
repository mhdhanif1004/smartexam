<?php

namespace App\Exports\QuestionTemplates;

class MatchingTemplateSheet extends BaseQuestionTemplateSheet
{
    public function title(): string
    {
        return 'Data Menjodohkan';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Mata Pelajaran', 'Pertanyaan', 'Kiri', 'Kanan', 'Bobot'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exampleRow(): array
    {
        return [
            'Mata Pelajaran' => 'IPA',
            'Pertanyaan' => 'CONTOH: Jodohkan hewan dengan suaranya.',
            'Kiri' => "Kucing\nAnjing\nAyam",
            'Kanan' => "Meong\nGuk\nKukuruyuk",
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
            'C' => 30,
            'D' => 30,
            'E' => 10,
        ];
    }

    protected function wrapColumns(): array
    {
        return ['C', 'D'];
    }

    /**
     * Instruksi cara mengisi pasangan item dipindah ke catatan sel (cell
     * comment) pada header, karena sheet "Petunjuk" terpisah sudah dihapus.
     *
     * @return array<string, string>
     */
    protected function headerComments(): array
    {
        return [
            'B1' => "Pertanyaan adalah petunjuk pengerjaan.\nContoh: Jodohkan hewan dengan suaranya.",
            'C1' => "Isi setiap item pada BARIS TERPISAH dalam satu sel (pakai Alt+Enter / Ctrl+Enter).\nUrutan baris menentukan pasangannya: item Kiri baris 1 dipasangkan dengan item Kanan baris 1, dst.\nJumlah item minimal 2 dan harus sama dengan kolom Kanan.",
            'D1' => "Pasangan mengikuti urutan baris: item Kanan baris 1 dipasangkan dengan item Kiri baris 1, dst.\nJumlah item harus sama dengan kolom Kiri (minimal 2 pasangan).",
        ];
    }
}
