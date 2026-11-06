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

### Quebra de título e galeria de erros — 04/11/2026

Corrigida a herança de `white-space: nowrap` no título da tela compartilhada de erros, com cor e alinhamento explícitos e largura mínima dos itens da grade. O título 429 foi verificado também no alerta dentro do aplicativo. Galeria de revisão local em `/preview/errors`, com os 24 estados do catálogo e seleção de tema claro/escuro; a rota existe apenas no ambiente local.

Verificações: build aprovado; 6 testes PHP de erros com 152 asserções; `scripts/test-errors.mjs` e `scripts/test-accessibility.mjs` aprovados; suíte responsiva aprovada com 239 verificações, nomes longos, orientação horizontal e leitura em 120%. As 24 prévias foram verificadas em 320, 568, 900 e 1920 px sem transbordamento horizontal; captura do 429 escuro inspecionada visualmente. Uma execução inicial da suíte responsiva perdeu os assets durante outro build; a repetição após o build final passou. Não foram realizados testes humanos com leitor de tela, pessoas com deficiência ou auditoria completa de contraste; estes resultados automatizados não comprovam conformidade integral com WCAG.

### Configuração de autenticação em dois fatores — 04/11/2026

Fluxo de 2FA com confirmação de senha, QR Code/chave manual, campo rotulado para código do autenticador, cancelamento e oito códigos de recuperação de uso único. A desativação usa campo de senha mascarado. Erros são associados ao campo e anunciados; resultados usam status; códigos e chaves usam `translate="no"`. Controles mantêm foco visível e alvos de 44 px. Traduções pt/en/es preservadas.

O backend impede substituir uma configuração ativa e valida o segundo fator antes de criar a sessão/cookie de login ou token da API. Google e Microsoft encaminham contas com 2FA a uma segunda etapa com prazo de cinco minutos. O consumo dos códigos de recuperação usa transação e bloqueio da conta; configuração e confirmação também são serializadas. Respostas que incluem chave ou códigos de recuperação usam `Cache-Control: no-store`.

Verificações: build aprovado; 27 testes PHP de segurança/login/Google/Microsoft com 203 asserções; `scripts/test-two-factor.mjs` aprovado em 320/1440 px com leitura em 120%, erros, confirmação, recuperação, cancelamento/foco, desativação, controles en/es e desafio OAuth em 320 px; `scripts/test-accessibility.mjs` aprovado. Suíte responsiva com 239 verificações aprovada. Captura de layout de configuração inspecionada com QR/chave sintéticos. Não houve pareamento com aplicativo autenticador em celular real, revisão humana com leitor de tela, auditoria completa de contraste ou testes com pessoas com deficiência. A conta real não foi ativada: isso requer confirmar o código no autenticador do titular. Resultados automatizados não comprovam conformidade integral com WCAG.

### Leitura do QR Code do 2FA — 04/11/2026

Após relato de falha de leitura no celular, ampliada a imagem de configuração de 180 para até 280 px (limitada à largura disponível), com SVG de 320 px, margem de quatro módulos e fundo branco. Incluídas instruções pt/en/es de cadastro manual com chave e tipo TOTP. O QR anterior também foi decodificado automaticamente; não foi possível reproduzir a falha física relatada. A leitura do novo QR com dados sintéticos foi verificada por decodificador independente ZXing em 180, 280 e 320 px, conferindo esquema otpauth, segredo, emissor e período de 30 segundos. Build, teste de interface 2FA e sete testes PHP com 62 asserções aprovados. Pareamento em celular real e leitor de tela permanecem sem avaliação humana.

A suíte responsiva passou na repetição com 239 verificações; a primeira execução excedeu o tempo de carregamento da página inicial. Nenhuma mudança adicional de implementação foi necessária para a repetição.

### Revisão do layout responsivo — 04/11/2026

Cabeçalho compacto reorganizado em duas linhas em até 620 px: nome completo da página/ajuda e busca/perfil. Apenas o ícone decorativo que duplicava o nome da página é retirado nesse tamanho. A navegação recolhível e a busca em linha própria se estendem até 1280 px para evitar cabeçalho apertado em tablets e leitura ampliada. Mantidos idioma/tema nos Ajustes rápidos, foto/menu de perfil e ações com texto. Os botões de tarefas incompletas e ordenação, antes ocultos por regras antigas, voltam a ficar disponíveis no celular.

Painel usa duas colunas em larguras intermediárias, com equipes na largura disponível, e uma coluna em telas pequenas. Formulários de projeto usam duas colunas em tablets e uma no celular. Abas do perfil quebram em linhas, mantendo as três opções visíveis; cabeçalhos e espaçamentos permitem textos longos. Menu do perfil abre abaixo do cabeçalho no celular. Menu de aplicativos usa o estado de abertura e exige ativação explícita em layouts compactos, evitando sobrepor o seletor de páginas após uma troca de tela. O teste reproduziu essa falha em 390 × 420 e passou a verificá-la.

Build, testes de teclado (`scripts/test-accessibility.mjs`), configuração de 2FA e modal de integração Microsoft aprovados. Capturas com dados fictícios inspecionadas em 320, 768, 1101 e 1440 px e leitura em 120%; fluxo de idioma espanhol em 320 px verificado sem transbordamento. Não houve avaliação em dispositivos físicos, leitor de tela, auditoria completa de contraste ou testes com pessoas com deficiência; não se declara conformidade integral com WCAG.

Suíte final `npm run test:responsive` aprovada: 323 verificações, sem erros, entre 320 e 1440 px, orientação vertical/horizontal, nomes longos e leitura em 100%/120%, páginas públicas e aplicação com dados simulados. Incluídas regressões para títulos completos, ações disponíveis, abas visíveis, posição do menu do perfil e abertura explícita dos aplicativos em telas compactas.

### Espaçamento do seletor de assuntos do tutorial — 05/11/2026

O controle “Escolher um assunto” recebeu preenchimento interno de 12 px na vertical e 15 px na horizontal, altura mínima de 44 px e borda completa. A lista aberta mantém recuo lateral e inferior; o ícone não encolhe. Traduções e comportamento nativo de `details` preservados.

Verificações: build, teste do tutorial (729 verificações) e teste de acessibilidade aprovados com servidor na porta 8000. Conferidas larguras de 320, 440 e 1440 px sem transbordamento horizontal, com controle de 44 px; captura em tema escuro a 440 px inspecionada visualmente. Não houve avaliação humana com leitor de tela, dispositivos físicos ou pessoas com deficiência, nem auditoria completa de contraste. O ajuste é local ao tutorial e não altera layouts compartilhados.

### Logotipo da navegação volta ao início — 05/11/2026

O logotipo Computécnica da barra lateral agora é um botão nativo com nome acessível “Ir para o início”, já traduzido em en/es, e foco visível. Aciona a mesma navegação da Home e fecha o menu no celular. Preservadas as dimensões responsivas da imagem, com alvo mínimo de 44 px.

Verificações: build aprovado; navegação Equipes → Home pelo logotipo usando Enter verificada em 1440 e 390 px, incluindo fechamento do menu móvel. Suíte responsiva aprovada com 323 verificações (320–1440 px, orientação vertical/horizontal, nomes longos e leitura em 100%/120%). Teste de acessibilidade aprovado na repetição; primeira execução falhou na asserção de posição do painel Ajustes rápidos, fora do componente alterado. A primeira verificação móvel usou `innerText` no menu já fechado; corrigida a leitura do teste para verificar o conteúdo e estado de navegação. Não houve revisão humana com leitor de tela, dispositivos físicos, pessoas com deficiência ou auditoria completa de contraste.

### Conectar em Apps na Agenda — 05/11/2026

O botão “Conectar em Apps” agora abre o modal existente Gerenciar integrações, permitindo acessar as conexões Google Workspace e Microsoft 365. Antes, a ação apenas rolava a página para o topo. Mantidos o rótulo traduzido e o hook de foco do modal.

Verificações: build e teste de acessibilidade aprovados. Em 390 e 1440 px, abertura pelo teclado, foco inicial dentro do modal, exibição dos dois provedores, fechamento por Escape e retorno do foco ao botão aprovados com dados simulados. Nenhuma conexão OAuth real foi realizada. Não houve avaliação humana com leitor de tela ou pessoas com deficiência; alteração restrita à ação do botão, sem mudança de layout.

### Espaçamento da observação no modal de integrações — 05/11/2026

A observação sobre atalhos do menu Apps agora acompanha o recuo dos cartões e do aviso OAuth, com 16 px de separação antes desse aviso. Margens laterais de 24 px no desktop e 12 px no celular; usada a cor de texto secundário do modal para cada tema. Textos e traduções preservados.

Verificações: build, teste de acessibilidade e teste de integração Microsoft aprovados. Medidos alinhamento lateral e intervalo de 16 px em 320 e 768 px sem transbordamento horizontal; captura escura em 768 px inspecionada. Não houve revisão humana com leitor de tela, pessoas com deficiência ou auditoria completa de contraste. Alteração local ao modal, sem mudança de layouts compartilhados.

### Solicitação de acesso aos relatórios e indicadores — 05/11/2026

Incluído fluxo de pedido com projeto/destinatário/mensagem, cópia ao remetente, mensagens internas ao responsável e ao solicitante, aprovação/recusa e revogação. A alçada é verificada no servidor: gestor só concede nos projetos geridos por ele; gestor-administrador só concede se o solicitante participar ativamente do projeto. Não há autoconcessão nem acesso entre empresas. Tipos: visualizar, personalizar visual/indicadores dos gráficos ou editar progresso/valor contratado/datas e gráficos. Prazos: reunião Teams, 24h, 48h, 72h, semana, mês ou até encerrar/revogar. Projeto encerrado revoga os convites, sem restaurá-los ao reabrir. A decisão registrada é mostrada separadamente do estado efetivo do acesso.

O modal usa `useModalFocus`, rótulos visíveis, nomes de campos explícitos, erros associados aos campos, status/alertas sem mudança desnecessária de foco, controles de pelo menos 44 px e foco visível. Não inclui animações. Textos pt/en/es incluídos e conteúdo de usuários marcado com `translate="no"`. Escala de leitura e limites de viewport seguem as regras de `responsive.css`. Cabeçalho do modal reorganizado em telas estreitas para preservar título e ação Fechar.

Validação: build aprovado; 20 testes PHP relacionados com 176 asserções; teste específico de navegador com 50 verificações, incluindo envio, concessão, seleção de Teams, revogação, diferença entre os três tipos de acesso, gravação e recálculo de dados, teclado, Escape/retorno de foco e idiomas en/es. Verificados 320 px com leitura real em 120% e fonte Verdana e 1440 px; captura móvel inspecionada. Suíte responsiva compartilhada aprovada com 323 verificações (320–1440 px, orientação vertical/horizontal, nomes longos e leitura 100%/120%). Teste de acessibilidade aprovado na repetição isolada; uma execução concorrente falhou na asserção já intermitente de posição do painel Ajustes rápidos, fora do fluxo novo. Teste de idiomas aprovado com 144 verificações, mantendo os itens de revisão preexistentes `% concluído`, `Não informado: 1`, `Permissões` e `Gerenciar permissões`.

As consultas ao Teams foram simuladas nos testes: não houve consentimento real, convite real, envio de mensagem a contas reais ou teste em chamada física. A modalidade de reunião usa os horários agendados na Microsoft, conforme confirmação do usuário, e não a presença ou abertura real da chamada. Se a Microsoft não confirmar a reunião, o acesso por reunião fica indisponível. Os horários são consultados novamente nas requisições protegidas, incluindo cancelamento e reagendamento. Não houve revisão humana com leitor de tela, pessoas com deficiência, dispositivos físicos ou auditoria completa de contraste/cores forçadas; estes resultados não comprovam conformidade integral WCAG. A personalização visual permanece por navegador, como no painel existente. Escopo e limites em `docs/solicitacoes-acesso-indicadores.md`.

### Visual do bloqueio de acesso em Portfólios — 05/11/2026

Substituído o alerta vermelho extenso por um cartão neutro com ícone decorativo, título, explicação e botão de solicitações agrupados. Cabeçalho com cantos completos nesse estado; filtros e aviso de atualização automática aparecem quando há acesso aos relatórios. O cartão se reorganiza em coluna no celular e mantém a ação com texto completo. A atualização dos dados mantém o componente de solicitação montado e evita alternar o estado restrito durante uma consulta, preservando a janela e sua confirmação após enviar um pedido.

Verificações: build aprovado; 50 verificações do fluxo de solicitações aprovadas. Teste de acessibilidade aprovado em repetição isolada; a primeira execução voltou a falhar na asserção intermitente da posição de Ajustes rápidos. Conferidos o botão dentro do cartão, a ausência dos filtros e o limite horizontal em 320/1440 px; capturas claras/escuras inspecionadas, incluindo 320 px com Verdana e leitura em 120%. Traduções existentes preservadas e ícone sem repetição do texto para tecnologias assistivas. Não houve avaliação humana com leitor de tela, dispositivos físicos, pessoas com deficiência ou auditoria completa de contraste e cores forçadas.

Suíte responsiva aprovada na repetição após o build final: 323 verificações, orientação vertical/horizontal, nomes longos e leitura em 100%/120%. A execução anterior perdeu o carregamento dos assets durante um build corretivo; a repetição sem alterações de assets durante o teste passou. `git diff --check` sem erros.

### Integração do botão ao visual anterior de Portfólios — 05/11/2026

Após esclarecimento do usuário, restaurados o painel vermelho original, o cabeçalho e os filtros. O botão de solicitações agora fica dentro do painel, abaixo da explicação, com alinhamento central, foco visível e altura mínima de 44 px. Retiradas as regras do cartão neutro introduzido na execução anterior. Mantida a montagem estável da janela durante a atualização dos relatórios.

Build e teste específico de solicitações aprovados (50 verificações); captura escura desktop inspecionada. Testes de teclado e responsividade executados abaixo. Não houve avaliação humana com leitor de tela, dispositivos físicos, pessoas com deficiência ou auditoria completa de contraste. Traduções e regras de autorização preservadas.

Verificação complementar: botão dentro do painel e ausência de transbordamento horizontal em 320 px com leitura em 120% e Verdana. Suíte responsiva aprovada com 323 verificações; teste de acessibilidade aprovado em repetição isolada, após falha intermitente no posicionamento de Ajustes rápidos na execução concorrente inicial. `git diff --check` sem erros.

### Formulário de solicitação e escolha do gerente — 05/11/2026

Retirado o texto de regras de negócio da abertura e o histórico vazio. A janela apresenta projeto e mensagem; com um gerente elegível, o destinatário é selecionado automaticamente e identificado por nome. Com vários, é exibido o seletor de destinatário. Projeto sem destinatário permanece no catálogo com orientação específica e envio indisponível, sem atribuir permissões ou responsáveis por suposição.

Corrigidos os filtros que exigiam o perfil literal de analista e escondiam projetos sem destinatário. Usuários ativos com perfil na empresa podem solicitar; o catálogo retorna apenas nome/ID dos projetos abertos da empresa atual. Gerentes de projeto e de contas são considerados. O perfil `administrador` utilizado pelo cadastro segue a mesma exigência de participação ativa do solicitante aplicada a `gestor-administrador`. Essa interpretação substitui a exclusão de `administrador` descrita na implementação inicial. Continuam bloqueados autoconcessão, destinatários de outra empresa, concessão fora da alçada, contas inativas e aprovação por terceiros. Não foram alterados cadastros reais de responsáveis, perfis ou participantes.

Verificações: build aprovado; 20 testes PHP relacionados com 178 asserções; 55 verificações do fluxo no navegador, incluindo um gerente, múltiplos gerentes, ausência de destinatário, envio, aprovação, revogação, teclado, idiomas e ausência do texto de regras. Teste de acessibilidade aprovado. Verificados 320 px com leitura em 120% e 1440 px; captura móvel inspecionada. Consulta somente de leitura à base local confirmou cinco projetos no catálogo e um com destinatário elegível para a conta analisada; nenhuma solicitação real foi criada. Traduções pt/en/es incluídas e nomes continuam com `translate="no"`. Sem revisão humana com leitor de tela, dispositivos físicos ou pessoas com deficiência e sem auditoria completa de contraste. Não houve alteração de layout compartilhado nesta correção.

### Gerentes da equipe e participação obrigatória do analista — 05/11/2026

A solicitação agora reconhece integrantes ativos com papel `manager` na equipe, além dos gerentes de projeto/contas e perfis administrativos elegíveis. A perda desse papel, a inativação ou a revogação do integrante impedem novas concessões e o uso de concessões dependentes dessa alçada. Para solicitantes com perfil de analista, a participação ativa e sem revogação no projeto é exigida no catálogo e no servidor, inclusive envio direto por API, aprovação e uso do acesso. Usuários sem participação não recebem nomes de projetos alheios no catálogo de solicitações.

Padronizados os textos visíveis, seletores de equipe/convite, rótulos de perfis, validação de gerente, tutorial e traduções pt/en/es para “gerente”. Migration aplicada para atualizar os nomes dos perfis existentes, preservando seus identificadores, permissões, vínculos e slugs internos de compatibilidade. Não foram atribuídos participantes nem gerentes a contas reais. Consulta somente de leitura confirmou que Implementação retorna dois destinatários para um analista participante; uma conta sem participação não recebe esse projeto.

Verificações: build aprovado; 25 testes PHP com 259 asserções, cobrindo perfil global versus papel na equipe, catálogo restrito, tentativas diretas indevidas, remoção de participação/papel, equipes e gerência. Teste de solicitações com 55 verificações aprovado; seleção de equipe e seleção de gerentes aprovadas em navegador, incluindo celular e leitura em 120%. Testes de idiomas (144 verificações) e acessibilidade aprovados. Itens antigos de revisão en/es continuam registrados pela suíte de idiomas. Sem alteração do layout compartilhado, auditoria completa de contraste, revisão humana com leitor de tela, dispositivos físicos ou pessoas com deficiência nesta correção.

### Janela de envio exclusiva do analista — 05/11/2026

Retirados o histórico e os controles de concessão/revogação da janela aberta em Portfólios. A janela termina no formulário e confirma que a resposta chegará pela caixa de entrada. As mensagens da caixa de entrada abrem somente o pedido selecionado em modo de consulta/análise, sem o formulário de envio. Mantidos teclado, Escape/retorno de foco, anúncios de resultado e traduções pt/en/es.

A elegibilidade do envio agora exige perfil de analista e exclui administrador, gerente administrador e gerente, inclusive com perfis acumulados. O catálogo exige participação ativa com papel de analista no projeto; o servidor repete essa verificação no envio. Uma solicitação pendente deixa de ser aprovável se o solicitante passar a ter perfil de gerente/administrador. O servidor informa se deve haver ação de solicitação em Portfólios. Administradores/gerentes administradores mantêm o acesso geral existente; pedidos de gerentes para projetos de que não participam não são criados neste fluxo. Não foram excluídos nem revogados registros reais antigos.

Verificações: build aprovado; 19 testes PHP relacionados, 179 asserções; 59 verificações de navegador, incluindo envio sem histórico, análise pela caixa de entrada sem formulário de analista, elegibilidade do botão, perfis acumulados e separação dos fluxos. Teste de acessibilidade aprovado; idiomas com 144 verificações sem falhas, mantendo os itens de revisão preexistentes. Verificados 320 px com leitura em 120% e 1440 px, com captura móvel inspecionada. `git diff --check` sem erros. Sem alteração de CSS/layout compartilhado, avaliação humana com leitor de tela, dispositivos físicos ou pessoas com deficiência, nem auditoria completa de contraste nesta correção.

### Equipes em consulta para analistas — 05/11/2026

Analistas sem perfil de gerência/administração veem apenas equipes de projetos em que têm participação ativa, sem revogação. O detalhe não retorna o diretório de usuários da empresa. Integrantes revogados são excluídos da resposta; consultar outra equipe por ID retorna 404 e editar por API é bloqueado, inclusive com overrides gerais de visualização/edição. Perfis de gerência/administração mantêm seus controles existentes.

A consulta usa linhas estáticas de avatar, nome, e-mail e papel, sem botão “+”, marcação de seleção nem seletor de papel. Nome do time é texto, e as instruções descrevem consulta. Busca filtra apenas os participantes recebidos. Conteúdo pessoal permanece com `translate="no"`; textos pt/en/es incluídos. O envio implícito por Enter na busca é impedido em modo de consulta. Ajustado o contraste do nome estático do time no tema escuro.

Verificações iniciais: 21 testes PHP relacionados com 191 asserções, incluindo isolamento de equipes, diretório vazio, integrantes revogados e bloqueio de escrita com overrides; teste específico de navegador com 16 verificações em 320 px/leitura em 120% e 1440 px; fluxo de criação/seleção de equipe para quem pode editar aprovado. Captura escura desktop inspecionada. Não houve avaliação humana com leitor de tela, dispositivos físicos, pessoas com deficiência ou auditoria completa de contraste/cores forçadas. Resultados finais de build, teclado e suíte responsiva registrados a seguir.

Resultados finais: build aprovado; consulta de equipes com 16 verificações aprovada, incluindo Enter na busca sem tentativa de escrita. Suíte responsiva aprovada com 323 verificações (320–1440 px, orientação vertical/horizontal, nomes longos e leitura 100%/120%). Após os últimos ajustes apenas de cor do nome e prevenção de envio implícito, repetidos build e teste específico. Teste de acessibilidade aprovado em execução isolada; execuções concorrentes falharam na asserção intermitente já conhecida do posicionamento de Ajustes rápidos, fora da tela alterada. `git diff --check` sem erros.
