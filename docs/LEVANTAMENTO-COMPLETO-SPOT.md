# Levantamento funcional e técnico do Spot

Data: 26/10/2026. Base: código e documentação presentes no workspace, incluindo alterações locais ainda não commitadas.

## 1. O que é o Spot

O Spot é uma aplicação web de gestão de projetos com controle de execução, equipes, horas, custos, despesas, indicadores e acesso. Seu foco mais evidente é a operação de serviços: relacionar o projeto contratado ao trabalho dos analistas e ao resultado financeiro gerencial.

A estrutura combina uma interface de trabalho diário com recursos administrativos. O repositório também contém um módulo de estoque, acessível em `/estoque`, com produtos, categorias e movimentações.

A avaliação abaixo descreve a implementação encontrada. Não é certificação de segurança, comparação de mercado nem comprovação de funcionamento em produção. Não foram acessadas credenciais, enviados e-mails ou feitas chamadas às contas externas para este levantamento. Na revisão funcional foram executados 122 testes PHP com 788 asserções, build e testes de navegador; integrações externas usaram respostas simuladas. O documento foi atualizado para refletir as notificações e a atualização automática agora implementadas.

## 2. Como o sistema funciona

1. Um administrador provisiona a conta, atribui perfil e gera um link de primeiro acesso. Não há cadastro público.
2. O usuário ativa a conta e define a senha. Contas autorizadas também podem usar Google ou Microsoft.
3. A administração mantém clientes, parâmetros, usuários, permissões e configurações da empresa.
4. O gestor cadastra o projeto, cliente, responsáveis, proposta, datas, valor contratado e estimativas.
5. A equipe é vinculada ao projeto. Tarifas por analista e período definem custos normais, custos extras e venda de horas extras.
6. Os usuários registram tarefas e horas, acompanham prazos e submetem despesas conforme suas permissões.
7. Os cálculos consolidam receita, impostos, comissão, mão de obra e despesas aprovadas, comparando planejamento e realização.
8. O portfólio apresenta a carteira e permite criar combinações de indicadores e gráficos.
9. Operações relevantes geram histórico. Finalização, reabertura e exclusão de projeto exigem autorização e justificativa.

## 3. Módulos e funcionalidades

### 3.1 Home

- Visão resumida de projetos, tarefas e equipes.
- Progresso e situação dos projetos.
- Tarefas com responsável, participantes, prioridade, prazo, descrição e duração.
- Alteração de conclusão e prioridade, sujeita às regras de acesso.
- Acesso aos detalhes e ao histórico da tarefa.
- Visibilidade filtrada pelo usuário e seus vínculos.

A Home é um resumo: o backend limita a consulta a 20 projetos e 30 tarefas. Não deve ser usada como inventário completo da carteira.

### 3.2 Projetos

- Cadastro, listagem, busca, edição e exclusão lógica governada.
- Número da proposta único dentro da empresa.
- Cliente, gerente de contas e gerente de projeto.
- Status e situação configuráveis, progresso e datas.
- Datas de proposta, início, término e faturamento.
- Valor contratado em BRL, comissão e sua base de cálculo.
- Estimativas de mão de obra, custos adicionais e duração.
- Campos de participação, como projeto completo, subprojeto, frente de trabalho, pacote de serviço, consultoria e suporte, previstos na validação da API.
- Referências de projeto externo e descrição do escopo na API.
- Configuração de equipe, tarifas, impostos e despesas.
- Resultado financeiro e histórico do projeto.
- Finalização e reabertura com justificativa; projeto finalizado recebe destaque visual e bloqueios operacionais nos fluxos principais.

A finalização desativa os vínculos ativos de membros e remove as permissões desses vínculos. A reabertura não os reativa automaticamente; a equipe precisa ser revisada.

### 3.3 Tarefas e apontamentos

- Visualização em quadro, lista e calendário, com busca e filtros.
- Projeto, responsável, participantes, criador, título e descrição.
- Status, prioridade e prazo.
- Data trabalhada, duração em minutos, tipo de atividade e indicação de hora extra.
- Criação e alteração persistidas pela API principal.
- Regras distintas para operações próprias e operações sobre registros de terceiros.
- Convidados não criam tarefas; perfis ativos autorizados operam nos projetos acessíveis.
- Histórico de mudanças e bloqueio em projetos finalizados.
- API própria para apontamentos de horas, além das tarefas da interface.

O cálculo financeiro inclui tarefas com data de trabalho e evita somá-las novamente quando já existem apontamentos vinculados à mesma tarefa. Tarefas sem tarifa ficam identificadas como pendência de precificação; apontamentos exigem tarifa vigente.

### 3.4 Equipes

- Equipe por projeto, com nome próprio ou derivado do projeto.
- Membros e funções vinculados ao projeto.
- Inclusão, alteração e remoção de membros autorizadas.
- Vínculos ativos e revogação de acesso.
- Tarifas por projeto e analista, com datas de vigência.
- Bloqueio de sobreposição dos períodos de tarifa.

### 3.5 Financeiro gerencial

O resultado realizado segue a regra encontrada no código:

```text
Receita gerencial = valor contratado + faturamento de horas extras
Custo operacional = mão de obra + despesas aprovadas
Lucro gerencial = receita − impostos − comissão − custo operacional
Margem = lucro / receita × 100
```

- Impostos fixos ou percentuais, com composição por tipo.
- Base tributária no contrato ou na receita total.
- Comissão sobre receita bruta ou líquida de impostos.
- Custos normais e extras com tarifas próprias.
- Registro de despesas, edição, exclusão e aprovação/rejeição.
- Alterações de despesas sujeitas a nova aprovação.
- Fotografia das tarifas no registro da atividade, preservando o histórico frente a reajustes futuros.
- Planejado versus realizado de receita, custos, lucro, margem e duração.
- Detalhamento por analista e tipo de atividade.
- Indicador de resultado parcial quando existem tarefas sem tarifa.
- Aritmética decimal no cálculo financeiro principal, com arredondamento em centavos.
- Campos financeiros protegidos por autorização específica.

A receita chamada de realizada usa o contrato como base; não representa, por si só, dinheiro recebido. Não foi identificado neste escopo um módulo completo de contas a pagar/receber, conciliação bancária, emissão fiscal ou escrituração contábil. O financeiro deve ser apresentado como apuração gerencial do projeto.

### 3.6 Portfólios e indicadores

- Carteira de projetos com totais, situação, clientes e gestores.
- Indicadores de projetos concluídos, em andamento, atrasados, congelados e cancelados.
- Receita, custos, lucro, horas, progresso e atividades sem tarifa.
- Filtros por período e projeto, além de agrupamentos e personalização da apresentação.
- Cruzamentos por projeto, cliente, gestores e situação.
- Agregação por soma, média, mínimo e máximo.
- Barras, colunas, linhas, área, pizza, rosca, radar, mapa de árvore, funil, cartões e tabela; indicador circular quando aplicável.
- Configuração de ordem, quantidade de categorias, cores, valores, largura e posição.
- Exportação CSV por levantamento.
- Painel financeiro individual de projeto.
- Preferências gravadas no navegador.
- Atualização automática do portfólio a cada 30 segundos quando visível, após alterações e entre abas, preservando os filtros aplicados.

O período da API de análise seleciona projetos pela data de início ou criação. Os valores financeiros são os totais desses projetos, não necessariamente os lançamentos ocorridos dentro do intervalo. As agregações gráficas usam conversões numéricas em Python/JavaScript; a precisão decimal citada anteriormente se refere ao cálculo financeiro principal.

### 3.7 Clientes e parâmetros

- Cadastro, consulta e alteração de clientes.
- Arquivamento e reativação para preservar referências históricas.
- Manutenção de status, situações, tipos de atividade, tipos de despesa e impostos.
- Opções ativas fornecidas aos formulários.
- Regras por empresa e permissões administrativas.

### 3.8 Documentos

- Envio, listagem, download e exclusão de arquivos.
- Associação opcional a um projeto.
- Limite de 10 MB por arquivo.
- Aceita formatos de escritório, PDF, texto, CSV, imagens e ZIP previstos na validação.
- Armazenamento local privado.
- Auditoria de envio, download e exclusão, sem registrar o conteúdo do arquivo.

Na implementação atual, listagem, download e exclusão são vinculados ao proprietário do arquivo. Associar um documento a um projeto não o transforma automaticamente em biblioteca compartilhada de toda a equipe. Não foram identificados controle de versões, edição colaborativa ou antivírus de uploads neste fluxo.

### 3.9 Agenda

- Eventos próprios do Spot.
- Consulta de eventos do Google Calendar e do calendário Microsoft.
- Criação de eventos no Spot, Google e Outlook.
- Criação de reunião online do Teams quando a conta externa permite.
- Título, descrição, início, fim, local e link da reunião quando fornecido.
- Renovação dos tokens externos e mensagens de erro por provedor.

A integração exige OAuth configurado, autorização da conta e permissões externas. O controlador analisado oferece consulta e criação; não demonstra CRUD completo, webhooks ou sincronização contínua em segundo plano. A consulta externa agora percorre páginas, com limite de 20 e aviso para reduzir o período; next links Microsoft ficam limitados ao host Graph. Fusos são normalizados para UTC e a conexão PostgreSQL também fixa UTC.

### 3.10 Caixa de entrada e notificações

- Listagem de itens por usuário e contagem de não lidos.
- Marcar item ou conjunto como lido.
- Arquivar, limpar e restaurar itens elegíveis.
- Consulta de itens arquivados dentro da janela de 30 dias implementada.
- Preferências por evento e canal: aplicativo e e-mail.
- Preferências de resumo diário/semanal e horário de silêncio.
- Preferências de alertas de segurança.

O catálogo funcional possui produção de eventos ligada às operações auditadas e lembretes por agendador. A outbox persistente aplica canais, silêncio por fuso e resumos diário/semanal; o worker revalida permissões e estado antes do envio. Opções de comentários/revisões sem fluxo correspondente foram retiradas do catálogo. SMTP e ativação do envio externo de atividades continuam sendo requisitos de operação; avisos internos são independentes. A caixa de entrada não equivale a um chat: não há endpoint de composição de mensagens nas rotas examinadas.

### 3.11 Perfil e experiência de uso

- Atualização de perfil, avatar e fuso horário.
- Temas claro e escuro.
- Português, inglês e espanhol.
- Datas e valores formatados pelo idioma; moeda financeira BRL.
- Pesquisa na interface e navegação por áreas.
- Atalhos recentes no navegador.
- Tutorial guiado adaptado às permissões, com até 35 etapas.
- Pausa, retomada, seleção de assuntos e progresso por conta no navegador.
- Adaptações de layout para telas menores.

O progresso do tutorial, a escolha de idioma e a personalização de painéis dependem do armazenamento do navegador. Não são preferências integralmente sincronizadas entre dispositivos. O tutorial não usa API de inteligência artificial.

### 3.12 Administração, identidade e segurança

- Perfis administrador, gestor-administrador, gestor, analista e convidado.
- Permissões por operação, exceções por usuário e controles por campo do cadastro de projeto.
- Estrutura de pessoas, empresas, perfis e vínculos.
- Usuários provisionados administrativamente e ativação por link de uso único.
- Proteção do último administrador ativo contra desativação/rebaixamento.
- Login por senha e autenticação Google/Microsoft para contas autorizadas.
- Recuperação e alteração de senha.
- Configuração e validação de 2FA por autenticador no login por senha, com QR Code e códigos de recuperação.
- Consulta e encerramento de sessões.
- Desconexão de provedores externos.
- Tokens OAuth e segredos de 2FA criptografados no modelo de usuário.
- Sessão protegida por Sanctum e CSRF na API principal.
- Limitação de frequência em rotas da API, login e operações sensíveis.
- Tokens de convite armazenados como hash SHA-256; validade, revogação e aceite único.
- Convites com escopo de projeto e exigência opcional de criação de senha.

O isolamento por empresa é parte importante dos módulos centrais, mas não deve ser anunciado como cobertura uniforme de toda a aplicação: o CRUD de estoque examinado não aplica o mesmo filtro de empresa ou autorização granular. A presença de controles de segurança não substitui auditoria de segurança nem comprova conformidade regulatória. A avaliação de 2FA aqui não afirma cobertura idêntica entre login por senha, OAuth e compatibilidade JWT.

### 3.13 Diretório Microsoft / Entra ID

- Configuração de tenant, aplicação e segredo.
- Segredo protegido e omitido das respostas.
- Consulta paginada ao diretório pelo Microsoft Graph.
- Importação explícita de usuários como analistas.
- Sem concessão automática de perfil administrativo pela origem externa.

Requer credenciais e consentimento administrativo para a permissão de aplicação `User.Read.All`. A documentação registra validação com respostas simuladas; o funcionamento no tenant real precisa de configuração e validação próprias.

### 3.14 Histórico e auditoria

- Histórico geral e por projeto/tarefa.
- Ação, autor, data, item e contexto da requisição.
- Valores anteriores e posteriores restritos à permissão administrativa prevista.
- Nomes preservados nos eventos novos para consulta após exclusão ou renomeação.
- Registro de alterações de projetos, tarefas, participantes, equipes, tarifas, impostos, despesas, apontamentos e documentos.
- Operações de negócio importantes gravadas na mesma transação do evento de auditoria.
- Retenção por empresa, de 1 a 120 meses, padrão 12.
- Comando de expurgo e execução agendada.

Não há reconstrução de eventos antigos que não foram registrados. O histórico não captura cada clique e não foi demonstrado como armazenamento imutável ou inviolável.

### 3.15 Estoque

- Interface específica em `/estoque`.
- Categorias e produtos com nome, SKU, preço e saldo.
- Entradas e saídas com histórico e usuário responsável.
- Indicadores de estoque e movimentações recentes.
- Atualização de saldo por movimentação, sem edição direta pelo endpoint de atualização do produto.
- Transação com bloqueio da linha do produto e rejeição de saída com saldo insuficiente.
- API por sessão e rotas JWT de compatibilidade.

O estoque é um módulo adicional. Não foi encontrada integração que transforme automaticamente movimentação de produto em custo de projeto. Seu modelo de autorização/isolamento precisa ser revisado antes de apresentá-lo como módulo multiempresa equivalente ao núcleo de projetos.

### 3.16 E-mails e rotinas de fundo

- Convites e reenvios de primeiro acesso processados pela fila `emails`.
- Worker com até três tentativas e intervalos de repetição.
- Links criptografados no payload da fila.
- Envio após confirmação da transação e verificação de validade do link.
- Comandos para acompanhar fila, consultar falhas e reprocessar tarefas.
- Agendador para rotinas periódicas, incluindo retenção de auditoria.

Exige SMTP, worker e agendador operacionais. A confirmação de criação do convite não comprova recebimento do e-mail. O cadastro administrativo retorna o link inicial; o envio de primeiro acesso documentado ocorre no pedido de reenvio. Não há alerta externo automático de falha de fila documentado.

## 4. Tecnologias

| Camada | Tecnologia encontrada | Papel |
|---|---|---|
| Backend | PHP ^8.3, Laravel ^13.8 | Regras, API, autenticação, validação, filas e agendamento |
| Persistência | PostgreSQL; imagem 17-alpine no Compose | Dados relacionais, transações e concorrência |
| ORM | Eloquent | Modelos e relacionamentos |
| Frontend | React 18.3, JavaScript/JSX | Interface SPA |
| Build | Vite 8, plugin React e integração Laravel | Desenvolvimento e empacotamento |
| Node | ^22.12.0 no manifesto; Node 22 na imagem | Ferramentas do frontend |
| Interface | CSS próprio, Lucide React, catálogos Iconify | Estilos e ícones |
| Análise | Python 3 e Pandas >=2.2,<3 | Consolidação dos indicadores |
| Cálculo financeiro | Brick Math / BigDecimal | Aritmética decimal |
| Sessão/API | Laravel Sanctum | Autenticação da interface |
| Compatibilidade | tymon/jwt-auth | Consumidores JWT legados |
| OAuth | Laravel Socialite e provider Microsoft | Google e Microsoft |
| 2FA | pragmarx/google2fa e bacon/bacon-qr-code | TOTP e QR Code |
| E-mail | Mail do Laravel, SMTP e jobs | Envio assíncrono |
| Testes | PHPUnit 12.5 e Playwright | Backend e fluxos de navegador |
| Servidor | FrankenPHP e Caddy | Execução PHP e HTTP/HTTPS |
| Empacotamento | Docker e Docker Compose | Serviços web, worker, scheduler e banco |
| Dependências | Composer e npm | Bibliotecas e ferramentas |

Versões acima vêm dos manifestos e arquivos locais; intervalos de versão não afirmam o patch exato instalado nem a versão mais recente do mercado.

## 5. Arquitetura

```text
Navegador / React
    → API principal /api/v1, sessão e CSRF
        → Controllers e validações
            → Regras em app/Domain + modelos Eloquent
                → PostgreSQL
                → Arquivos privados
                → Jobs de e-mail → worker → SMTP
                → APIs Google / Microsoft
                → Processo Python/Pandas → indicadores JSON

Scheduler → tarefas periódicas de manutenção
API legada sem /v1 → autenticação JWT de compatibilidade
```

É uma aplicação central Laravel com frontend React, e não uma arquitetura de microserviços. O Python é chamado como processo pelo backend: recebe JSON pela entrada padrão e devolve JSON, sem acessar diretamente o banco. Isso mantém o escopo da consulta sob responsabilidade do Laravel.

A interface ativa nasce em `resources/js/app.jsx`; `resources/js/App.jsx` organiza o shell e muitas telas. Componentes separados tratam administração, tarefas, auditoria, portfólios e estoque. O diretório `src/` não é a entrada ativa indicada pelo Vite.

Em desenvolvimento, `iniciar.sh` coordena servidor, Vite, worker e scheduler. O Compose de produção separa os processos em serviços e usa volumes persistentes. Essa estrutura facilita implantação, mas não comprova alta disponibilidade, restauração de backup, monitoramento externo ou capacidade sob carga.

## 6. Pontos fortes e diferenciais sustentados pelo código

1. **Execução ligada ao resultado gerencial.** Horas, tarifas, despesas e contrato alimentam o mesmo resultado do projeto.
2. **Preservação do custo histórico.** Reajustar uma tarifa não recalcula automaticamente atividades já precificadas.
3. **Tratamento explícito de dados incompletos.** Atividades sem tarifa deixam o resultado parcial, evitando falsa precisão.
4. **Governança do ciclo do projeto.** Finalizar, reabrir e excluir envolvem autorização, justificativa e rastreabilidade.
5. **Permissões com mais de uma dimensão.** Perfis, exceções por usuário, operações próprias/de terceiros e campos de projeto.
6. **Visão operacional e visão de gestão.** O usuário trabalha nas tarefas; a gestão acompanha carteira, custos e margens.
7. **Portfólio configurável.** Indicadores, agrupamentos, agregações e várias representações com exportação CSV.
8. **Integração com a rotina corporativa.** Login, calendários, reuniões e diretório Google/Microsoft nas áreas implementadas.
9. **Facilidade de adoção.** Tutorial por permissão, três idiomas, temas e diferentes visões de tarefas.
10. **Integridade transacional.** Bloqueios e transações em operações como estoque e fluxos governados reduzem inconsistências concorrentes.
11. **Autonomia de hospedagem.** Código e configuração de implantação permitem operar em infraestrutura própria.
12. **Base de evolução organizada.** Migrações, ações de domínio, documentação e testes de backend/navegador.

Esses são diferenciais do desenho do Spot. Não foi feita pesquisa concorrencial; não há base para afirmar exclusividade, superioridade de desempenho ou menor custo em relação a produtos específicos.

## 7. Limites e oportunidades de evolução

- Homologar a operação das notificações implementadas: SMTP, consentimentos e supervisão de worker/scheduler; eventos, canais, resumos e silêncio têm cobertura automatizada.
- Revisar isolamento e autorização do estoque, assim como sua eventual ligação ao custo dos projetos.
- Validar integrações reais com Google/Microsoft, incluindo paginação, renovação de consentimentos e cenários de falha.
- Tornar explícita a diferença entre receita gerencial contratada e recebimentos financeiros.
- Evoluir documentos para compartilhamento por equipe e versionamento, se isso fizer parte da proposta do produto.
- Sincronizar preferências de painéis/tutorial entre dispositivos quando necessário.
- Melhorar manutenção do frontend: `App.jsx` concentra muitas responsabilidades e a navegação é controlada por estado próprio.
- Medir consultas e desempenho do portfólio com carteiras grandes: há acessos por projeto e um processo Python por requisição analítica.
- Definir monitoramento de filas, logs, disponibilidade e restauração de backups em produção.
- Ampliar validação integrada: scripts de navegador usam frequentemente APIs simuladas, úteis para a interface, mas insuficientes para comprovar toda a integração real.

## 8. O que pode ser afirmado em uma apresentação

> O Spot centraliza projetos, equipes e atividades e transforma os registros de execução em indicadores de custo, receita e margem. A plataforma combina planejamento e acompanhamento financeiro gerencial com permissões por perfil, histórico de alterações, painéis configuráveis e integração com calendários Google e Microsoft.

Evitar apresentar como pronto: chat completo; integração de dados com os 24 aplicativos do menu; ERP contábil/fiscal; inteligência artificial; sincronização contínua com todos os provedores; notificações fora do catálogo implementado; isolamento multiempresa uniforme em todos os módulos; alta disponibilidade ou conformidade certificada.

Os 24 aplicativos do menu são atalhos que abrem páginas externas. Integrações efetivas identificadas incluem autenticação Google/Microsoft, calendários, criação de reuniões Teams e diretório Microsoft.

## 9. Evidências e pontos de entrada

- Visão e operação: `README.md`, `docs/MAPA-DO-CODIGO.md`, `docs/memorial/levantamento.md`.
- Tecnologias: `composer.json`, `package.json`, `requirements.txt`, `vite.config.js`.
- API: `routes/api.php`, `routes/web.php`.
- Interface: `resources/js/App.jsx`, `Management.jsx`, `TasksPage.jsx`, `TaskDetails.jsx`.
- Portfólio: `PortfolioDashboard.jsx`, `PortfolioChart.jsx`, `portfolioReports.js`, `scripts/project_analytics.py`.
- Financeiro: `app/Domain/Finance/ProjectFinancialSummary.php`, `app/Domain/Tasks/TaskCosting.php`.
- Governança: `app/Domain/Projects/Actions/`.
- Identidade e autorização: `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, controllers de autenticação e segurança.
- Agenda, documentos e notificações: controllers correspondentes em `app/Http/Controllers/Api/`.
- Estoque: `app/Domain/Inventory/Actions/RegisterStockMovement.php` e controllers de produtos/categorias/movimentações.
- Auditoria: `app/Domain/Audit/AuditRecorder.php`, `HistoryController.php`, `routes/console.php`.
- Operação: `Dockerfile`, `docker-compose.yml`, `docker/Caddyfile`, `iniciar.sh`, `scripts/scheduler.sh`.
- Verificação existente: `tests/Feature/`, `tests/Unit/` e scripts `scripts/test-*.mjs`.


Revisão de implementação: consulte `docs/notificacoes-e-atualizacao-automatica.md` para eventos, lembretes, resumos, ativação de e-mail e limites de operação. IA foi retirada da lista de implementação do material do TCC.
