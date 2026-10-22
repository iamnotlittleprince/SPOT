# Implantação do Spot em um servidor

Este pacote executa o Spot em Docker Compose com Caddy/FrankenPHP, PostgreSQL,
worker de e-mail e agendador. O Caddy emite e renova HTTPS automaticamente para
`APP_DOMAIN`. Use um servidor Linux com Docker Engine e o plugin Docker Compose.

## Preparar o servidor

1. Aponte o registro DNS `A` do domínio para o IP público do servidor. Se usar
   `www`, configure também esse nome no DNS e no Caddyfile.
2. Libere as portas TCP 80 e 443 (e UDP 443 para HTTP/3) no firewall.
3. Instale Docker Engine e Docker Compose no servidor.
4. Envie o código ao servidor por Git e entre na pasta do projeto.

## Configurar segredos

Copie `.env.production.example` para `.env.production` e preencha domínio,
credenciais PostgreSQL, SMTP, Google e Microsoft. Gere uma `APP_KEY` nova no
servidor com:

```sh
docker compose --env-file .env.production build web
docker compose --env-file .env.production run --rm --no-deps web php artisan key:generate --show
```

Cole o valor completo em `APP_KEY` no arquivo `.env.production`. Proteja esse
arquivo (`chmod 600 .env.production`) e preserve a chave e os volumes ao atualizar
o Spot. Nunca reutilize o `.env` local nem publique credenciais no Git.

## Iniciar

```sh
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d database
docker compose --env-file .env.production run --rm web php artisan migrate --force
docker compose --env-file .env.production up -d
```

O `APP_URL`, `APP_DOMAIN`, `SESSION_DOMAIN` e URLs de retorno dos provedores OAuth
devem usar o mesmo domínio HTTPS. Cadastre também exatamente as URLs
`/auth/google/callback` e `/auth/microsoft/callback` nos respectivos provedores.

Para acompanhar os serviços:

```sh
docker compose --env-file .env.production ps
docker compose --env-file .env.production logs -f web worker scheduler
```

## Atualizar e fazer backup

Após enviar uma nova versão:

```sh
docker compose --env-file .env.production build
docker compose --env-file .env.production run --rm web php artisan down
docker compose --env-file .env.production run --rm web php artisan migrate --force
docker compose --env-file .env.production up -d --remove-orphans
docker compose --env-file .env.production run --rm web php artisan up
```

Faça backups regulares do banco PostgreSQL e da pasta `storage` (avatares e
arquivos enviados). Guarde os backups fora do servidor. Não remova volumes ao
atualizar: `docker compose down -v` apaga o banco e os arquivos persistentes.

O Compose publica somente HTTP/HTTPS; o PostgreSQL não é exposto à internet. O
volume de dados mantém o banco, arquivos e certificados entre reinicializações.
