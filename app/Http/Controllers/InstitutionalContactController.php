<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class InstitutionalContactController extends Controller
{
    public function __invoke(): View
    {
        return view('institutional-contacts.show', [
            'contacts' => [
                'phones' => [
                    ['label' => 'Cartório', 'display' => '(11) 0000-0001', 'tel' => '+551100000001'],
                    ['label' => 'Diretoria', 'display' => '(11) 0000-0002', 'tel' => '+551100000002'],
                    ['label' => 'Gabinete', 'display' => '(11) 0000-0003', 'tel' => '+551100000003'],
                    ['label' => 'Assessoria', 'display' => '(11) 0000-0004', 'tel' => '+551100000004'],
                    ['label' => 'Audiência', 'display' => '(11) 0000-0005', 'tel' => '+551100000005'],
                ],
                'whatsapp' => [
                    'display' => '(11) 90000-0000',
                    'url' => 'https://wa.me/5511900000000',
                ],
                'email' => 'contato@dominio-do-cliente.example',
                'address' => 'Endereço institucional sintético do cliente on demand.',
                'hours' => null,
                'source' => 'https://example.com/contatos-institucionais',
                'checkedAt' => '19/11/2026',
            ],
        ]);
    }
}
