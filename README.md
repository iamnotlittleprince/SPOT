# Spot Desktop — NativePHP + Laravel 13

Aplicação desktop para **Windows / macOS / Linux** com a tela de login visual do Spot.

## Stack utilizada

| Ferramenta | Versão |
|---|---|
| PHP | 8.5.6 |
| Laravel | 13.14.0 |
| Node.js | 22.22.3 |
| Composer | 2.10.0 |
| NativePHP/Electron | latest |

---

## 1. Criar o projeto Laravel

```bash
composer create-project laravel/laravel spot-desktop
cd spot-desktop
```

---

## 2. Instalar dependências PHP

```bash
# NativePHP
composer require nativephp/electron

# Socialite (OAuth Google + Microsoft)
composer require laravel/socialite
composer require socialiteproviders/microsoft
```

---

## 3. Instalar dependências Node

```bash
npm install
```

---

## 4. Publicar e configurar o NativePHP

```bash
php artisan native:install
```

Isso publica o arquivo `config/nativephp.php` e registra os Service Providers necessários.

---

## 5. Copiar os arquivos deste projeto

Substitua / crie os arquivos nas respectivas pastas:

```
resources/
  css/app.css                          ← estilos da tela de login
  js/app.js                            ← JS principal
  views/
    auth/login.blade.php               ← view de login
    auth/forgot-password.blade.php     ← (criar conforme necessidade)

app/Http/Controllers/Auth/
  LoginController.php
  SocialLoginController.php

app/Http/Requests/Auth/
  LoginRequest.php

routes/
  web.php

config/
  nativephp.php
  services.php

vite.config.js
.env.example → copie para .env e preencha
```

---

## 6. Variáveis de ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Preencha no `.env`:

```dotenv
# SQLite (mais simples para desktop)
DB_CONNECTION=sqlite

# Google OAuth
GOOGLE_CLIENT_ID=seu-client-id
GOOGLE_CLIENT_SECRET=seu-client-secret

# Microsoft OAuth
MICROSOFT_CLIENT_ID=seu-client-id
MICROSOFT_CLIENT_SECRET=seu-client-secret
MICROSOFT_TENANT_ID=common
```

---

## 7. Migrar banco de dados

```bash
php artisan migrate
```

---

## 8. Adicionar ícones / imagens

Coloque na pasta `public/images/`:

- `spot-logo.svg` — logotipo Spot (rodapé esquerdo)
- `spot-icon.png` — ícone do app (512×512 px)
- `google-icon.svg` — ícone colorido Google
- `microsoft-icon.svg` — ícone colorido Microsoft

---

## 9. Rodar em desenvolvimento

```bash
# Terminal 1 — Vite (assets)
npm run dev

# Terminal 2 — NativePHP (abre a janela Electron)
php artisan native:serve
```

---

## 10. Build para distribuição

```bash
npm run build
php artisan native:build
```

O instalador ficará em `dist/`.

---

## Estrutura de pastas gerada

```
spot-desktop/
├── app/
│   └── Http/
│       ├── Controllers/Auth/
│       │   ├── LoginController.php
│       │   └── SocialLoginController.php
│       └── Requests/Auth/
│           └── LoginRequest.php
├── config/
│   ├── nativephp.php
│   └── services.php
├── public/
│   └── images/          ← coloque aqui os SVG/PNG
├── resources/
│   ├── css/app.css
│   ├── js/app.js
│   └── views/auth/
│       └── login.blade.php
├── routes/
│   └── web.php
├── vite.config.js
├── .env.example
└── README.md
```

---

## Notas importantes

- **SQLite** é recomendado para apps desktop (sem servidor externo).
- O provider Microsoft do Socialite requer registro em `config/app.php` → `providers` ou via `EventServiceProvider`.
- Para produção, rode `php artisan optimize` antes do build.
- A janela é configurada em `config/nativephp.php` (tamanho, título, ícone).
