<?php

namespace App\Exports\QuestionTemplates;

/**
 * Template impor soal benar/salah.
 *
 * Hanya memuat satu sheet "Data Benar Salah". Instruksi pengisian tidak
 * lagi dibuat dalam sheet "Petunjuk" terpisah karena gagal dirender dengan
 * baik di Excel.
 */
class TrueFalseTemplateExport extends TrueFalseTemplateSheet {}
