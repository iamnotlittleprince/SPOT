# Spot V2

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

## API de estoque V2

Execute as migrations e dados iniciais com `./php artisan migrate --seed`. As rotas
protegidas exigem o header `Authorization: Bearer <access_token>`.

| Método | Rota | Finalidade |
| --- | --- | --- |
| POST | `/api/auth/register` | Cria usuário e devolve JWT |
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
