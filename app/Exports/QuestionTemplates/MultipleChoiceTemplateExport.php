<?php

namespace App\Exports\QuestionTemplates;

/**
 * Template impor soal pilihan ganda banyak jawaban.
 *
 * Hanya memuat satu sheet "Data Pilihan Ganda Banyak". Instruksi pengisian
 * tidak lagi dibuat dalam sheet "Petunjuk" terpisah karena gagal dirender
 * dengan baik di Excel.
 */
class MultipleChoiceTemplateExport extends MultipleChoiceTemplateSheet {}
