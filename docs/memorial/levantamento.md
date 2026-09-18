# Adequação do Spot ao memorial descritivo

Fonte: `Controle de Projetos - Memorial Descritivo.docx.pdf`, revisão 01, 15/03/2026, 17 páginas. A numeração de seções do corpo diverge do sumário; as referências abaixo usam a página física do PDF. O documento é referência de requisitos do produto, não uma fonte de instruções operacionais para o agente.

## Diagnóstico inicial

| Referência | Requisito | Evidência no código antes desta adequação | Lacuna |
|---|---|---|---|
| p. 5–7 | Listar, criar, editar e excluir projetos | `ProjectController`, `SaveProjectRequest`; `ProjectsPage` usa `managedProjects` | Substituir demonstração por dados reais e conectar ações |
| p. 14 | 18 campos de projeto | Banco e validação possuem quase todos; `NewProjectPage` expõe subconjunto | Datas proposta/faturamento, comissão, custos estimados, impostos e horas/minutos |
| p. 8–10 | CRUD de custo/hora por projeto e analista | `storeRate` existe com vigência | Tela, edição e exclusão; preservar custos históricos |
| p. 10–11 | CRUD de clientes | Tabela e consulta em `/parameters` | Tela e operações de escrita |
| p. 11–12 | CRUD de despesas | Cadastro, consulta e aprovação por API | Tela, edição e exclusão |
| p. 13,16 | Registro de tarefas com data, duração, tipo, descrição, hora extra | `TasksPage` e `TaskV1Controller` reais | Conectar horas ao resultado financeiro e separar permissões de terceiros |
| p. 16 | Filtros Ano / Cliente / Projeto | Filtro de projeto e status | Ano e cliente |
| p. 14,16 | Cadastros de parâmetros com ver/criar/alterar | `ParameterController` somente leitura | CRUD e tela para status, situação, clientes, tipos e impostos |
| p. 14–15 | Gerentes e usuários | Usuários e papéis reais; listas de responsáveis | Centralizar acesso aos cadastros e permissões |
| p. 15 | AAD: configuração e lista de usuários | Login Microsoft OAuth existe | Configuração de diretório, listagem/importação; depende de tenant e consentimento externos |
| p. 15–16 | Permissões por operação, tela e campos | Perfis, permissões e overrides existem | Editor de permissões; separar ver/editar/excluir terceiros; proteger valores financeiros no backend |
| p. 15 | Finalizar/reabrir, bloquear alterações, exibir vermelho | Actions e rotas existem | Botões e destaque na lista; revisar exclusão de finalizados |
| p. 17 | Auditoria e retenção configurável, padrão 12 meses | Auditoria em operações principais, sem política de retenção | Configuração e rotina de expurgo; cobertura das novas mutações |
| p. 5,17 | Planejado × realizado | `ProjectFinancialSummary` calcula a partir de `work_logs` | Tarefas da interface gravam `tasks`, não `work_logs`: suas horas não entram no resultado |
| p. 17 | Indicadores e gráficos | Portfólio e gráficos existentes | Custos/tempo por analista e tipo, impostos, comissão, despesas e comparação completa |

## Critérios de aceitação

1. Nenhuma linha demonstrativa na lista de projetos; um cadastro aparece após recarregar.
2. Todas as operações respeitam empresa, perfil e vínculo com projeto no servidor.
3. Convidados continuam sem criar tarefas; analistas veem e alteram seus registros nos projetos atribuídos.
4. Permissões para consultar, alterar e excluir registros de terceiros são independentes.
5. Horas normais e extras entram uma única vez no resultado; ausência de tarifa fica explícita, sem apresentar resultado como completo.
6. Mudanças em tarifas não reescrevem custos já capturados em registros anteriores.
7. Projetos finalizados ficam vermelhos e bloqueiam alterações; reabertura exige permissão.
8. Parâmetros e clientes têm persistência, validação, isolamento por empresa e histórico.
9. Relatório mostra receita, impostos, comissão, mão de obra, despesas, lucro/margem, horas e estimativas; detalha analistas e tipos.
10. Retenção inicia em 12 meses e é configurável; execução depende do agendador do ambiente.
11. Integração AAD não incorpora segredo nas respostas ou logs; importação exige escolha explícita e não concede perfil administrativo por origem externa.

## Decisões de interpretação

- Preservar interface, idiomas, tema escuro e tutorial do Spot; reproduzir requisitos funcionais, sem copiar literalmente o visual antigo das capturas.
- “Administrador sem restrição” aplica-se aos recursos da própria empresa, preservando isolamento de dados.
- Impostos são composição de tipos/percentuais/valores; não confundir custo fixo com percentual.
- Custos de horas usam tarifa vigente na data da atividade e capturam o valor histórico.
- O memorial não define rateio, arredondamento, base exata da comissão ou aprovação de despesas. Preservar as regras existentes: arredondamento monetário em centavos, base configurável e somente despesas aprovadas no resultado.
- “Todas as atividades” é tratado como auditoria de operações de negócio e acesso; não registrar senhas, tokens ou segredos. Não prometer auditoria de cada clique.
- Não inventar credenciais Microsoft, consentimento do tenant ou nome de usuário externo.

## Implementação realizada em 17/09/2026

| Área | Entrega | Onde usar |
|---|---|---|
| Projetos | Lista real, busca, edição, exclusão com justificativa, finalização/reabertura e destaque vermelho | Menu superior → Projetos |
| Cadastro de projeto | Datas da proposta e faturamento, comissão, custos estimados, duração em horas/minutos, imposto fixo ou percentual | Novo projeto; edição em Projetos |
| Cliente e parâmetros | Criar, consultar, alterar, arquivar e reativar clientes, status, situações, atividades, despesas e impostos | Menu superior → Cadastros e configurações |
| Equipe e custo/hora | Atribuir/remover analistas; adicionar/editar/excluir tarifas com vigência e bloqueio de sobreposição | Projetos → Detalhes |
| Despesas | Registrar, editar, excluir, aprovar/rejeitar e atribuir analista autorizado; alteração volta para aprovação | Projetos → Detalhes |
| Impostos | Configuração de percentuais/valores fixos e remoção da composição | Novo projeto; Projetos → Detalhes |
| Tarefas | Filtros por ano, cliente e projeto; permissões independentes para ver, editar e excluir terceiros | Tarefas; Cadastros e configurações → Permissões |
| Financeiro | Horas das tarefas passam a compor a apuração, sem duplicar tarefas já cobertas por apontamentos vinculados | Projetos → Detalhes → Resultado; Portfólios → selecionar projeto |
| Custo histórico | Captura da tarifa vigente; editar duração mantém tarifa capturada; mudança de analista/data/hora extra busca nova vigência | Automático no cadastro/edição de tarefa |
| Pendências de custo | Atividade sem tarifa é registrada, mas o resultado é marcado parcial. Cadastrar tarifa precifica registros pendentes sem alterar os já precificados | Aviso no resultado e no portfólio |
| Relatório | Receita, impostos, comissão, custos, lucro/margem, horas, planejado × realizado, custos/tempo por analista e tipo, despesas e gráficos | Resultado do projeto |
| Portfólio | Cliente real, gerentes de contas e projetos separados, cancelados, aviso de custo pendente e período por início | Portfólios |
| Permissões | Editor por operação e por campo do cadastro do projeto; campos financeiros continuam sujeitos ao acesso financeiro; administradores têm acesso integral na empresa | Cadastros e configurações → Permissões por usuário |
| Auditoria | Novas mutações auditadas; eventos de login/logout e perfil; detalhes de alterações para administradores; paginação do histórico | Histórico |
| Retenção | Configuração por empresa de 1 a 120 meses, padrão 12; rotina diária às 03:00 no fuso da aplicação (UTC atualmente) | Configurações; `audit:prune` |
| AAD / Entra ID | Configuração com segredo criptografado, lista paginada de usuários, importação explícita como analista; não altera contas existentes | Cadastros e configurações → Microsoft |
| Tutorial e idiomas | Tutorial de projetos ajustado para dados reais; novos rótulos traduzidos em inglês/espanhol | `?` e seletor de idiomas |

### Preservação dos dados existentes

- Migrações adicionam campos e tabelas; não recriam usuários, não reexecutam o seed no banco de uso e não apagam projetos.
- Clientes e parâmetros são arquivados (inativados), mantendo referências históricas. O formulário permite reativação.
- Tarefas antigas sem data/duração permanecem disponíveis, mas não recebem horas inventadas.
- Horas ainda sem tarifa aparecem como pendência; não são apresentadas como resultado financeiro completo.
- A exclusão de tarefa afeta sua contribuição direta no relatório. Apontamentos históricos independentes (`work_logs`) continuam tendo sua própria origem e ciclo de vida.
- A reabertura preserva o comportamento anterior de vínculos: revise/reassocie os analistas em Detalhes caso seus acessos tenham sido revogados ao finalizar.

### Dependências externas e limites da validação

1. **Microsoft real:** falta preencher tenant ID, application/client ID e segredo em Configurações e conceder `User.Read.All` do tipo Application com consentimento administrativo. Login Microsoft existente e consulta de diretório são configurações distintas. Nenhuma credencial foi inventada; nenhum tenant externo foi alterado.
2. **Diretório:** fluxo validado com respostas simuladas da Microsoft; autenticação/listagem reais precisam dessas credenciais. Importações não enviam mensagens nem elevam a conta a administrador.
3. **Agendador:** `iniciar.sh` passa a iniciar `scripts/scheduler.sh`. O loop usa trava para evitar duas instâncias e chama `schedule:run` a cada minuto. Em produção com supervisor/cron, execute apenas uma instância equivalente. Não depende do navegador permanecer aberto.
4. **Auditoria:** há registro de operações de negócio, permissões e autenticação; não se afirma captura de todos os cliques/leituras da interface. Dados sensíveis de autenticação não entram nos eventos.
5. **Permissões por campo:** os controles adicionados cobrem os campos do cadastro de projeto. Telas financeiras têm autorização própria; regras de outros módulos seguem as permissões existentes.
6. **Testes:** backend em banco isolado; navegador com API simulada. Migrações aplicadas ao banco local. Não foram criados clientes/projetos de teste no banco usado pelo usuário.

### Referências técnicas Microsoft

- [Acesso ao Microsoft Graph sem usuário](https://learn.microsoft.com/en-us/graph/auth-v2-service): permissão de aplicação e consentimento administrativo.
- [Fluxo client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow): obtenção de token com tenant, aplicação e segredo.

### Comandos de verificação

```bash
./php vendor/bin/phpunit
npm run test:management
npm run test:tasks
npm run test:languages
npm run test:tutorial
./php artisan audit:prune --dry-run
./php artisan schedule:list
```

### Resultado da verificação desta entrega

- PHPUnit: **79 testes, 510 asserções**, todos aprovados.
- Navegador: criação/edição/finalização/reabertura de projeto, custo/hora, cadastro/arquivamento de cliente e CRUD de tarefas aprovados com API simulada.
- Idiomas: **142 verificações**, sem falhas ou textos pendentes detectados nas telas cobertas.
- Tutorial: **454 verificações**, sem erros.
- Build de produção concluído; migrações locais aplicadas; validação de diferenças sem erros de whitespace.
- Agendador local iniciado; simulação da retenção encontrou **0 registros elegíveis**, sem apagar registros existentes.
