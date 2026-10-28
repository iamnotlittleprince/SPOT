# Mudança de status pelo quadro

Implementado em 28/10/2026 na tela Tarefas, visualização **Quadro**.

## Como usar

- No computador, arraste um cartão para **A fazer**, **Em andamento**, **Em revisão** ou **Concluído**. A coluna de destino fica destacada. Colunas vazias também recebem cartões.
- Em telas de toque, arraste pela alça junto ao nome do projeto. Próximo às bordas do quadro, o movimento ajuda a rolar horizontalmente. A área restante mantém a rolagem normal da página.
- O seletor **Status** em cada cartão editável oferece a mesma operação por teclado, clique ou toque, inclusive quando o destino está fora da tela.
- O cartão muda de coluna imediatamente. Durante o envio, as novas movimentações ficam bloqueadas; se a requisição falhar, o cartão retorna à coluna anterior e uma mensagem explica o erro. O foco do seletor é restaurado após a resposta.
- Soltar na própria coluna não envia uma alteração. Não foi incluída ordenação manual dentro da coluna.

## Persistência e permissões

`PATCH /api/v1/tasks/{task}/status` recebe somente o status necessário:

```json
{"status": "in_progress"}
```

O servidor aceita `todo`, `in_progress`, `review` e `done`, verifica empresa, acesso ao projeto, visibilidade da tarefa e permissão de edição. Coparticipação permite consulta conforme as regras existentes, mas não concede edição por si só. Projetos finalizados não permitem alterações; cartões somente leitura não podem ser arrastados e não apresentam seletor.

A rota altera apenas `status`, preservando responsável, projeto, datas, duração, descrição e valores financeiros, inclusive em tarefas antigas sem todos os campos atuais. A mudança e sua auditoria são transacionais. O evento `task.status_changed` guarda os estados anterior e novo e reutiliza o fluxo de notificações existente. Operações sem mudança não geram nova auditoria. Não há migração de banco.

## Verificações

- `tests/Feature/TaskBoardStatusTest.php`: quatro estados, campos preservados, validação, auditoria, rollback, acesso por empresa, responsável/coparticipante/convidado, projeto finalizado e sessão obrigatória.
- `scripts/test-task-board.mjs`: arraste com mouse, eventos reais de toque via Chromium, colunas vazias, seletor com restauração de foco, falha 422 e retorno ao estado anterior, consulta após recarregar e cartões somente leitura, desktop/mobile com leitura em 120%.
- Testes existentes de CRUD e auditoria de tarefas, build e verificações compartilhadas de responsividade, teclado e traduções.

Os testes de interface usam API simulada; os testes PHP verificam autorização e persistência no banco de testes. Toque foi exercitado em emulação Chromium; não houve avaliação em dispositivo físico, Firefox/Safari ou leitor de tela nesta alteração.
