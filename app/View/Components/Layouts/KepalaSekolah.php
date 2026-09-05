<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;
use Illuminate\View\View;

class KepalaSekolah extends Component
{
    public function __construct(public string $title = 'Dashboard Kepala Sekolah') {}

    public function render(): View
    {
        return view('layouts.kepala_sekolah');
    }
}
