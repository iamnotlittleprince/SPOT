# Formatação de mensagens no Chat

Abra uma conversa e escolha **Formatar texto**. Selecione um trecho para formatá-lo, ou ative um estilo antes de digitar. A barra pode ser recolhida por **Ocultar formatação**, preservando o conteúdo. Ela também aparece ao editar uma mensagem formatada.

Os recursos equivalentes aos controles da imagem do Teams estão implementados no próprio Spot:

| Controle | Função no Spot |
| --- | --- |
| B — Negrito | Realça o trecho em negrito. Atalho Ctrl+B (Command+B no macOS). |
| I — Itálico | Aplica itálico ao trecho. Ctrl+I / Command+I. |
| U — Sublinhado | Sublinha o trecho. Ctrl+U / Command+U. |
| S — Tachado | Risca o texto, mantendo-o legível. |
| Lista com marcadores | Organiza itens com marcadores. |
| Lista numerada | Organiza itens em sequência numérica. |
| Marcador — Destacar texto | Aplica fundo amarelo com texto de contraste adequado. |
| Cor | Escolhe Automática, Roxo, Vermelho, Verde ou Azul; as cores se adaptam ao tema. |
| Tamanho | Escolhe Normal, Grande ou Maior, respeitando os ajustes de leitura do site. |
| Aspas — Citação | Destaca o trecho como citação com uma barra lateral. |
| Corrente — Link | Insere/edita o endereço e o texto visível, ou remove o vínculo preservando o texto. Aceita HTTP/HTTPS. |
| Código | Formata um bloco em fonte monoespaçada, preservando espaços e linhas. O código é texto e não é executado. |
| Mais opções | Desfazer, Refazer, Limpar formatação, Código em linha, Linha divisória e aumentar/diminuir recuo de itens de lista. |

**Enter** continua enviando a mensagem no campo de composição. **Ctrl + Shift + Enter** insere uma nova linha; dentro de uma lista, cria o próximo item; em código, preserva a estrutura do bloco. No formulário de edição, o envio da alteração continua sendo feito por **Salvar edição**. A edição só é permitida ao autor, dentro das 24 horas do envio original, incluindo alterações que modifiquem somente a formatação.

A janela de links tem rótulos visíveis, validação de endereço, foco inicial no endereço, contenção de Tab, Escape para fechar e retorno do foco ao botão que a abriu. Links enviados abrem com proteção `noopener noreferrer`. A inserção de um link no trecho selecionado preserva os outros estilos desse trecho.

## Armazenamento e compatibilidade

O editor usa Tiptap/ProseMirror, carregado sob demanda no Chat. A mensagem continua tendo um texto simples em `body` e passa a ter um documento estruturado opcional em `body_format`. O servidor gera o texto simples a partir do documento validado; um cliente não consegue salvar um texto de tradução diferente do conteúdo formatado. Mensagens antigas, sem documento estruturado, continuam sendo exibidas como texto literal.

O servidor aceita apenas parágrafos, títulos de nível limitado, listas, itens, citação, quebra de linha, divisória, código e as marcas disponíveis na barra. Cor e tamanho são valores predefinidos, sem CSS arbitrário. O documento tem limites de 5.000 caracteres, 60 KB serializados, 1.200 nós e profundidade 12. A renderização usa elementos React explícitos, sem inserir HTML recebido do usuário. Scripts, imagens embutidas, atributos de evento, URLs de script e protocolos fora de HTTP/HTTPS não são permitidos.

Espaços entre trechos e recuos de código são preservados: o middleware de limpeza de strings não remove espaços de campos dentro de `body_format`. Tanto a formatação quanto o texto ficam em cada versão do histórico; mudar só o estilo também incrementa a versão. Autoria, empresa, acesso por time/aprovação e prazo são verificados pelas mesmas regras do Chat. Anexos e sua retenção permanecem independentes do documento formatado.

A tradução continua usando o texto simples e é exibida no próprio balão. **Ver original** recupera o conteúdo com a formatação original. Traduzir não altera texto, formatação ou versões salvas.

## Uso por teclado e leitura

A barra usa botões nativos com nome acessível, estado pressionado nos estilos, seletores com rótulos e foco visível. Tab percorre os controles; os atalhos de negrito/itálico/sublinhado funcionam dentro do editor. Listas e citações são elementos semânticos. A contagem e o limite consideram caracteres Unicode, incluindo emojis. Colagem continua permitida; a estrutura suportada é mantida e elementos não aceitos são descartados ou rejeitados na validação. Cores da fonte usam uma paleta adaptada aos temas, e o destaque força contraste de primeiro plano mesmo em trechos coloridos. Cores forçadas usam as cores do sistema.

O editor e os controles se reorganizam em telas estreitas. No desktop, a composição pode rolar em sua própria área quando Mais opções está expandido. A janela de links é renderizada fora do formulário de composição, para que Aplicar link não envie uma mensagem acidentalmente, e aplica sua própria escala de leitura.

## Referências

- [Microsoft: Formatar uma mensagem no Teams](https://support.microsoft.com/en-us/teams/chat/format-a-message-in-microsoft-teams).
- [Microsoft: chat com leitor de tela, formatação e atalhos](https://support.microsoft.com/en-us/accessibility/teams/use-a-screen-reader-to-chat-in-microsoft-teams).
- [Tiptap: StarterKit](https://tiptap.dev/docs/editor/extensions/functionality/starterkit).
- [Tiptap: Link](https://tiptap.dev/docs/editor/extensions/marks/link).

## Instalação e validação

Após atualizar os arquivos: `npm ci`, `./php artisan migrate --force` e `npm run build`. A migration de formatação foi aplicada no ambiente local. `npm run test:chat` executa as verificações anteriores do Chat e a suíte específica de formatação. Os testes PHP relacionados incluem casos de documento malicioso, limites, versões, espaços e compatibilidade com texto antigo/anexos.

A revisão humana com leitor de tela, dispositivos físicos, zoom real e pessoas com deficiência continua pendente. Testes automatizados e emulação não comprovam conformidade integral WCAG 2.2 AA.
