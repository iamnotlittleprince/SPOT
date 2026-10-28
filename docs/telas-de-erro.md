# Telas de erro do Spot

Implementação em 28/10/2026. A identidade visual usa cartão responsivo, marca Spot, código, mensagem específica, orientação e ações de recuperação. Suporta tema claro/escuro, teclado, foco visível e telas pequenas.

## Cobertura

| Origem | Comportamento |
| --- | --- |
| Exceções HTTP de páginas Laravel | Tela HTML própria, preservando status e headers, incluindo `Retry-After`. Não depende de React, Vite, CDN ou fontes externas. |
| 400, 401, 402, 403, 404, 405, 408, 409, 410, 413, 415, 419, 422, 429, 500, 502, 503, 504 | Título e orientação específicos. Outros códigos 4xx/5xx recebem mensagem da família. |
| Endereço web desconhecido | Retorna 404 real, em vez de carregar a aplicação com status 200. Rotas existentes de login, primeiro acesso, convite e estoque continuam disponíveis. |
| Respostas API no cliente compartilhado | Erros HTTP abrem a tela de recuperação sobre a aplicação, mantendo o componente e seus dados montados. O usuário pode fechar e voltar à tela. |
| 400, 409 e 422 em formulários | Continuam tratados na tela de origem, sem substituir mensagens e validações por campo. Login 401 também mantém a mensagem local. |
| Falha de rede | Tela com orientação para verificar a conexão. Cancelamentos `AbortError` não são apresentados como falha. |
| Erro de renderização React | Error Boundary apresenta recuperação com recarga ou início, em vez de deixar a tela em branco. |
| Erros JavaScript e promessas sem tratamento após o carregamento | Tela genérica de falha, sem exibir o conteúdo técnico do erro. |
| Retorno OAuth com `auth_error` | Tela de integração ou conta indisponível, incluindo cancelamentos e problemas de vínculo/cadastro já sinalizados pelos controladores. |

O catálogo de mensagens em `resources/errors/messages.json` é compartilhado pelo servidor e React. As mensagens React têm traduções pt/en/es no catálogo da interface; o HTML independente do servidor usa português. O CSS independente fica em `public/spot-errors.css`.

A configuração usa o mecanismo `respond` de exceções do [Laravel 13](https://github.com/laravel/docs/blob/13.x/errors.md). APIs e requisições com `Accept: application/json` continuam recebendo JSON. Falhas 5xx não expõem traces, SQL ou credenciais no corpo JSON, mesmo com debug habilitado. O log do servidor continua disponível para diagnóstico.

## Recuperação e integração com as telas existentes

O cliente `sessionRequest` e a função `spotFetch` para endpoints web existentes (perfil, avatar e recuperação de senha) anunciam falhas por `spot:request-error`. O `RequestErrorHost` mostra a primeira falha e evita repetir o mesmo código na mesma página a cada atualização automática. A troca de página ou navegação pelo histórico reinicia essa supressão. Respostas e rejeições continuam disponíveis ao chamador, que mantém seu tratamento local. O erro não dispara repetição automática da requisição.

Uma tela que já tenha tratamento completo para um status pode usar `handledStatuses`, como o painel de portfólios faz para o 403 com sua mensagem de acesso restrito. Isso evita apresentar duas interfaces para a mesma falha. Não use a opção para esconder falhas sem oferecer recuperação local.

A inicialização CSRF deve ter sucesso antes de enviar alterações; se ela falhar, a requisição de escrita não é executada. Nenhum botão repete POST/PUT/DELETE automaticamente. Antes de repetir uma operação, a interface orienta conferir seu resultado. A recarga de uma tela React com falha pode perder dados não salvos; isso é informado.

## Limites

- Páginas de erro de Google/Microsoft, como o 400 mostrado em `accounts.google.com`, pertencem ao provedor e não podem ser personalizadas pelo Spot. O Spot personaliza apenas suas páginas e os retornos que recebe; uma falha externa que não redirecione de volta continua na interface externa.
- Se o navegador não alcançar o servidor antes de carregar a aplicação, ou se o JavaScript principal não carregar, o navegador pode mostrar sua própria falha. O Error Boundary não cobre falhas anteriores ao seu carregamento.
- Respostas produzidas diretamente pelo proxy, servidor web ou infraestrutura externa precisam da configuração dessa camada. As páginas Laravel cobrem exceções que chegam ao seu tratador; respostas HTML de erro retornadas manualmente fora dele devem usar a view compartilhada.
- Não há garantia de preservação de formulários depois de uma falha de renderização; a preservação da tela aplica-se ao overlay de requisições.
- O mecanismo de foco segue o hook existente. A avaliação completa com leitores de tela e a revisão de exposição do fundo dos diálogos continuam pendentes.

## Verificações

Com servidor em `http://localhost:8000`:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
./php vendor/bin/phpunit tests/Feature/ErrorPageTest.php
node scripts/test-errors.mjs
node scripts/test-browser-navigation.mjs
node scripts/test-accessibility.mjs
node scripts/test-languages.mjs
```

O teste PHP cria rotas somente no ambiente de teste: nenhum endpoint público para provocar falhas foi acrescentado. Verifica vinte códigos HTTP, status/headers preservados, JSON, validação e ausência de detalhes internos. O teste de navegador verifica respostas HTTP pela API, rede, captura React, retorno OAuth e a página 404 real sem JavaScript em 320 px. Também verifica mensagens em inglês/espanhol e erro de sessão sobre o modal de evento: o foco fica na tela de erro, o formulário mantém o título e nenhuma escrita é enviada quando a inicialização CSRF falha. O hook de foco coordena uma pilha de diálogos para que somente o superior contenha o teclado.

## Resultado da validação

- Build de produção aprovado.
- Testes PHP de páginas de erro, entrada por Google/Microsoft, recuperação de senha, sessão, perfil e avatar aprovados.
- Testes de navegador de erros, navegação e acessibilidade aprovados; inclui overlay em 320 px com fonte Verdana e escala de 120%.
- Idiomas: 144 verificações sem falhas; permanecem os avisos de revisão de textos anteriores já registrados na documentação de acessibilidade.
- Tutorial: 645 verificações, nenhum erro. O cenário que simula indisponibilidade do serviço agora fecha a tela de recuperação antes de continuar a orientação.
- `git diff --check` sem erros.
