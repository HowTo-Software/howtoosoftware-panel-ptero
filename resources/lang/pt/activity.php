<?php

return [
    'auth' => [
        'fail' => 'Falha ao entrar',
        'success' => 'Entrou na conta',
        'password-reset' => 'Senha redefinida',
        'reset-password' => 'Solicitou redefinição de senha',
        'checkpoint' => 'Autenticação em duas etapas solicitada',
        'recovery-token' => 'Usou token de recuperação 2FA',
        'token' => 'Concluiu desafio 2FA',
        'ip-blocked' => 'Bloqueou requisição de IP não permitido para :identifier',
        'sftp' => [
            'fail' => 'Falha ao entrar via SFTP',
        ],
    ],
    'user' => [
        'account' => [
            'email-changed' => 'Alterou o email de :old para :new',
            'password-changed' => 'Alterou a senha',
        ],
        'api-key' => [
            'create' => 'Criou a chave de API :identifier',
            'delete' => 'Excluiu a chave de API :identifier',
        ],
        'ssh-key' => [
            'create' => 'Adicionou a chave SSH :fingerprint à conta',
            'delete' => 'Removeu a chave SSH :fingerprint da conta',
        ],
        'two-factor' => [
            'create' => 'Ativou autenticação em duas etapas',
            'delete' => 'Desativou autenticação em duas etapas',
        ],
    ],
    'server' => [
        'reinstall' => 'Reinstalou o servidor',
        'console' => [
            'command' => 'Executou ":command" no servidor',
        ],
        'power' => [
            'start' => 'Iniciou o servidor',
            'stop' => 'Parou o servidor',
            'restart' => 'Reiniciou o servidor',
            'kill' => 'Forçou o encerramento do servidor',
        ],
        'backup' => [
            'download' => 'Baixou o backup :name',
            'delete' => 'Excluiu o backup :name',
            'restore' => 'Restaurou o backup :name (arquivos excluídos: :truncate)',
            'restore-complete' => 'Concluiu a restauração do backup :name',
            'restore-failed' => 'Falha ao concluir a restauração do backup :name',
            'start' => 'Iniciou um novo backup :name',
            'complete' => 'Marcou o backup :name como concluído',
            'fail' => 'Marcou o backup :name como falho',
            'lock' => 'Bloqueou o backup :name',
            'unlock' => 'Desbloqueou o backup :name',
        ],
        'database' => [
            'create' => 'Criou o banco de dados :name',
            'rotate-password' => 'Girou a senha do banco de dados :name',
            'delete' => 'Excluiu o banco de dados :name',
        ],
        'file' => [
            'compress_one' => 'Compactou :directory:file',
            'compress_other' => 'Compactou :count arquivos em :directory',
            'read' => 'Visualizou o conteúdo de :file',
            'copy' => 'Criou uma cópia de :file',
            'create-directory' => 'Criou o diretório :directory:name',
            'decompress' => 'Descompactou :files em :directory',
            'delete_one' => 'Excluiu :directory:files.0',
            'delete_other' => 'Excluiu :count arquivos em :directory',
            'download' => 'Baixou :file',
            'pull' => 'Baixou um arquivo remoto de :url para :directory',
            'rename_one' => 'Renomeou :directory:files.0.from para :directory:files.0.to',
            'rename_other' => 'Renomeou :count arquivos',
            'write' => 'Gravou novo conteúdo em :file',
            'upload' => 'Iniciou um upload de arquivo',
            'uploaded' => 'Enviou :directory:file',
        ],
        'sftp' => [
            'denied' => 'Bloqueou acesso SFTP por falta de permissão',
            'create_one' => 'Criou :files.0',
            'create_other' => 'Criou :count arquivos',
            'write_one' => 'Modificou o conteúdo de :files.0',
            'write_other' => 'Modificou o conteúdo de :count arquivos',
            'delete_one' => 'Excluiu :files.0',
            'delete_other' => 'Excluiu :count arquivos',
            'create-directory_one' => 'Criou o diretório :files.0',
            'create-directory_other' => 'Criou :count diretórios',
            'rename_one' => 'Renomeou :files.0.from para :files.0.to',
            'rename_other' => 'Renomeou ou moveu :count arquivos',
        ],
        'allocation' => [
            'create' => 'Adicionou :allocation ao servidor',
            'notes' => 'Atualizou as notas de :allocation de ":old" para ":new"',
            'primary' => 'Definiu :allocation como alocação primária do servidor',
            'delete' => 'Excluiu a alocação :allocation',
        ],
        'schedule' => [
            'create' => 'Criou o agendamento :name',
            'update' => 'Atualizou o agendamento :name',
            'execute' => 'Executou manualmente o agendamento :name',
            'delete' => 'Excluiu o agendamento :name',
        ],
        'task' => [
            'create' => 'Criou uma tarefa ":action" para o agendamento :name',
            'update' => 'Atualizou a tarefa ":action" do agendamento :name',
            'delete' => 'Excluiu uma tarefa do agendamento :name',
        ],
        'settings' => [
            'rename' => 'Renomeou o servidor de :old para :new',
            'description' => 'Alterou a descrição do servidor de :old para :new',
        ],
        'startup' => [
            'edit' => 'Alterou a variável :variable de ":old" para ":new"',
            'image' => 'Atualizou a imagem Docker do servidor de :old para :new',
        ],
        'subuser' => [
            'create' => 'Adicionou :email como subusuário',
            'update' => 'Atualizou as permissões do subusuário :email',
            'delete' => 'Removeu :email como subusuário',
        ],
    ],
];
