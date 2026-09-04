<?php

return [
    'sign_in' => 'Entrar',
    'go_to_login' => 'Ir para o login',
    'failed' => 'Nenhuma conta foi encontrada com essas credenciais.',

    'sso' => [
        'failed' => 'O login único não foi concluído. Tente novamente.',
        'not_permitted' => 'Essa conta não pode entrar com login único.',
        'not_configured' => 'O login único não está configurado neste painel.',
    ],

    'forgot_password' => [
        'label' => 'Esqueceu a senha?',
        'label_help' => 'Informe o email da sua conta para receber instruções de redefinição de senha.',
        'button' => 'Recuperar conta',
    ],

    'reset_password' => [
        'button' => 'Redefinir e entrar',
    ],

    'two_factor' => [
        'label' => 'Token 2FA',
        'label_help' => 'Esta conta exige uma segunda camada de autenticação. Informe o código gerado pelo seu dispositivo para continuar.',
        'checkpoint_failed' => 'O token de autenticação em duas etapas é inválido.',
    ],

    'throttle' => 'Muitas tentativas de login. Tente novamente em :seconds segundos.',
    'password_requirements' => 'A senha deve ter pelo menos 8 caracteres e ser única para este painel.',
    '2fa_must_be_enabled' => 'O administrador exige que a autenticação em duas etapas esteja ativada para usar o painel.',
];
