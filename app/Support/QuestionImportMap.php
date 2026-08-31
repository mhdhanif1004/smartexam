<?php

namespace App\Support;

use App\Exports\QuestionTemplates\EssayTemplateExport;
use App\Exports\QuestionTemplates\MatchingTemplateExport;
use App\Exports\QuestionTemplates\MultipleChoiceTemplateExport;
use App\Exports\QuestionTemplates\SingleChoiceTemplateExport;
use App\Exports\QuestionTemplates\TrueFalseTemplateExport;
use App\Imports\Questions\EssayImport;
use App\Imports\Questions\MatchingImport;
use App\Imports\Questions\MultipleChoiceImport;
use App\Imports\Questions\SingleChoiceImport;
use App\Imports\Questions\TrueFalseImport;
use App\Models\Question;

/**
 * Peta bersama jenis soal => class import, template export, dan metadata
 * file. Dipakai oleh controller impor/ekspor soal Admin dan Guru Mapel agar
 * definisi pasangan jenis-tipe tidak terduplikasi (lihat trait
 * BuildsQuestionPayload). Murni berisi data (constant), tanpa logika.
 */
final class QuestionImportMap
{
    /**
     * @return array<string, array{export: class-string, import: class-string, file: string, label: string}>
     */
    public static function templates(): array
    {
        return [
            Question::TYPE_SINGLE_CHOICE => [
                'export' => SingleChoiceTemplateExport::class,
                'import' => SingleChoiceImport::class,
                'file' => 'template-import-pilihan-ganda.xlsx',
                'label' => 'Pilihan Ganda',
            ],
            Question::TYPE_MULTIPLE_CHOICE => [
                'export' => MultipleChoiceTemplateExport::class,
                'import' => MultipleChoiceImport::class,
                'file' => 'template-import-pilihan-ganda-banyak.xlsx',
                'label' => 'Pilihan Ganda Banyak',
            ],
            Question::TYPE_TRUE_FALSE => [
                'export' => TrueFalseTemplateExport::class,
                'import' => TrueFalseImport::class,
                'file' => 'template-import-benar-salah.xlsx',
                'label' => 'Benar/Salah',
            ],
            Question::TYPE_MATCHING => [
                'export' => MatchingTemplateExport::class,
                'import' => MatchingImport::class,
                'file' => 'template-import-menjodohkan.xlsx',
                'label' => 'Menjodohkan',
            ],
            Question::TYPE_ESSAY => [
                'export' => EssayTemplateExport::class,
                'import' => EssayImport::class,
                'file' => 'template-import-essay.xlsx',
                'label' => 'Essay',
            ],
        ];
    }
}
