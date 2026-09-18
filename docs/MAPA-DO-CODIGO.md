# Mapa do código do Spot

Este arquivo indica onde alterar cada parte do sistema. Os comentários dentro do
código ficam reservados às decisões que não são evidentes pela implementação.

## Inicialização

- `iniciar.sh`: inicia servidor PHP, worker de e-mail, scheduler e Vite.
- `bootstrap/app.php`: registra rotas e configura a aplicação Laravel.
- `resources/views/app.blade.php`: HTML base e dados iniciais do usuário.
- `resources/js/app.jsx`: monta o React no elemento `#root`.

## Frontend

- `resources/js/App.jsx`: shell, autenticação, menu e páginas gerais.
- `resources/js/api.js`: chamadas para `/api/v1`, sessão e proteção CSRF.
- `resources/js/Management.jsx`: projetos, parâmetros e financeiro.
- `resources/js/TasksPage.jsx`: listagem e edição de tarefas.
- `resources/js/InventoryApp.jsx`: interface do estoque em `/estoque`.
- `resources/js/i18n.js`: seleção do idioma e aplicação das traduções.
- `resources/js/translations.js`: textos em português, inglês e espanhol.
- `resources/js/tutorial/`: conteúdo, progresso e interface do tutorial.
- `resources/css/`: estilos separados por área da interface.

Para adicionar uma página ao menu, altere `navGroups` e `pageMeta` em
`App.jsx`, crie o componente e inclua sua renderização na seleção por
`activePage` dentro de `Dashboard`.

## Backend

- `routes/api.php`: API da SPA e rotas JWT de compatibilidade.
- `routes/web.php`: OAuth, perfil, recuperação de senha e fallback da SPA.
- `app/Http/Controllers/`: entrada HTTP; valida acesso e prepara respostas.
- `app/Http/Requests/`: validação reutilizável das requisições.
- `app/Domain/`: regras de negócio que não devem ficar nos controllers.
- `app/Models/`: entidades e relacionamentos do banco.
- `database/migrations/`: estrutura e evolução do banco.
- `database/seeders/`: dados iniciais e demonstração local.

Ao criar uma operação com várias gravações, coloque a regra em uma ação de
domínio e use `DB::transaction`. Controllers devem permanecer pequenos.

## Acesso e segurança

- `app/Models/User.php`: perfis e resolução de permissões.
- `app/Http/Controllers/Api/AuthController.php`: login da SPA e JWT legado.
- `app/Http/Controllers/Api/AccountSecurityController.php`: senha, sessões,
  provedores OAuth e autenticação em duas etapas.
- `config/auth.php`, `config/jwt.php` e `config/session.php`: guards, JWT e
  cookies de sessão.

## Configuração por ambiente

As credenciais e opções variáveis devem ficar no `.env`, nunca no código.
Consulte principalmente:

- `APP_URL`, `APP_ENV`, `APP_DEBUG`;
- `DB_CONNECTION` e demais opções `DB_*`;
- `MAIL_*` e `QUEUE_CONNECTION`;
- `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`;
- `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET` e tenant;
- `SESSION_SECURE_COOKIE` e `SESSION_SAME_SITE`;
- `PYTHON_BINARY` para os relatórios analíticos.

Depois de mudar configuração, execute `./php artisan config:clear`.

## Verificação

```bash
./composer test
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
```

Os scripts `test:*` do `package.json` executam os fluxos de navegador de cada
área. Eles exigem Chromium do Playwright e a aplicação em execução.
