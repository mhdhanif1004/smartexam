<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;
use Illuminate\View\View;

class PesertaExam extends Component
{
    public function __construct(public string $title = 'Ujian') {}

    public function render(): View
    {
        return view('layouts.peserta-exam');
    }
}
