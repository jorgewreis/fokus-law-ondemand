<?php

return [
    'modules' => [
        'people' => 'Gestão de Pessoas',
        'processes' => 'Gestão de Processos',
        'users' => 'Gestão de Usuários',
        'system' => 'Gestão Básica do Sistema',
    ],

    'actions' => [
        'read' => 'Consultar',
        'create' => 'Cadastrar',
        'update' => 'Alterar',
        'delete' => 'Excluir',
        'archive' => 'Arquivar',
        'update_self' => 'Alterar próprios dados',
    ],

    'grantable' => [
        'people' => ['read', 'create', 'update', 'delete'],
        'processes' => ['read', 'create', 'update', 'archive'],
        'users' => ['read', 'update_self'],
        'system' => ['read', 'update_self'],
    ],
];
