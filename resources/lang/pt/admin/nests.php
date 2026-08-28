<?php

return [
    'notices' => [
        'created' => 'O nest :name foi criado com sucesso.',
        'deleted' => 'O nest solicitado foi excluído com sucesso do painel.',
        'updated' => 'A configuração do nest foi atualizada com sucesso.',
    ],
    'eggs' => [
        'notices' => [
            'imported' => 'Este egg e suas variáveis foram importados com sucesso.',
            'updated_via_import' => 'Este egg foi atualizado usando o arquivo enviado.',
            'deleted' => 'O egg solicitado foi excluído com sucesso do painel.',
            'updated' => 'A configuração do egg foi atualizada com sucesso.',
            'script_updated' => 'O script de instalação do egg foi atualizado e será executado quando servidores forem instalados.',
            'egg_created' => 'Um novo egg foi criado com sucesso. Reinicie os daemons em execução para aplicar o novo egg.',
        ],
    ],
    'variables' => [
        'notices' => [
            'variable_deleted' => 'A variável ":variable" foi excluída e não estará mais disponível após reconstruir os servidores.',
            'variable_updated' => 'A variável ":variable" foi atualizada. Reconstrua os servidores que usam essa variável para aplicar a alteração.',
            'variable_created' => 'A nova variável foi criada e atribuída ao egg com sucesso.',
        ],
    ],
];
