# Exclusão de cadastro pendente — revisão de 11/11/2026

## Comportamento atual

Administradores podem excluir um usuário em Aguardando ativação, inclusive Convidado. O destino depende dos vínculos:

- Sem impedimento de integridade: exclusão física do cadastro, como antes.
- Com histórico vinculado: exclusão lógica. O registro recebe `archived_at`, sai de Usuários e acessos, fica desativado e conserva seu identificador e nome para referências históricas. Seu e-mail principal é substituído por um identificador interno único no domínio reservado `users.invalid`, liberando o endereço original para novo cadastro.

A exclusão lógica não apaga conversas, mensagens, projetos ou registros financeiros. Desvincula identidades e tokens Google/Microsoft, remove senha/credenciais de autenticação, revoga ativações, limpa recuperações de senha, sessões e tokens pessoais da conta. Administrar ou reativar diretamente um registro arquivado pelo endpoint de usuários retorna 404. Usuários ativos continuam sujeitos à desativação, sem exclusão por essa operação.

As APIs autenticadas de sessão/Sanctum e JWT recusam contas arquivadas, inclusive com JWT emitido antes da exclusão.

A confirmação da interface explica que o acesso será removido e o histórico será preservado, em pt/en/es.

## Causa do problema apresentado

O PostgreSQL recusava a exclusão física com SQLSTATE 23503 por vínculo em `chat_conversations`. O tratamento anterior apenas trocava a falha interna por uma validação, mantendo o cadastro bloqueado. Esta revisão substitui a validação pelo arquivamento transacional quando essa recusa ocorre. Outros erros de banco continuam sendo propagados.

O controlador bloqueia a linha do usuário e verifica novamente sua situação durante a operação. A tentativa de exclusão física é desfeita antes do arquivamento, sem duplicar auditoria. O evento `user.deleted` registra `mode=archived` e `history_preserved=true` quando a exclusão é lógica.

## Verificações

- Suíte PHP completa: 260 testes / 3.532 asserções aprovados.
- Casos administrativos: 14 testes / 112 asserções, incluindo e-mail reutilizável, conservação de conversa/mensagem/autoria, remoção da lista, impedimento de reativação direta e revogação de credenciais/sessões/tokens.
- Build aprovado, 144 verificações de idiomas sem falhas, teste de teclado/modal e ajustes de leitura aprovado.
- Perfil Convidado disponível na criação e edição, mantendo suas permissões restritas.
- Fluxo de tela com API simulada: confirmação informa preservação do histórico; exclusão remove a linha e atualiza a listagem.

Não foram excluídos ou arquivados cadastros reais durante a validação. A migração aditiva foi aplicada localmente; isso não significa aplicação na VM de produção. Os testes usam schema isolado e serviços simulados. Não houve alteração de layout, ensaio com leitor de tela nem homologação Azure nesta revisão.

## Publicação na VM Docker

Esta versão exige a migration `2026_11_11_180000_add_archived_at_to_users.php`. Após receber o código e fazer backup do banco e arquivos, o responsável pode usar, na pasta da instalação que utiliza o Compose deste repositório:

```bash
docker compose --env-file .env.production build web worker scheduler
docker compose --env-file .env.production run --rm --no-deps web php artisan migrate --force
docker compose --env-file .env.production up -d web worker scheduler
```

Preservar `.env.production`, chaves e volumes existentes. A migration acrescenta uma coluna nula aos usuários e não arquiva nenhuma conta por conta própria. As ações de exclusão ocorrem somente quando confirmadas na administração.
