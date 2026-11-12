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

## Tarefas dentro dos projetos — 12/11/2026

Em **Projetos → nome do projeto (ou Detalhes) → Tarefas do projeto → Gerenciar tarefas**, o Spot abre o quadro daquele projeto. É possível criar, consultar, editar, excluir e mudar o status conforme as permissões existentes. O seletor de projeto no cadastro já vem preenchido e fica bloqueado nesse contexto. **Fechar quadro de tarefas** retorna à relação resumida; documentos continuam disponíveis na mesma página. Lista e calendário também podem ser usados nesse espaço.

`GET /api/v1/tasks?project_id=ID` restringe tarefas, projetos disponíveis, clientes e pessoas ao projeto solicitado. A seleção continua sujeita à empresa, atribuição ao projeto e visibilidade das tarefas; a filtragem não concede permissões. Projetos finalizados aparecem para consulta e não oferecem cadastro/edição. As operações de escrita usam os endpoints e a auditoria já existentes. Não há migração nem alteração de dados anteriores.

No quadro geral e no quadro do projeto, o título visível tem no máximo **15 caracteres, incluindo a reticência `…`**. A segmentação usa grafemas quando `Intl.Segmenter` está disponível, preservando acentos combinados e emojis compostos. Em navegadores sem essa API, o fallback usa pontos de código. O título completo permanece no nome acessível e no atributo `title` do botão; clicar ou pressionar Enter abre os detalhes com título e descrição completos. A truncagem não altera o conteúdo salvo nem a descrição exibida na lista e nos detalhes.

### Verificação da alteração

- Build de produção aprovado.
- `TaskV1CrudTest` e `ProjectResourcesTest`: 14 testes, 144 asserções. Incluem filtro por projeto, pessoas restritas, empresa diferente, projeto sem atribuição, visibilidade por analista, validação do filtro e projeto finalizado.
- `npm run test:tasks`: CRUD nas três vistas e movimentação com mouse, toque e teclado, persistência e tratamento de falhas.
- `node scripts/test-project-tasks.mjs`: quadro incorporado, títulos de 15 grafemas (incluindo emoji), acesso ao conteúdo completo, criação/edição/exclusão no projeto, confirmação com Escape e retorno do foco, somente leitura e rótulos pt/en/es em 320 e 1440 px, com leitura a 120%.
- `node scripts/test-project-resources.mjs`: tarefas e documentos, buscas, retomada após falha, teclado, temas claro/escuro e 320/1440 px a 120%.
- `node scripts/test-accessibility.mjs`: foco, teclado, modais, movimento reduzido e recursos de leitura.
- `node scripts/test-languages.mjs`: 144 verificações sem falhas.
- `npm run test:responsive` (build e execução da suíte): 323 verificações sem erros; retrato/paisagem, 320–1440 px, 100% e 120%. Após o ajuste de largura do quadro incorporado, a mesma suíte foi novamente executada por `node scripts/test-responsive.mjs`.

A inspeção do cenário de tela estreita identificou cartões maiores que sua coluna no quadro incorporado; as regras de grade foram corrigidas para manter os cartões dentro da coluna e a rolagem dentro do quadro. As cores e os tamanhos mínimos de interação existentes foram preservados, e os textos das ações continuam disponíveis no celular. Não foi realizada auditoria integral de contraste.

Os testes de navegador usam Chromium e APIs simuladas. Não houve revisão com leitor de tela, dispositivos físicos, Firefox/Safari ou pessoas com deficiência. Os testes automatizados não certificam conformidade WCAG 2.2 AA.
