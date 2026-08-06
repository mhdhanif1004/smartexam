<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;
use Illuminate\View\View;

class Peserta extends Component
{
    public function __construct(public string $title = 'Dashboard') {}

    public function render(): View
    {
        return view('layouts.peserta');
    }
}
