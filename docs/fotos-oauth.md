# Fotos nos logins Google e Microsoft

Diagnóstico e ajustes em 28/10/2026.

## O que foi verificado

Os callbacks preservavam qualquer `avatar_url` existente, impedindo a atualização de fotos importadas. No Google, o campo guardava diretamente a URL externa do perfil. A interface tentava exibir a imagem sem tratar falhas de carregamento.

A configuração Microsoft não habilita `include_avatar`, mas isso **não prova ausência de busca da foto**: o `Provider` instalado usa `MicrosoftUser`, cuja implementação de `getAvatar()` faz uma requisição própria ao Graph e retorna um objeto que pode ser convertido em data URI. A hipótese inicial de que a configuração, sozinha, causava o problema foi descartada após revisar essa classe concreta.

Verificações somente de leitura no ambiente local, sem imprimir credenciais, nomes, URLs pessoais ou conteúdo de imagens:

- A URL Google já armazenada retornou HTTP 200 e uma imagem válida no servidor. Portanto, não foi reproduzida uma indisponibilidade dessa URL; o problema no navegador não foi confirmado por esse teste.
- Uma conta vinculada à Microsoft estava sem avatar; a consulta ao Graph usando seu token armazenado retornou HTTP 401. Isso comprova rejeição da credencial atual, mas não identifica se ela expirou, foi revogada ou se houve outra condição durante o callback original.
- O link público `public/storage` existe e aponta para o armazenamento público local.

Não foi realizado um novo consentimento/login real com as contas do usuário. Os resultados acima não equivalem a reproduzir o callback original.

## Correção

`app/Domain/Profile/OAuthAvatar.php` centraliza a importação após validar a conta e salvar os dados do provedor:

- Google: usa a URL de foto recebida pelo Socialite; aceita HTTPS e hosts Google `googleusercontent.com`/subdomínios, sem seguir redirecionamentos.
- Microsoft: consulta diretamente `GET https://graph.microsoft.com/v1.0/me/photo/$value` com o token recém-recebido. O driver instalado já solicita `User.Read`; não foram acrescentadas permissões amplas de diretório nem alteradas as credenciais/configurações dos aplicativos externos.
- Valida JPEG, PNG ou WebP com até 5 MB e salva uma cópia em `storage/app/public/avatars/providers/{user_id}/`. O banco guarda uma URL local relativa, evitando depender da entrega externa da imagem a cada exibição.
- Atualiza a foto importada a cada login OAuth bem-sucedido e remove a cópia anterior depois de salvar a nova. Fotos enviadas manualmente pelo perfil mantêm prioridade.
- Não impede autenticação quando a foto não existe, o acesso é negado, ocorre timeout ou o serviço/armazenamento falha. Preserva o avatar anterior nesses casos. Os registros de diagnóstico contêm somente o provedor e, quando disponível, o status HTTP.
- Se uma imagem falhar no navegador, `UserAvatar` mostra as iniciais. O elemento de imagem usa `referrerPolicy="no-referrer"`.

A correção entra em ação **no próximo login pelo Google ou Microsoft**. Não houve alteração em lote das contas nem uso dos refresh tokens para reparar contas automaticamente. Se o provedor não disponibilizar uma foto, as iniciais permanecem e o usuário pode enviar uma imagem manualmente.

A API Microsoft documenta a leitura da foto, as permissões e a resposta 404 para ausência de imagem: [Get profilePhoto — Microsoft Graph](https://learn.microsoft.com/en-us/graph/api/profilephoto-get?view=graph-rest-1.0).

## Validação

- `tests/Feature/OAuthAvatarTest.php`: callbacks dos dois provedores, arquivo persistido, renovação de imagem antiga, preservação de foto manual, cabeçalho de autenticação somente no Graph, ausência/negação/indisponibilidade, imagem inválida, hosts/redirecionamentos rejeitados e falha ao salvar com limpeza do arquivo.
- Testes existentes de OAuth Google/Microsoft, avatar e provisionamento de contas.
- Build concluído; `scripts/test-oauth-avatar.mjs` confirma foto local e retorno às iniciais em desktop e mobile. Verificações compartilhadas de teclado também aprovadas.

Os testes de OAuth simulam as respostas dos provedores. A confirmação com uma conta real exige um novo login do usuário; não foi realizada avaliação com leitor de tela nesta alteração. Em implantação, o armazenamento público e o link `/storage` devem estar disponíveis.


## Confirmação adicional da Microsoft e correção da renovação

A investigação continuou com a conta Microsoft real já vinculada no ambiente local:

1. O token salvo tinha a validade vencida; o Graph retornava `401 InvalidAuthenticationToken`.
2. A renovação com o refresh token foi aceita pela Microsoft: HTTP 200. As novas credenciais e sua validade foram gravadas com os casts criptografados existentes.
3. Com o token renovado, a chamada de foto retornou **HTTP 404, código `ImageNotFound`**. Isso confirma ausência de foto disponibilizada por esse endpoint para a conta; não é um erro de renderização da imagem pelo Spot.
4. O novo fluxo de busca foi exercitado com a conta real e respondeu `no_photo`, preservando o avatar anterior e usando o token válido.

Não é possível criar uma foto que o Graph não fornece. A pessoa pode usar **Meu perfil → Alterar foto → Enviar imagem**, ou atualizar a foto na conta Microsoft e tentar **Buscar foto da Microsoft** novamente. Uma foto visível em outro produto Microsoft não foi verificada nesta investigação.

Foi identificada e corrigida outra falha do código: a renovação da agenda solicitava somente `offline_access Calendars.ReadWrite`, sem manter `User.Read`. O serviço compartilhado `app/Domain/Auth/MicrosoftToken.php` agora renova com `offline_access User.Read Calendars.ReadWrite`, mantém refresh tokens rotacionados e evita renovações simultâneas da mesma conta usando bloqueio de linha. Agenda e importação reutilizam essa credencial. A solicitação de escopos na renovação segue os escopos já pedidos no login; não foram solicitadas permissões administrativas adicionais. Consulte [escopos Microsoft](https://learn.microsoft.com/en-us/entra/identity-platform/scopes-oidc).

`POST /profile/avatar/microsoft`, autenticado, protegido por CSRF e limitado a seis tentativas por minuto, busca a foto somente para a conta da sessão. A foto recebe uma nova tentativa após renovar o token quando o Graph responde 401; não há repetição ilimitada. A resposta contém `status`, `message` e `avatar_url`, sem credenciais. Os estados são `updated`, `no_photo`, `custom_photo`, `reconnect` e `unavailable`. O menu **Alterar foto → Buscar foto da Microsoft** apresenta o resultado sem apagar a foto atual. Fotos enviadas manualmente continuam prioritárias.

Testes adicionais em `tests/Feature/MicrosoftPhotoTest.php` e `scripts/test-microsoft-photo.mjs` cobrem renovação com os escopos de perfil/agenda, rotação do refresh token, nova tentativa após 401, resposta 404, reconexão, preservação da foto manual e isolamento entre contas. O callback real original não foi reproduzido; a consulta com credenciais renovadas e o fluxo novo foram verificados.
