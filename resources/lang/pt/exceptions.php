<?php

return [
    'daemon_connection_failed' => 'Houve uma exceção ao comunicar com o daemon, resultando em uma resposta HTTP/:code. A exceção foi registrada.',
    'node' => [
        'servers_attached' => 'Um nó não pode ter servidores vinculados para ser excluído.',
        'daemon_off_config_updated' => 'A configuração do daemon foi atualizada, mas houve erro ao tentar atualizar automaticamente o arquivo de configuração no daemon. Atualize manualmente o config.yml para aplicar as alterações.',
    ],
    'allocations' => [
        'server_using' => 'Um servidor está usando esta alocação. Ela só pode ser excluída quando não estiver atribuída a nenhum servidor.',
        'too_many_ports' => 'Não é possível adicionar mais de 1000 portas em uma única faixa.',
        'invalid_mapping' => 'O mapeamento informado para :port é inválido e não pôde ser processado.',
        'cidr_out_of_range' => 'A notação CIDR permite apenas máscaras entre /25 e /32.',
        'port_out_of_range' => 'As portas de uma alocação devem ser maiores que 1024 e menores ou iguais a 65535.',
    ],
    'nest' => [
        'delete_has_servers' => 'Um nest com servidores ativos não pode ser excluído do painel.',
        'egg' => [
            'delete_has_servers' => 'Um egg com servidores ativos não pode ser excluído do painel.',
            'invalid_copy_id' => 'O egg selecionado para copiar script não existe ou já está copiando um script.',
            'must_be_child' => 'A opção "Copiar configurações de" deste egg precisa ser filha do nest selecionado.',
            'has_children' => 'Este egg é pai de um ou mais eggs. Exclua esses eggs antes de continuar.',
        ],
        'variables' => [
            'env_not_unique' => 'A variável de ambiente :name precisa ser única neste egg.',
            'reserved_name' => 'A variável de ambiente :name é protegida e não pode ser usada.',
            'bad_validation_rule' => 'A regra de validação ":rule" não é válida para esta aplicação.',
        ],
        'importer' => [
            'json_error' => 'Houve um erro ao interpretar o arquivo JSON: :error.',
            'file_error' => 'O arquivo JSON informado não é válido.',
            'invalid_json_provided' => 'O arquivo JSON informado não está em um formato reconhecido.',
        ],
    ],
    'subusers' => [
        'editing_self' => 'Não é permitido editar sua própria conta de subusuário.',
        'user_is_owner' => 'Você não pode adicionar o dono do servidor como subusuário.',
        'subuser_exists' => 'Um usuário com esse email já está atribuído como subusuário deste servidor.',
    ],
    'databases' => [
        'delete_has_databases' => 'Não é possível excluir um host de banco de dados com bancos ativos vinculados.',
    ],
    'tasks' => [
        'chain_interval_too_long' => 'O intervalo máximo para tarefas encadeadas é de 15 minutos.',
    ],
    'locations' => [
        'has_nodes' => 'Não é possível excluir uma localização com nós ativos vinculados.',
    ],
    'users' => [
        'node_revocation_failed' => 'Falha ao revogar chaves no <a href=":link">Nó #:node</a>. :error',
    ],
    'deployment' => [
        'no_viable_nodes' => 'Nenhum nó atende aos requisitos informados para deploy automático.',
        'no_viable_allocations' => 'Nenhuma alocação atende aos requisitos para deploy automático.',
    ],
    'api' => [
        'resource_not_found' => 'O recurso solicitado não existe neste servidor.',
    ],
];
