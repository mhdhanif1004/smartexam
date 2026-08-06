<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;
use Illuminate\View\View;

class Pengawas extends Component
{
    public function __construct(public string $title = 'Dashboard') {}

    public function render(): View
    {
        return view('layouts.pengawas');
    }
}
