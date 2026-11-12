# Verificação das mensagens de erro — 11/11/2026

## Escopo implementado

- Catálogo completo das chaves de validação fornecidas pelo framework instalado, com mensagens pt/en/es, nomes legíveis dos campos usados nas validações e mensagem específica de proposta duplicada. Não modifica regras de unicidade nem libera números de projetos arquivados.
- Catálogos de autenticação, recuperação de senha e descrições HTTP nos três idiomas. A preferência da interface é enviada no cabeçalho `X-Spot-Locale`, aceito somente para os idiomas suportados.
- Resumo estável nas respostas 422, preservando o mapa `errors`. Mensagens específicas das regras de negócio continuam disponíveis. Falhas 5xx continuam sem SQL, credenciais ou rastreamento na resposta.
- Editor compartilhado de projetos e cadastros: nome acessível do diálogo, foco pelo `useModalFocus`, identificação dos campos inválidos, vínculo das mensagens com `aria-describedby`, resumo anunciado, conservação dos valores e mensagens de campos não renderizados disponíveis no resumo.
- Cliente HTTP: mensagem de rede traduzida com orientação de recuperação; cancelamento intencional continua sendo cancelamento. `apiJson` preserva erros por campo e status para consumidores.
- Inventário estático reproduzível de mensagens: [catálogo](mensagens-de-erro.md). A busca por contextos pode incluir trechos auxiliares; não é prova de cobertura de todos os caminhos de execução.

## Verificações executadas

| Verificação | Resultado |
| --- | --- |
| `./php artisan test` | 266 testes, 4.060 asserções, sem falhas; banco de testes isolado |
| Testes novos de mensagens | Cobertura de todas as chaves/grupos do catálogo instalado; respostas por idioma; datas, limites, campos aninhados e senhas; proposta de projeto arquivado; preservação de mensagens de domínio |
| `npm run build` | Aprovado |
| `node scripts/test-management.mjs` | Aprovado; inclui falha 422 com dois campos, anúncio do resumo, mensagem específica de duplicidade, conservação dos valores e correção seguida de reenvio; APIs simuladas |
| `node scripts/test-errors.mjs` | Aprovado; recuperação HTTP, rede, falha de renderização, retorno OAuth e 404 real sem JavaScript em 320 px |
| `node scripts/test-accessibility.mjs` | Aprovado; navegação, foco, Tab/Shift+Tab, Escape, retorno do foco, movimento reduzido e recursos de leitura |
| `node scripts/test-languages.mjs` | 144 verificações, nenhuma falha ou item de revisão |
| `npm run test:responsive` | 323 verificações, sem erros; 320–1440 px, retrato/paisagem, 100% e 120%, páginas públicas e painel preenchido |
| `git diff --check` | Sem problemas de espaços |

As cores novas de texto de erro possuem contraste calculado de aproximadamente 7,8:1 (`#a11b2b` sobre branco) e 8,8:1 (`#ffb4bd` sobre `#24282d`). Em cores forçadas, a mensagem usa `CanvasText`. O erro também é comunicado por texto e atributos, sem depender apenas da cor. O cálculo é limitado a essas combinações declaradas; não é uma auditoria integral das telas.

## Limitações e revisão humana

Os testes de navegador usam Chromium e majoritariamente respostas simuladas. A captura enviada pelo usuário foi no Firefox; não houve homologação manual completa nesse navegador. A suíte de responsividade cobre seus cenários existentes, inclusive nomes longos, mas não todas as combinações possíveis de mensagens, formulários e zoom. Mensagens específicas já existentes mantêm suas traduções e mecanismos de associação por campo; somente o editor compartilhado recebeu a nova apresentação por campo nesta alteração.

Permanecem necessárias revisão com leitor de tela, avaliação humana de teclado em todos os formulários, zoom real de navegador, telas pequenas, cores forçadas no sistema e, quando disponível, participação de pessoas com deficiência. Não se declara conformidade integral WCAG 2.2 AA com esses resultados. Nenhuma nova medição de satisfação ou desempenho de usuários foi realizada.

## Ajuste de espaçamento dos campos — 11/11/2026

Os rótulos em grade passam a alinhar seu conteúdo ao início (`align-content: start`). Assim, uma mensagem de erro ou ajuda em uma coluna deixa espaço abaixo do campo vizinho, sem distribuir essa altura entre o rótulo e o controle. A correção mantém as colunas e as regras responsivas existentes. Verificações: build, suítes de cadastros/projetos, acessibilidade automatizada e responsividade. Permanecem as limitações de revisão humana descritas acima.
