<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuruMapel extends Component
{
    public function __construct(public string $title = 'Dashboard') {}

    public function render(): View
    {
        return view('layouts.guru_mapel');
    }
}
