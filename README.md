# Spot

Aplicação de gestão de projetos construída com Laravel 13, React 18 e Vite.

A V2 acrescenta a API REST de controle de estoque documentada na apostila: JWT,
categorias, produtos, movimentações atômicas e dashboard.

## Executar

O projeto já inclui runtimes locais de PHP e Node.js, além das dependências
instaladas. No Linux, basta executar:

```bash
./iniciar.sh
```

Depois, acesse [http://localhost:8000](http://localhost:8000).

O script inicia o Laravel pelo FrankenPHP na porta `8000` e o Vite na porta
`5173`. Encerre ambos com `Ctrl+C`.

## Comandos locais

Não é necessário instalar PHP ou Composer globalmente:

```bash
./php artisan migrate
./php artisan route:list
./composer install
./composer test
```

Para os comandos JavaScript, carregue o Node local antes:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm install
npm run build
```

O levantamento gráfico de projetos é consolidado por Python e Pandas. Instale a
dependência antes de abrir a página **Portfólios**:

```bash
python3 -m pip install --user -r requirements.txt
```

Em produção, `PYTHON_BINARY` pode apontar para o executável de um ambiente virtual.

## Estrutura principal

- `app/`, `config/`, `database/` e `routes/`: aplicação Laravel.
- `resources/js/`: componentes React.
- `resources/css/`: estilos da interface.
- `resources/views/app.blade.php`: página base servida pelo Laravel.
- `public/`: imagens e arquivos públicos.
- `.runtime/`: PHP/FrankenPHP, Composer e Node.js portáteis.

O Spot usa exclusivamente PostgreSQL. O banco padrão de desenvolvimento é
`spot`; a suíte automatizada usa o schema isolado `spot_testing`.

Antes da primeira execução, crie os bancos e aplique as migrações:

```bash
createdb spot
psql -d spot -c 'CREATE SCHEMA IF NOT EXISTS spot_testing'
./php artisan migrate
```

Configure host, porta, banco, usuário, senha e modo SSL pelas variáveis `DB_*`
do `.env`. Em produção, use credenciais próprias e conexão protegida por TLS.

## API principal da SPA

A interface web usa `/api/v1` com Laravel Sanctum, sessão e proteção CSRF. O token
de autenticação não é salvo no `localStorage`. Antes de requisições mutáveis, o
cliente inicializa o cookie CSRF em `/sanctum/csrf-cookie`.

Não existe cadastro público. Um administrador cria o usuário, define seu perfil e
gera um link de primeiro acesso de uso único. O usuário confirma nome/e-mail e cria
a senha. Google e Microsoft apenas autenticam contas ativas previamente autorizadas;
cada usuário pode vincular no máximo uma identidade de cada provedor.

Perfis administrativos: `administrador` e `gestor-administrador`. Ambos podem
administrar a segurança; o segundo também reúne as atribuições operacionais de
gestor. O último administrador ativo não pode ser desativado ou rebaixado.

As rotas antigas sem `/v1` continuam aceitando JWT temporariamente para não quebrar
consumidores existentes. Elas devem ser tratadas como uma camada de compatibilidade,
e não utilizadas em novas integrações.

Fluxos governados disponíveis na API principal:

| Método | Rota | Regra |
| --- | --- | --- |
| POST | `/api/v1/projects/{id}/finalize` | Exige `projects.finalize` e justificativa |
| POST | `/api/v1/projects/{id}/reopen` | Exige `projects.reopen` e justificativa |
| DELETE | `/api/v1/projects/{id}` | Exclusão lógica, permissão e justificativa |
| GET/POST | `/api/v1/projects/{id}/expenses` | Lista ou registra despesas do projeto |
| POST | `/api/v1/expenses/{id}/review` | Gestor aprova ou rejeita uma despesa pendente |
| GET/POST | `/api/v1/projects/{id}/work-logs` | Lista ou registra apontamentos de horas |
| POST | `/api/v1/projects/{id}/invitations` | Gera convite de convidado com validade e escopo |
| POST | `/api/v1/invitations/{token}/accept` | Aceita token uma única vez |
| POST | `/api/v1/invitations/{id}/revoke` | Revoga convite ainda não utilizado |
| GET | `/api/v1/projects/{id}/financial-result` | Resultado planejado e realizado do projeto |
| GET | `/api/v1/reports/portfolio` | Totais financeiros e projetos da carteira |
| GET | `/api/v1/reports/project-analytics` | Indicadores gráficos consolidados com Pandas |
| GET | `/api/v1/parameters` | Opções ativas para os formulários |
| POST | `/api/v1/admin/users` | Provisiona usuário e gera link de primeiro acesso |
| PATCH | `/api/v1/admin/users/{id}` | Altera perfil e estado da conta |
| POST | `/api/v1/first-access` | Solicita reenvio seguro do link de ativação |
| POST | `/api/v1/first-access/{token}` | Ativa conta e define senha |
| GET | `/api/v1/projects/{id}/configuration` | Equipe, tarifas e impostos do projeto |
| POST | `/api/v1/projects/{id}/members` | Atribui membro e função ao projeto |
| POST | `/api/v1/projects/{id}/rates` | Cria tarifa com vigência sem sobreposição |
| PUT | `/api/v1/projects/{id}/taxes` | Substitui a composição tributária |

Projetos finalizados rejeitam alterações e novas despesas. Finalização, reabertura,
exclusão e revisão de despesas produzem registros em `audit_logs`.

Apontamentos exigem que o analista esteja atribuído ao projeto e possua tarifa
vigente na data trabalhada. O lançamento salva uma fotografia das tarifas de custo
e venda, evitando que reajustes futuros alterem resultados históricos. Horas extras
usam tarifas de custo e faturamento distintas.

O token de convite é exibido somente na criação; o banco armazena apenas SHA-256.
Convites expiram em até sete dias, podem ser revogados e concedem permissões somente
no projeto indicado. Se o e-mail já possuir conta, o usuário precisa se autenticar
nessa conta antes do aceite.

Os relatórios financeiros usam aritmética decimal e exigem `financial.view`.
Receita realizada inclui horas extras faturáveis; custos consideram as tarifas
copiadas nos apontamentos; somente despesas aprovadas entram no resultado. Comissão
pode incidir sobre o bruto ou sobre a receita líquida de impostos.

O CRUD `/api/v1/projects` exige número da proposta, status, situação e valor
contratado, valida clientes e gestores dentro da Computécnica e audita criação e
alteração. Campos financeiros são removidos das respostas para usuários sem
`financial.view`, embora permaneçam preservados no banco.

Em produção, configure pelo menos:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://spot.seudominio.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Use HTTPS e um armazenamento compartilhado de sessão, como Redis, quando houver
mais de uma instância da aplicação.

## API de estoque V2 (compatibilidade JWT)

Execute as migrations e dados iniciais com `./php artisan migrate --seed`. As rotas
protegidas exigem o header `Authorization: Bearer <access_token>`.

| Método | Rota | Finalidade |
| --- | --- | --- |
| POST | `/api/auth/login` | Faz login e devolve JWT |
| POST | `/api/auth/logout` | Invalida o JWT |
| GET | `/api/auth/me` | Usuário autenticado |
| GET/POST/PUT/DELETE | `/api/categories` | Categorias (PUT/DELETE usam `/{id}`) |
| GET/POST/PUT/DELETE | `/api/products` | Produtos (PUT/DELETE usam `/{id}`) |
| GET/POST | `/api/movements` | Histórico e movimentação de estoque |
| GET | `/api/dashboard` | Indicadores e movimentações recentes |

A quantidade do produto não pode ser alterada pelo endpoint de atualização. Ela muda
apenas em `POST /api/movements`, que registra o histórico e ajusta o saldo na mesma
transação; saídas com saldo insuficiente recebem resposta `422`.

O cliente React da V2 é a aplicação principal, em [http://localhost:8000](http://localhost:8000).

## Fila de e-mails

Convites de projeto e reenvios de primeiro acesso são processados em segundo plano
na fila `emails`, usando `QUEUE_CONNECTION=database` e o banco configurado no `.env`.
O `./iniciar.sh` inicia também o worker e o encerra com os demais processos.
Para iniciar somente o worker:

```bash
./php artisan queue:work --queue=emails,default --sleep=3 --tries=3 --timeout=60
```

Cada envio tem até três tentativas, com intervalos de 60 e 300 segundos após falhas.
O SMTP tem timeout padrão de 30 segundos (`MAIL_TIMEOUT`), menor que o timeout do
job (60 segundos) e o prazo de recuperação da fila (90 segundos). Preserve essa
ordem ao ajustar os valores. Sem worker ativo, os e-mails ficam aguardando no banco.

Os links são criptografados no payload da fila com `APP_KEY`. Preserve essa chave
para processar tarefas pendentes. O worker verifica se o link ainda está válido
antes de enviar e ignora registros expirados, revogados ou utilizados. O envio é
agendado após a confirmação das transações. A confirmação na tela indica a criação
do convite, e não a entrega do e-mail. O cadastro administrativo continua retornando
seu link de ativação; o envio de primeiro acesso ocorre na solicitação de reenvio.

### Acompanhar falhas

```bash
./php artisan queue:status
./php artisan queue:failed
# Após corrigir a causa, use o UUID mostrado em queue:failed:
./php artisan queue:retry UUID
```

O status mostra tarefas aguardando, reservadas pelo worker e falhas definitivas.
Consulte também `storage/logs/laravel.log`. Uma tarefa removida com sucesso significa
que foi processada (ou ignorada por link inválido); não comprova entrega na caixa do
destinatário. Falhas após a aceitação pelo SMTP podem causar reenvio: não há garantia
de entrega exatamente uma vez. Os links continuam sendo de uso único.

Em produção, mantenha o worker sob um gerenciador como systemd ou Supervisor, com
reinício automático, mesmo usuário/permissões do aplicativo, diretório de trabalho
do Spot e o comando acima. Após publicar alterações no código, execute
`./php artisan queue:restart`; o gerenciador deve iniciar o novo processo. Acompanhe
periodicamente `queue:status` e `queue:failed`; não há alerta externo automático.

## Idiomas da interface

O seletor alterna entre português, inglês e espanhol e mantém a escolha neste
navegador. O catálogo está em `resources/js/translations.js`; o tratamento de texto,
atributos e idioma está em `resources/js/i18n.js`. Datas e valores usam o formato do
idioma escolhido, preservando a moeda BRL. Nomes e conteúdo fornecidos pelos usuários
não devem ser traduzidos: marque esses elementos com `translate="no"`.

Para verificar a interface em Chromium, com o Spot em execução:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npx playwright install chromium
npm run test:languages
```

O teste usa o build local e respostas simuladas de API, sem alterar cadastros ou
enviar mensagens. Percorre as páginas principais nos três idiomas, com e sem
permissão administrativa, incluindo avisos de restrição, abas do perfil, convites,
datas, pesquisa, preservação dos campos e restauração do português. Também verifica
textos e atributos atualizados após o carregamento. Use `SPOT_BASE_URL` para testar
em outra porta local.

## Tutorial guiado

O botão **?** no topo abre o guia do Spot. Na primeira visita da conta neste
navegador, um pop-up recomenda o tutorial e permite escolher **Agora não**. A cada
nova abertura/recarregamento do Spot, outro aviso lembra que o botão **?** está
disponível, inclusive depois da conclusão. Trocar de página não repete o aviso.

O guia oferece até 35 etapas conforme as permissões da conta: navegação, painel,
projetos, tarefas, mensagens, documentos, agenda, portfólio, histórico, perfil,
segurança, notificações, integrações e administração. Permite voltar, avançar,
escolher um assunto e pausar com Escape. Ao abrir o botão **?** novamente, retoma
diretamente a última etapa; voltar ao início exige confirmação. O conteúdo acompanha
português, inglês e espanhol, além dos temas claro e escuro e telas pequenas.

O progresso é salvo por conta em `localStorage`, com a chave `spot.tutorial.v1:`.
Ele não é sincronizado entre dispositivos; limpar os dados do navegador reinicia
a apresentação de primeiro acesso. Sem armazenamento disponível, o guia continua
funcionando e avisa que não conseguiu salvar o progresso.

O tutorial navega pelas telas, mas não preenche nem envia formulários e não cria,
altera ou exclui registros. Enquanto aberto, bloqueia a interação com os controles
reais para evitar cliques acidentais. Oriente o usuário a salvar alterações antes
de começar. As telas que ainda usam exemplos demonstrativos são identificadas nas
explicações. Nenhuma API de IA é utilizada.

Conteúdo e regras de seleção: `resources/js/tutorial/content.js`.
Interface e progresso: `resources/js/tutorial/SpotTutorial.jsx`.

Com o Spot em execução, verifique o fluxo no Chromium:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run test:tutorial
npm run test:languages
```

Os testes usam contas e respostas simuladas no navegador, sem alterar o banco.
Cobrem as etapas de analista e administrador, permissão revogada, primeiro acesso,
lembrete recorrente, pausa, retomada, conclusão, idiomas, navegação por teclado,
telas pequenas, isolamento por conta e falhas de armazenamento ou carregamento.

### Cadastro de tarefas

A tela **Tarefas → Nova tarefa** cadastra projeto, analista, data da tarefa, duração em horas e minutos, tipo, descrição e indicação de hora extra no banco pela API de sessão `/api/v1/tasks`. O cadastro atribui a tarefa ao usuário conectado; a permissão `work_logs.create_for_others` permite selecionar outro analista ativo do projeto. Quadro, lista e calendário exibem esses registros, com busca e filtros.

Todos os perfis ativos da empresa, exceto convidados, podem criar tarefas nos projetos a que têm acesso. O analista pode editar e excluir os próprios registros; gestores com `tasks.manage` e `tasks.delete` podem administrar os demais. A exclusão exige confirmação na interface. O acesso respeita a empresa e os projetos atribuídos ao usuário; projetos finalizados ficam somente para leitura. As alterações ficam no histórico de auditoria.

Validação: `./php vendor/bin/phpunit tests/Feature/TaskV1CrudTest.php` testa persistência e permissões no banco de testes. `npm run test:tasks` testa o fluxo da interface com API simulada, sem alterar dados reais.

### Adequação ao memorial descritivo

O levantamento por página e o estado das entregas estão em [docs/memorial/levantamento.md](docs/memorial/levantamento.md).

- **Projetos → Detalhes:** equipe, custo/hora, impostos, despesas e resultado planejado × realizado.
- **Cadastros e configurações** (menu superior): clientes, parâmetros, permissões por operação/campo, retenção de auditoria e diretório Microsoft.
- O custo das tarefas entra no relatório; atividades sem tarifa vigente deixam o resultado marcado como parcial.
- `iniciar.sh` também inicia o agendador. `./php artisan audit:prune --dry-run` mostra quantos registros venceram a retenção, sem apagá-los.
- A importação AAD exige credenciais da aplicação e consentimento Microsoft Graph `User.Read.All` do tipo Application. O segredo fica criptografado e não é retornado pela API.
