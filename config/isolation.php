<?php

use App\Security\IsolationPaths;

return [
    'forbidden_database' => env('ISOLATION_FORBIDDEN_DATABASE', 'fokuscloud'),

    /*
    | Homes de outros produtos no mesmo VPS, informados em
    | LAW_ISOLATION_FOREIGN_PATHS (lista separada por vírgula). O comando
    | law:isolation-check falha se o usuário do site conseguir lê-los.
    | Caminhos ausentes neste host não contam como prova. O padrão genérico
    | serve só de exemplo; o ambiente de produção define a lista real em
    | shared/.env, fora deste repositório.
    */
    'foreign_paths' => IsolationPaths::parse(env('LAW_ISOLATION_FOREIGN_PATHS')),
];
