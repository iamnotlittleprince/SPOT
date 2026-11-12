# Primeiro acesso, login e sessão expirada

Verificação local em 12/11/2026.

O frontend considerava `?home` uma indicação suficiente de autenticação, mesmo
quando `window.__SPOT__.authenticated` era falso. O primeiro acesso redirecionava
para essa URL. Assim, uma sessão ausente podia abrir o painel e gerar respostas
401 e a tela global de erro. O logout só navegava ao início em respostas 2xx.

Agora a abertura inicial do painel depende da identidade informada pelo servidor.
Após criar a senha, a navegação vai para `/`, que consulta novamente a sessão.
O login confirma `/api/v1/auth/me` antes de montar as telas privadas; uma resposta
401 aparece no formulário, sem abrir o painel. Logout com resposta 401 retorna
ao início, pois a sessão já não está autenticada. Outros erros continuam visíveis.

## Duas abas e troca de conta

Em uma segunda investigação, o erro foi reproduzido com Chromium, backend PHP e
PostgreSQL reais em schema descartável: login do administrador na primeira aba,
ativação de outra conta na segunda e respostas 401 após ativação bem-sucedida.
As rotas v1 executavam o grupo `web` e também o wrapper stateful do Sanctum,
duplicando o processamento de cookies e sessão em requisições do navegador.

As rotas v1 agora usam apenas uma camada `web` (cookies, sessão e proteção CSRF).
`AuthenticateBrowserSession` mantém a verificação de senha do Sanctum para os
mesmos requests de frontend, sem reiniciar a sessão. As rotas de compatibilidade
JWT continuam com sua configuração anterior.

Mudanças de autenticação não disparam mais a atualização comum de dados entre
abas. Um marcador aleatório de alteração de sessão avisa as outras abas, que
consultam `/auth/me` e retiram a tela privada quando a identidade mudou ou saiu.
O aviso permite reabrir o Spot com a sessão atual. Não são armazenados tokens,
links de ativação, senhas ou identificadores de usuário nesse marcador. O foco
e o retorno à aba também verificam a sessão, inclusive sem localStorage.

O frontend informa a identidade esperada em `X-Spot-User`; a API rejeita com 409
`session_changed` se o cookie corresponde a outra conta. Isso impede inclusive
que um logout atrasado da aba anterior encerre a sessão da conta recém-ativada.
O cabeçalho não concede permissões: autenticação e autorização continuam no
servidor. Duas abas da mesma conta continuam funcionando normalmente.

Não há suporte a duas contas independentes no mesmo contexto de cookies. Para
isso, usar perfis ou contextos de navegador separados. Confirmar em produção a
mesma sequência após publicar a nova imagem e recarregar as abas antigas.

## Verificações

- `npm run build`: aprovado.
- `./composer test`: 272 testes e 4105 asserções aprovados. Inclui sessão em banco,
  cookies entre requisições e ativação substituindo a sessão anterior.
- `node scripts/test-session-flow.mjs`: aprovado. Chromium com API simulada,
  cobrindo URL sem sessão, confirmação de sessão no login, retorno após ativação
  e logout com sessão expirada. Não cria contas nem envia e-mails reais.
- `node scripts/test-session-tabs.mjs`: aprovado. Chromium com backend real,
  contas sintéticas e PostgreSQL em schema novo, removido ao final. Cobre duas
  abas da mesma conta, troca após ativação, persistência após recarga, bloqueio
  de logout da identidade anterior, rejeição de request de origem não confiável
  sem CSRF e logout normal. O aviso foi verificado em 320, 390 e 1440 px, sem
  expansão horizontal, e seu link pode receber foco por teclado.
- `npm run test:responsive`: 323 verificações aprovadas, 320–1440 px, retrato e
  paisagem, leitura em 100% e 120%.
- `npm run test:languages`: 144 verificações aprovadas, sem falhas.
- `node scripts/test-accessibility.mjs`: falhou na asserção de limites horizontais
  do painel de ajustes de leitura (`scripts/test-accessibility.mjs:51`). Não foi
  atribuída causalidade a esta alteração de autenticação; a avaliação geral de
  acessibilidade permanece pendente nesse ponto.
- Não houve alteração de layout compartilhado. Não foi realizada revisão humana
  com leitor de tela ou teste dos provedores OAuth reais. Os resultados não
  representam declaração de conformidade WCAG.

## Publicação

Publicar os middlewares `AuthenticateBrowserSession` e `VerifySessionIdentity`,
`routes/api.php`, os arquivos de frontend `App.jsx`, `api.js`, `sessionSync.js`,
`useSessionSync.js`, as traduções JS e `lang/*/errors.php`, os testes de sessão,
`scripts/browser-fixtures.mjs` e este documento. No servidor, reconstruir a imagem
e recriar web, worker e scheduler após baixar o commit. Não há migração de banco
para esta correção. Preservar `.env.production`, as chaves e os volumes existentes.
