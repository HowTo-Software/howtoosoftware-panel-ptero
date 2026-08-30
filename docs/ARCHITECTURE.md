# Arquitetura HowToo sobre Pterodactyl

## Regra central

A interface HowToo e uma customizacao do Pterodactyl real. Ela nao mantem uma segunda fonte de servidores e nao simula estados de maquina.

```text
Browser
  -> Laravel/Pterodactyl (sessao, CSRF, 2FA e permissoes)
      -> MySQL/Redis (usuarios, servidores e configuracoes)
      -> Wings via API/WebSocket (estado, console, arquivos e energia)
      -> Servicos HowToo server-side (integracoes externas)
```

## Servidores

- A listagem vem dos modelos e endpoints oficiais do Pterodactyl.
- O servidor selecionado e carregado pelo `ServerContext` oficial.
- Console e metricas usam o websocket autenticado do Pterodactyl/Wings.
- Arquivos, bancos, backups, rede e energia continuam protegidos pelas permissoes oficiais.
- A barra lateral apenas organiza essas rotas; ela nao replica a logica delas.

Servidores de teste devem ser criados somente em um ambiente de homologacao e nunca versionados em codigo, seeds ou fixtures de producao.

## Idioma

O middleware Laravel escolhe `pt` ou `en` pelo cabecalho `Accept-Language`. O cliente React faz a mesma escolha com `navigator.languages`. O endpoint de traducoes aceita os lotes produzidos pelo adaptador i18next, mas valida estritamente os idiomas e os nomes dos namespaces.

## Integracoes

As credenciais de Ollama, Steam e CurseForge seguem estas regras:

1. Apenas administradores raiz acessam o formulario.
2. O navegador envia uma chave nova por POST protegido por CSRF, mas nunca recebe a chave atual.
3. O Laravel criptografa o valor com `APP_KEY` antes de persistir.
4. Somente servicos backend leem a chave descriptografada.
5. A URL configuravel do Ollama e validada e normalizada no backend; as demais URLs base pertencem ao codigo.
6. Respostas de provedores devem ser validadas e filtradas antes de qualquer operacao em arquivos.

Nenhuma integracao pode receber acesso irrestrito ao filesystem do host. Operacoes futuras em arquivos devem usar os endpoints autorizados do Pterodactyl/Wings e ficar limitadas ao servidor selecionado.

## Atualizacao do upstream

A referencia atual e Pterodactyl `v1.15.1`. Antes de incorporar uma nova versao:

1. Leia as notas de seguranca e migracoes do upstream.
2. Integre os commits em uma branch separada.
3. Resolva conflitos preservando middlewares, politicas e contratos de API oficiais.
4. Rode PHP, TypeScript, build e testes de integracao com MySQL.
5. Valide login, 2FA, websocket, console, arquivos e todas as permissoes de subusuario.
