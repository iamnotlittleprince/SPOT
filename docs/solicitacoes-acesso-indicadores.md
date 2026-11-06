# Solicitações de acesso aos relatórios e indicadores

O botão **Solicitações de acesso**, em **Portfólios**, permite ao analista escolher um projeto, escolher um destinatário quando houver mais de um gerente elegível e enviar uma mensagem. Com um único gerente, o destinatário é selecionado automaticamente. A janela de envio termina no formulário e na confirmação, sem histórico ou controles de concessão. Pedidos recebidos são abertos pela caixa de entrada em uma visualização própria, apenas da solicitação selecionada. O próprio remetente recebe uma cópia da solicitação enviada; concessão, recusa e revogação geram novas mensagens internas.

## Alçada de concessão

- Gerente: somente projetos em que é o `project_manager_id` ou `account_manager_id` atual, ou integrante ativo com papel `manager` na equipe. O papel de gerente na equipe também é reconhecido quando o perfil global é outro.
- Gerente-administrador: somente projetos em que o solicitante tem participação ativa, sem revogação de acesso à equipe.
- O perfil `administrador`, já utilizado pelo cadastro, segue a mesma restrição do perfil `gestor-administrador`: o solicitante deve participar ativamente do projeto. Se houver também o perfil de gerente, prevalece essa restrição de participação.
- Solicitante, responsável e projeto precisam pertencer à mesma empresa atual. Contas inativas/suspensas, autoconcessão, projetos excluídos/finalizados e respostas de terceiros são bloqueados no servidor.
- Somente o destinatário pode responder e revogar sua concessão. Mesmo se perder a alçada, ele ainda pode revogar uma concessão própria.

O catálogo de envio só é retornado para analistas elegíveis e contém nome/ID dos projetos abertos em que atuam com papel de analista na empresa atual. Para usuários com perfil de analista, só entram projetos com participação ativa e sem revogação; essa condição também é exigida no envio, na aprovação e durante o uso da concessão. Projetos sem destinatário elegível também aparecem, com orientação para conferir os responsáveis e a participação, sem permitir envio sem destinatário. Este fluxo é exclusivo de usuários ativos com perfil de analista, sem os perfis administrador, gerente administrador ou gerente na mesma empresa. A participação com papel de analista no projeto é obrigatória, inclusive por requisição direta à API. Acumular um perfil de analista não habilita administradores/gerentes a pedir acesso neste fluxo. Pedidos de gerentes para outros projetos não são tratados por este fluxo de analistas. O pedido não concede acesso aos dados. Pedidos pendentes repetidos para o mesmo projeto/destinatário são bloqueados por transação.

## Tipos

| Tipo | Comportamento |
| --- | --- |
| Somente visualizar | Consulta os indicadores e o detalhamento financeiro do projeto autorizado. |
| Editar visual e indicadores dos gráficos | Também permite escolher indicadores, informações exibidas, cores, tipo, ordenação e distribuição dos gráficos. Não altera os dados do projeto. |
| Editar dados do projeto e gráficos | Além da personalização, permite alterar progresso, valor contratado, data inicial e final; os gráficos são recalculados. Alterações são auditadas. |

O editor de dados é específico dos indicadores. A concessão não atribui permissões gerais sobre equipe, tarefas, administração, impostos, despesas ou tarifas. A personalização visual utiliza o armazenamento local já empregado pelo painel: é salva neste navegador, sem criar um layout compartilhado entre usuários/dispositivos.

A autorização temporária filtra os projetos **antes** de calcular os gráficos e totais. Não libera o portfólio financeiro global nem outros projetos. As permissões gerais que o usuário já possuía continuam seguindo os mecanismos existentes.

## Validade

Opções: reunião Teams, 24h, 48h, 72h, uma semana, um mês de calendário ou até o encerramento do projeto/revogação. Prazos fixos contam a partir da aprovação. O encerramento do projeto revoga permanentemente pedidos pendentes e aprovados; reabrir não restaura esses convites.

A validade e a alçada atual do concedente são verificadas em cada requisição protegida. A revogação, a troca de empresa, a inativação, a perda de perfil/alçada ou de participação exigida impedem novas consultas. A atualização da tela de indicadores segue o intervalo existente de 30 segundos; dados já apresentados não podem ser retirados da memória de quem os consultou.

O painel distingue a decisão registrada (aprovada, recusada etc.) do estado atual (ativo, aguardando início, prazo encerrado, indisponível).

### Reuniões Teams

Utiliza eventos Teams do calendário Microsoft do responsável, consultados pelo Microsoft Graph. A seleção considera de hoje até um mês e os primeiros 100 eventos retornados. Somente eventos online com provedor `teamsForBusiness`, não cancelados, são aceitos. Datas enviadas pelo navegador não definem a validade.

Na aprovação e em cada requisição de acesso por reunião, o servidor consulta o evento na Microsoft. A janela é **início agendado inclusive até fim agendado exclusivo**, conforme escolha confirmada pelo usuário. Reagendamentos e cancelamentos são considerados. Se não puder confirmar a reunião, o acesso de reunião fica indisponível, sem conceder permissões por suposição. Não detecta a abertura/encerramento reais da chamada nem a presença na reunião.

Referência: [eventos online e Teams no Microsoft Graph](https://learn.microsoft.com/en-us/graph/outlook-calendar-online-meetings).

## Operação e verificação

Aplicar `./php artisan migrate` para criar a tabela de pedidos. A migration foi aplicada no ambiente local desta execução; não foram criados pedidos nem enviadas mensagens para contas reais.

Rotas autenticadas e protegidas por sessão/CSRF em `/api/v1/project-access`: listagem, criação, reuniões, decisão, revogação e edição dos dados autorizados. Concessões e decisões têm auditoria, e a criação usa limitação de frequência.

Testes: `./php artisan test tests/Feature/ProjectAccessTest.php`; `npm run test:project-access`. Os testes PHP verificam autorização e dados persistidos; os testes de navegador usam respostas simuladas. A conexão real com Teams e o consentimento Microsoft devem ser validados com o titular em seu ambiente.
