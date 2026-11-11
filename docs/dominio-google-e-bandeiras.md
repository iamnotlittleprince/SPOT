# Google e bandeiras em outra máquina — 10/11/2026

O erro `Missing required parameter: client_id` significa que a autorização enviada ao Google não contém o identificador da aplicação. No Spot, esse valor vem de `GOOGLE_CLIENT_ID`, lido por `config/services.php`. O diagnóstico da instalação remota precisa conferir suas variáveis e seu cache de configuração; este trabalho não acessou a hospedagem.

## Configurar no ambiente do domínio

1. No [Google Cloud Console](https://console.cloud.google.com/), selecionar o projeto e criar ou editar um cliente OAuth do tipo **Aplicativo da Web**.
2. Cadastrar como URI de redirecionamento autorizado o endereço completo `https://SEU-DOMINIO/auth/google/callback`. Usar o domínio real, incluindo subdomínio e HTTPS exatamente como no acesso.
3. Configurar na hospedagem, substituindo os valores abaixo:

```dotenv
APP_URL=https://SEU-DOMINIO
GOOGLE_CLIENT_ID=ID_DO_CLIENTE.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=SEGREDO_DO_CLIENTE
GOOGLE_REDIRECT_URI=https://SEU-DOMINIO/auth/google/callback
```

Essas configurações pertencem ao backend. O segredo não deve ser incorporado ao JavaScript nem versionado no Git.

4. Em hospedagem PHP convencional, atualizar o cache na raiz da aplicação:

```sh
php artisan config:clear
php artisan config:cache
```

Se o ambiente usa o runtime portátil deste projeto, os comandos equivalentes começam com `./php artisan`. Credenciais antigas em cache não são substituídas apenas editando o arquivo `.env`.

No Docker Compose do Spot, preencher `.env.production` e recriar os serviços para que recebam as novas variáveis e o código:

```sh
docker compose --env-file .env.production up -d --build web worker scheduler
```

Não substituir `APP_KEY`, `JWT_SECRET` ou volumes existentes nesta correção. Na imagem padrão, não há cache de configuração persistido pelo projeto; se a instalação tiver criado um cache manualmente, atualizá-lo no serviço web também.

5. Repetir o login pelo domínio. O próximo passo de consentimento, permissões e usuários autorizados depende da configuração do projeto Google. Este ajuste não autentica contas não provisionadas no Spot.

O Google exige `client_id` e correspondência exata do `redirect_uri`: [documentação oficial](https://developers.google.com/identity/protocols/oauth2/web-server).

## Bandeiras

O seletor utilizava emojis, portanto sua representação dependia da fonte e do sistema operacional do visitante. Agora usa SVGs locais em `resources/images/flags/`, compartilhados pelo login, cabeçalho e chat. O Vite gera arquivos versionados dentro de `public/build`; nenhuma imagem de bandeira é buscada em CDN.

Em hospedagem convencional, gerar e publicar o build completo junto ao código:

```sh
npm ci
npm run build
```

O Node é necessário para construir os assets; não precisa permanecer rodando no servidor para atender a interface compilada. Publicar `public/build`, incluindo `manifest.json` e os SVGs gerados. Evitar misturar arquivos de builds diferentes. Se houver cache de CDN, invalidar o HTML antigo e testar recarregamento completo do navegador. No Docker acima, o build da imagem já inclui essa etapa.

Um domínio pode servir uma aplicação sem Vite, mas o Spot completo continua precisando do backend PHP e do PostgreSQL. Uma hospedagem somente de arquivos estáticos não executa login, OAuth, API ou gravação de dados.

## Alterações e verificações

- SVGs locais substituem emojis; mantidos nomes acessíveis, estado `aria-pressed`, foco e callbacks pt/en/es.
- Google sem ID, segredo ou callback permanece no Spot com orientação em pt/en/es, em vez de iniciar uma autorização incompleta.
- Build aprovado; teste Google: 5 testes/46 asserções aprovados.
- Idiomas: 144 verificações, sem falhas ou apontamentos.
- Responsividade: 323 verificações, 320–1440 px, orientação vertical/horizontal, leitura 100%/120% e nomes longos.
- Teste de teclado/modal, foco, redução de movimento e ajustes de leitura aprovado.
- Verificação direta no navegador: três SVGs carregados da própria origem e troca por teclado nos três idiomas.

Os testes de navegador usam Chromium e dados simulados. Não houve teste físico em Windows/macOS, avaliação com leitor de tela nem homologação Google no domínio real. As correções estão locais até o deploy.
