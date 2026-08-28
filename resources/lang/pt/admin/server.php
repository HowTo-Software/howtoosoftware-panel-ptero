<?php

return [
    'exceptions' => [
        'no_new_default_allocation' => 'Você está tentando remover a alocação padrão deste servidor, mas não há outra alocação disponível.',
        'marked_as_failed' => 'Este servidor foi marcado como falho em uma instalação anterior. O status atual não pode ser alternado nesse estado.',
        'bad_variable' => 'Houve um erro de validação na variável :name.',
        'daemon_exception' => 'Houve uma exceção ao comunicar com o daemon, resultando em uma resposta HTTP/:code. A exceção foi registrada. (request id: :request_id)',
        'default_allocation_not_found' => 'A alocação padrão solicitada não foi encontrada nas alocações deste servidor.',
    ],
    'alerts' => [
        'startup_changed' => 'A configuração de inicialização do servidor foi atualizada. Se o nest ou egg foi alterado, uma reinstalação será iniciada.',
        'server_deleted' => 'O servidor foi excluído com sucesso do sistema.',
        'server_created' => 'O servidor foi criado com sucesso no painel. Aguarde alguns minutos para o daemon concluir a instalação.',
        'build_updated' => 'Os detalhes de build do servidor foram atualizados. Algumas alterações podem exigir reinício.',
        'suspension_toggled' => 'O status de suspensão do servidor foi alterado para :status.',
        'rebuild_on_boot' => 'Este servidor foi marcado para reconstrução do container Docker na próxima inicialização.',
        'install_toggled' => 'O status de instalação deste servidor foi alternado.',
        'server_reinstalled' => 'Este servidor foi colocado na fila para reinstalação.',
        'details_updated' => 'Os detalhes do servidor foram atualizados com sucesso.',
        'docker_image_updated' => 'A imagem Docker padrão do servidor foi alterada. É necessário reiniciar para aplicar.',
        'node_required' => 'Você precisa configurar pelo menos um nó antes de adicionar um servidor.',
        'transfer_nodes_required' => 'Você precisa configurar pelo menos dois nós antes de transferir servidores.',
        'transfer_started' => 'A transferência do servidor foi iniciada.',
        'transfer_not_viable' => 'O nó selecionado não possui disco ou memória suficientes para este servidor.',
    ],
];
