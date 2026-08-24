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

## Estrutura principal

- `app/`, `config/`, `database/` e `routes/`: aplicação Laravel.
- `resources/js/`: componentes React.
- `resources/css/`: estilos da interface.
- `resources/views/app.blade.php`: página base servida pelo Laravel.
- `public/`: imagens e arquivos públicos.
- `.runtime/`: PHP/FrankenPHP, Composer e Node.js portáteis.

O banco local usa SQLite em `database/database.sqlite`.

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
