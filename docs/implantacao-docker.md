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
credenciais PostgreSQL, SMTP, Google e Microsoft. Em uma instalação nova, gere uma `APP_KEY` no
servidor com (em atualizações, preserve a chave existente):

```sh
docker compose --env-file .env.production build web
docker compose --env-file .env.production run --rm --no-deps web php artisan key:generate --show
```

Cole o valor completo em `APP_KEY` no arquivo `.env.production`. Gere também
uma `JWT_SECRET` para a API de estoque/autenticação por token:

```sh
docker compose --env-file .env.production run --rm --no-deps web php artisan jwt:secret --show
```

Copie o resultado para `JWT_SECRET` e preserve ambas as chaves nas atualizações. Proteja esse
arquivo (`chmod 600 .env.production`) e preserve a chave e os volumes ao atualizar
o Spot. Nunca reutilize o `.env` local nem publique credenciais no Git.

## Iniciar

```sh
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d database
docker compose --env-file .env.production run --rm web php artisan migrate --force
docker compose --env-file .env.production up -d
```

O `APP_URL` deve ser a origem HTTPS e `APP_DOMAIN` o hostname correspondente.
`SESSION_DOMAIN=null` mantém o cookie restrito ao host; se informar um domínio,
use o domínio correto da instalação. URLs de retorno OAuth devem usar a mesma origem HTTPS. Cadastre também exatamente as URLs
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


## Revisão antes de publicar

Consulte `docs/revisao-producao-2026-11-10.md`. No ambiente de homologação/CI, com dependências de desenvolvimento e servidor local na porta 8000:

```sh
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
node scripts/verify-production-browser.mjs
docker build -t spot:production-review .
python3 scripts/verify-container-tests.py
```

O último comando usa PostgreSQL descartável e não conecta ao banco da instalação. As suítes de navegador usam respostas simuladas; elas não substituem o teste dos provedores reais.

Após configurar os segredos e aplicar as migrations no servidor de destino:

```sh
docker compose --env-file .env.production exec web php scripts/check-production.php
```

O comando é somente leitura e não imprime credenciais. Confere ambiente, debug, HTTPS, cookies, build, escrita em diretórios e conexão/migrations. Mesmo com saída zero, valide envio/recebimento real de e-mail, callbacks OAuth e serviços externos, pois o comando não realiza esses envios.

Para uma instalação nova, provisione os perfis e a primeira conta **somente depois** de preencher `INITIAL_ADMIN_EMAIL` e uma senha forte em `INITIAL_ADMIN_PASSWORD`, usando `php artisan db:seed --force` no serviço web. O seed atualiza a conta inicial; não o execute automaticamente em toda implantação. Em atualização, mantenha banco, arquivos e chave existentes.

Não envie `public/hot`, `.env`, backups, uploads locais nem a pasta `output` na imagem. Os envs entram por `env_file` e uploads ficam no volume `spot_storage`. O Dockerfile prepara os diretórios vazios e o `.dockerignore` exclui os dados locais.

Confirme que worker e scheduler estão ativos após cada atualização. A fila inclui `emails,default`; tarefas agendadas rodam dentro do processo PHP, sem depender de um segundo executável PHP. Não execute o scheduler manualmente em homologação com destinatários reais apenas para testar sua disponibilidade: ele pode gerar entregas.
