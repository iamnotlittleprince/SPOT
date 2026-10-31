# Acessibilidade e inclusão no Spot

Revisão inicial: 28/10/2026. Escopo: aplicação React servida pelo Laravel.
Referência: [WCAG 2.2 — referência rápida do W3C](https://www.w3.org/WAI/WCAG22/quickref/).
Meta: nível AA; esta revisão parcial não é uma certificação de conformidade.

## Auditoria inicial e alterações

A inspeção encontrou tradução pt/en/es, formatação local, diversos campos com rótulos e alguns avisos com `role="alert"`/`status`. Também encontrou ausência de atalho à área principal, campos de segurança identificados apenas por placeholder e modais sem gerenciamento completo de teclado.

Implementado:

- Link “Pular para o conteúdo”, traduzido, visível ao receber foco. No dashboard, o destino fica depois da navegação lateral e do cabeçalho.
- Foco visível compartilhado com contorno azul e halo branco, com adaptação a cores forçadas. Alvos de botões, campos e selects recebem piso de 24 px; isso não representa auditoria de espaçamento ou de todos os links.
- Redução de animações e transições por `prefers-reduced-motion`, incluindo pseudo-elementos.
- Hook reutilizável para foco nos modais de novo evento e integrações: foco inicial, ciclo Tab/Shift+Tab, Escape e restauração ao elemento anterior, se ainda existir.
- Modal de evento com papel e título acessíveis e botão de fechar nomeado.
- Página ativa indicada por `aria-current` na navegação lateral.
- Login com autocomplete, estado do botão de mostrar senha e anúncio do erro. Redefinição com autocomplete de nova senha.
- Rótulos visíveis nos três campos de alteração de senha da área de segurança.
- `AGENTS.md` orienta futuras alterações feitas pelo Codex a preservar esses requisitos e documentar evidências.

Não foram acrescentados dados pessoais, campos de gênero, reconhecimento biométrico ou recursos de IA: não atendem a uma necessidade identificada neste escopo. Não foi implementado modo offline, que exige decisões sobre sincronização, autorização e dados locais. Não se adotou como fato a estimativa genérica de que ferramentas detectam 30–40% dos problemas; a cobertura depende da ferramenta e do contexto.

## Validação reproduzível

Com o servidor local disponível na porta 8000:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
node scripts/test-accessibility.mjs
node scripts/test-languages.mjs
```

O teste de acessibilidade usa Playwright e dados simulados. Verifica destino do atalho, foco inicial, ciclo de teclado, Escape, retorno de foco no modal da agenda e preferência de movimento reduzido. Não valida integrações reais, leitores de tela nem toda a aplicação.

## Critérios de aceite para próximas alterações

1. Tarefa essencial realizável com teclado; foco visível e sem bloqueio de saída.
2. Campos com rótulos, instruções e erros associados; resultados assíncronos anunciados.
3. Estados compreensíveis sem depender apenas de cor; contraste medido de texto e controles.
4. Conteúdo utilizável com zoom de 200% e largura de 320 px, sem perda de ações; tabelas podem ter rolagem própria.
5. Traduções preservadas, nomes de pessoas tratados como conteúdo e nenhum dado pessoal desnecessário exigido.
6. Testes pertinentes executados e limitações registradas; incluir avaliação humana com pessoas diversas quando disponível.

## Declaração parcial e limitações conhecidas

O Spot está em processo de melhoria de acessibilidade. Ainda faltam auditoria sistemática de contraste e refluxo, revisão de todos os modais/popovers, gráficos e formulários, avaliação da exposição do fundo dos modais a leitores de tela e testes com NVDA, VoiceOver, JAWS e TalkBack. O hook de foco não torna o fundo `inert` e não deve ser tratado isoladamente como implementação completa de modalidade acessível.

Também estão pendentes testes com usuários com deficiência e avaliação de nomes e endereços em todos os fluxos e regras do backend. O suporte existente a três idiomas não implica suporte a idiomas da direita para a esquerda.

Canal de relato disponível no repositório: [Issues do SPOT](https://github.com/iamnotlittleprince/SPOT/issues). Informe a tela, a tarefa, o navegador e a tecnologia assistiva se desejar; não envie senhas ou dados pessoais. Esse canal requer conta GitHub; um canal alternativo dentro do produto, responsável e prazo de resposta ainda precisam ser definidos pela equipe. Nenhuma mensagem externa foi enviada.

## Resultado desta execução

### Conectar Microsoft em Gerenciar integrações — 28/10/2026

Complemento após nova tentativa: a rota antiga `/auth/microsoft`, usada por abas anteriores à atualização, também inicia vínculo quando já existe usuário autenticado. Teste de regressão do início ao retorno simulado aprovado; suíte Microsoft com 11 testes e 86 asserções aprovada. Também foram verificadas as duas rotas no servidor HTTP da porta 8000, com sessões temporárias: ambas preservam o usuário de destino e o estado OAuth e redirecionam à Microsoft. Essas sessões foram removidas; não foram vinculadas contas reais nem concluído consentimento externo nessa verificação. Alteração restrita ao backend, sem novo ajuste visual.

O botão Microsoft agora inicia uma conexão autenticada, separada do login. O retorno OAuth vincula a conta externa ao mesmo usuário que iniciou o fluxo, permitindo e-mails diferentes e preservando nome e e-mail principal do Spot. Não altera layout, rótulos ou traduções.

Verificações: build aprovado; `node scripts/test-microsoft-integration.mjs` aprovado (abertura do modal, foco inicial, Escape e destino do botão Microsoft); `node scripts/test-accessibility.mjs` aprovado com servidor na porta 8000. Os testes PHP cobrem conexão com e-mail diferente, preservação da identidade, sessão ausente/trocada, conta suspensa, conflitos de vínculo, login posterior e login de visitante sem cadastro; testes de avatar OAuth e provisionamento também passaram. OAuth é simulado nos testes; falta o consentimento real na Microsoft pelo usuário. Não houve revisão humana com leitor de tela, zoom ou pessoas com deficiência.

### Correção da saída da conta — 28/10/2026

O logout confirmado pelo servidor redireciona para `/`, removendo `?home`, e não dispara atualização de dados privados após encerrar a sessão. Não foram alterados layout, textos ou controles.

Validação: build aprovado; `node scripts/test-logout.mjs` aprovado (ausência de evento de atualização no logout, preservação desse evento em outras gravações, saída pelo botão, login sem erro e permanência no login após recarregar). A sessão e a resposta de logout são simuladas no teste de navegador; o destino usa a página real de visitante. `node scripts/test-accessibility.mjs` foi executado com servidor na porta 8000 e falhou na linha 47, na asserção de posicionamento do painel “Ajustes rápidos”, fora dos componentes alterados. Não houve avaliação humana de teclado, leitor de tela, zoom ou telas pequenas nesta correção.

- Build de produção: aprovado.
- Teste de acessibilidade por teclado e movimento reduzido: aprovado.
- Teste de idiomas: 144 verificações, nenhuma falha; o relatório ainda lista textos para revisão em inglês/espanhol (`% concluído`, `Não informado: 1`, `Permissões`, `Gerenciar permissões`).
- `git diff --check`: sem erros de whitespace.
- Não foram executados leitores de tela, axe/Lighthouse, auditoria completa de contraste nem pesquisa com usuários nesta execução.

## Ajustes de leitura

O botão existente de “Ajustes rápidos” no cabeçalho abre o painel com os filtros de tarefas e os controles para mudar a fonte (padrão, Arial, Verdana ou Georgia), diminuir/aumentar o tamanho em passos de 10% entre 90% e 120% e restaurar o padrão. As fontes alternativas são locais, sem download adicional. A preferência é validada e salva no navegador em `spot.reading-settings`; se o armazenamento estiver indisponível, os controles continuam funcionando durante a sessão.

O tamanho usa CSS `zoom` no contêiner da aplicação: texto, campos, espaçamentos e botões crescem juntos para preservar suas proporções e permitir que os breakpoints responsivos se ajustem. Portanto, o controle identifica explicitamente “texto e interface”. O zoom nativo do navegador continua disponível. A família tipográfica é aplicada aos elementos da aplicação, preservando os ícones SVG. O atalho de teclado “Pular para o conteúdo” permanece disponível ao receber foco.

Validação adicional com Playwright: abertura e fechamento com retorno do foco, seleção das quatro fontes, limites do painel dentro da tela em larguras de 1440, 390 e 320 px a 120%, persistência após recarregar e restauração dos padrões. Build e teste passaram. Esta verificação não garante ausência de cortes em todas as telas/dados do sistema; conteúdo longo e cada fluxo ainda exigem revisão visual. O painel de ajustes rápidos é um popover não modal: recebe foco ao abrir, fecha com Escape e devolve o foco ao botão do cabeçalho. O fundo continua interativo. O controle separado flutuante foi removido. O painel tem limite de altura e rolagem própria e o botão do cabeçalho permanece disponível no celular. “Restaurar padrão” restaura tanto a leitura quanto os filtros anteriores.

O posicionamento de “Ajustes rápidos” acompanha o botão do cabeçalho: abre 10 px abaixo, alinhado à esquerda do botão quando houver espaço, e desloca-se apenas o necessário para respeitar as bordas da tela. A posição é recalculada ao redimensionar, rolar e alterar tamanho/fonte. O cálculo considera a escala de leitura; a altura disponível limita o painel com rolagem interna. O teste de navegador verifica esse alinhamento em desktop e a distância abaixo do botão em 1440, 390 e 320 px, incluindo escala de 120%. Build e teste aprovados após a correção.
