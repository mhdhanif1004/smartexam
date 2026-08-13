<?php

namespace App\Exports\QuestionTemplates;

/**
 * Template impor soal essay.
 *
 * Hanya memuat satu sheet "Data Essay". Instruksi pengisian tidak lagi
 * dibuat dalam sheet "Petunjuk" terpisah karena gagal dirender dengan baik
 * di Excel.
 */
class EssayTemplateExport extends EssayTemplateSheet {}
