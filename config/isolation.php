<?php

return [
    'forbidden_database' => env('ISOLATION_FORBIDDEN_DATABASE', 'fokuscloud'),

    /*
    | Homes de outros produtos no mesmo VPS. O comando law:isolation-check
    | falha se o usuário do site conseguir lê-los. Caminhos ausentes neste
    | host não contam como prova.
    */
    'foreign_paths' => [
        '/home/fokuscloud',
        '/home/cloud',
        '/home/lead',
        '/home/fokus',
    ],
];
