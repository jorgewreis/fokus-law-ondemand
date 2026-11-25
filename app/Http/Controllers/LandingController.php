<?php

namespace App\Http\Controllers;

use App\Dashboard\UnitBoard;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(UnitBoard $board): View
    {
        return view('landing.show', [
            'board' => $board->present(now()),
            'cadastroAtivo' => old('form') === 'cadastro',
        ]);
    }
}
