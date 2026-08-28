<?php

return [
    'email' => [
        'title' => 'Atualizar email',
        'updated' => 'Seu endereço de email foi atualizado.',
    ],
    'password' => [
        'title' => 'Alterar senha',
        'requirements' => 'Sua nova senha deve ter pelo menos 8 caracteres.',
        'updated' => 'Sua senha foi atualizada.',
    ],
    'two_factor' => [
        'button' => 'Configurar autenticação em duas etapas',
        'disabled' => 'A autenticação em duas etapas foi desativada na sua conta. Você não precisará mais informar um token ao entrar.',
        'enabled' => 'A autenticação em duas etapas foi ativada na sua conta. A partir de agora, será necessário informar o código gerado pelo seu dispositivo ao entrar.',
        'invalid' => 'O token informado é inválido.',
        'setup' => [
            'title' => 'Configurar autenticação em duas etapas',
            'help' => 'Não consegue escanear o código? Digite o código abaixo no seu aplicativo:',
            'field' => 'Digite o token',
        ],
        'disable' => [
            'title' => 'Desativar autenticação em duas etapas',
            'field' => 'Digite o token',
        ],
    ],
];
