<?php

return [
    'validation' => [
        'fqdn_not_resolvable' => 'O FQDN ou IP informado não resolve para um endereço IP válido.',
        'fqdn_required_for_ssl' => 'É necessário um domínio totalmente qualificado apontando para um IP público para usar SSL neste nó.',
    ],
    'notices' => [
        'allocations_added' => 'As alocações foram adicionadas com sucesso a este nó.',
        'node_deleted' => 'O nó foi removido com sucesso do painel.',
        'location_required' => 'Você precisa configurar pelo menos uma localização antes de adicionar um nó.',
        'node_created' => 'O novo nó foi criado com sucesso. Você pode configurar o daemon automaticamente pela aba "Configuração". Antes de adicionar servidores, aloque pelo menos um endereço IP e porta.',
        'node_updated' => 'As informações do nó foram atualizadas. Se alguma configuração do daemon foi alterada, será necessário reiniciá-lo.',
        'unallocated_deleted' => 'Todas as portas não alocadas de <code>:ip</code> foram excluídas.',
    ],
];
