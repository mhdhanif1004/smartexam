<?php

namespace App\Exports\QuestionTemplates;

/**
 * Template impor soal pilihan ganda (1 jawaban).
 *
 * Hanya memuat satu sheet "Data Pilihan Ganda". Instruksi pengisian tidak
 * lagi dibuat dalam sheet "Petunjuk" terpisah karena gagal dirender dengan
 * baik di Excel.
 */
class SingleChoiceTemplateExport extends SingleChoiceTemplateSheet {}
