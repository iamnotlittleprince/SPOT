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

### Ordem dos integrantes por papel — 06/11/2026

A lista de integrantes em Equipes segue gerente, analista e convidado, preservando a ordem recebida entre pessoas com o mesmo papel. A edição considera o papel atual do formulário; pessoas sem papel ficam ao final. A busca mantém a ordenação. A ordem é aplicada ao DOM, preservando a sequência de leitura e navegação, sem mudanças de estilos, controles ou traduções.

Verificações: build aprovado; consulta de equipes com 20 verificações, incluindo hierarquia e estabilidade entre analistas em 320 px/leitura em 120% e 1440 px; fluxo de seleção/edição da equipe aprovado em desktop e celular; teste de acessibilidade aprovado. Sem alteração de layout compartilhado. Não houve revisão humana com leitor de tela ou pessoas com deficiência nem auditoria completa de contraste; os testes não comprovam conformidade integral WCAG 2.2 AA.

### Chat entre integrantes e autorização externa — 06/11/2026

Adicionados ícone e texto Chat na navegação lateral e no menu de páginas, sem acesso para convidados, inclusive com perfis acumulados. Conversas individuais exigem empresa atual e participação ativa sem revogação no mesmo time, ou aprovação específica para o par por gerente administrador. A verificação ocorre no servidor para leitura, envio, edição, tradução e histórico. A área de solicitações permite aprovar, recusar e revogar, sem liberar a leitura de conversas alheias ao revisor. Avisos da caixa de entrada abrem a conversa ou as solicitações. Não foram criadas mensagens, solicitações ou concessões para contas reais.

Mensagens admitem edição pelo autor até 24 horas do envio original, sem renovação do prazo, com controle de versão e retenção do texto original e das edições. Sem ação/rota de exclusão. Tradução de mensagens pt/en/es preparada para LibreTranslate configurável no servidor, mantendo o original e identificando o resultado automático. Sem serviço configurado, a tela explica a indisponibilidade; testes usaram respostas simuladas, sem transmissão real de conversas a terceiros. Regras e configuração em `docs/chat.md`.

A interface usa rótulos visíveis, ações com texto completo e altura mínima de 44 px, foco visível, Escape/retorno de foco na edição inline, erros associados aos campos de mensagem/motivo e anúncios de resultado sem deslocar foco. O histórico é uma área de log com acesso por teclado; a rolagem preserva a leitura de mensagens anteriores. Nomes, mensagens, justificativas e versões mantêm `translate="no"`; traduções automáticas recebem o idioma de destino. Sem animações novas. Regras de reflow incluídas em `responsive.css` e ajustes para cores forçadas. Inspeção das capturas claras/escuras identificou e corrigiu o título branco herdado no tema claro.

Verificações: build aprovado sem aviso de tamanho (Chat carregado sob demanda); 22 testes PHP relacionados com 199 asserções; 60 verificações específicas de navegador, incluindo 320 px com Verdana/leitura em 120%, 1440 px, envio, edição, histórico, tradução, solicitações, revogação, notificações, ausência de acesso para convidados, perda de acesso do perfil, idiomas e emulação de cores forçadas/movimento reduzido. Suíte responsiva compartilhada aprovada com 323 verificações (320–1440 px, orientação vertical/horizontal, nomes longos e leitura 100%/120%). Idiomas: 144 verificações sem falhas, preservando os itens de revisão preexistentes `% concluído`, `Não informado: 1`, `Permissões` e `Gerenciar permissões`. Teste geral de acessibilidade aprovado em execução isolada. `git diff --check` sem erros.

Conferidas por cálculo 14 combinações das cores próprias de texto/ações/erros sobre superfícies claras/escuras, todas com contraste mínimo de 4,5:1; esse cálculo não cobre todos os estados/composições do site. Não houve avaliação humana com leitor de tela, dispositivos físicos ou pessoas com deficiência, nem auditoria integral de contraste, zoom real de navegador e cores forçadas em sistemas físicos. Os resultados automatizados não comprovam conformidade integral WCAG 2.2 AA. A tradução real aguarda configuração/homologação do serviço.

### Visual do Chat inspirado no Teams — 06/11/2026

Reorganizado o Chat em painel contínuo, com lista lateral, busca com rótulo visível, avatares/fotos, indicador de conversa selecionada, cabeçalho do destinatário e caixa de composição no rodapé do painel. Mensagens recebidas e enviadas usam alinhamentos distintos, além de superfícies neutras/roxas, preservando identificação textual de autoria. Ações de editar e traduzir continuam com texto completo e área mínima de 44 px de altura. Solicitações, edição por 24 horas, versões e permissões do servidor não foram alteradas. Não foram acrescentados indicadores fictícios de presença ou leitura.

A lista e o histórico têm rolagem própria no desktop; no celular os painéis se empilham e os controles de tradução/envio se reorganizam sem esconder textos das ações. Reflow centralizado em `responsive.css`, incluindo a precedência necessária para o CSS do Chat carregado sob demanda. Busca, idiomas, tema e perfil mantêm os acessos existentes. Rótulos/traduções pt/en/es preservados; nomes e iniciais mantêm `translate="no"`. Sem animações novas; cores forçadas usam cores do sistema e bordas explícitas para seleção/mensagens.

Verificações: build aprovado; 60 verificações do Chat aprovadas, incluindo envio/edição, histórico, tradução, pedidos e notificações, retorno de foco com Escape, pt/en/es, 320 px com Verdana/leitura em 120%, 1440 px e emulação de cores forçadas/movimento reduzido. Capturas clara/escura e móvel inspecionadas. Suíte responsiva compartilhada aprovada com 323 verificações, incluindo orientação vertical/horizontal e nomes longos. Idiomas: 144 verificações sem falhas; itens antigos de revisão en/es permanecem na saída da suíte. Cálculo de 26 combinações de texto/ações sobre a paleta própria, incluindo balões enviados e botão ativo, com contraste mínimo de 5,19:1; esse cálculo não cobre todos os estados/composições do site. `git diff --check` sem erros.

A revisão humana com leitor de tela, zoom real de navegador, dispositivos físicos e pessoas com deficiência continua pendente; emulação e testes automatizados não comprovam conformidade integral WCAG 2.2 AA. A tradução real continua dependente da configuração do serviço.

Validação final após marcar as iniciais dos avatares como conteúdo pessoal: build e teste geral de acessibilidade aprovados em execução isolada, incluindo teclado, Escape/retorno de foco, movimento reduzido e ajustes de leitura.

### Caixa de mensagem compacta — 06/11/2026

Substituída a composição alta por um campo de uma linha inicial, com placeholder pt/en/es, rótulo visível acima e ação Enviar mensagem à direita. Texto de retenção/edição e erros ficam fora da borda do campo. Mantidos redimensionamento vertical, quebras de linha, colagem, envio por botão e limites existentes. No celular o botão se reorganiza abaixo do campo, alinhado à direita, preservando seu texto completo e altura mínima de 44 px.

O foco do textarea agora destaca a borda externa do grupo de composição. A exceção em `accessibility.css` remove somente o contorno/sombra internos do campo `#chat-body`, em conjunto com o contorno externo de 2 px e mudança de borda em `:focus-within`. O botão mantém o foco individual compartilhado. Paleta com contraste já calculado na revisão anterior preservada; o placeholder usa o tom de texto secundário, sem transparência adicional. Sem animações novas e com cores de sistema no modo de cores forçadas.

Build aprovado; 64 verificações específicas do Chat aprovadas, incluindo o foco externo e ausência do contorno interno em 320 px/leitura em 120% e 1440 px. Captura escura desktop inspecionada. Idiomas: 144 verificações sem falhas, mantendo os itens de revisão preexistentes. Não houve revisão humana com leitor de tela, zoom real, dispositivos físicos ou pessoas com deficiência; estes testes não demonstram conformidade integral WCAG 2.2 AA.

Resultados complementares: suíte responsiva aprovada com 323 verificações. Teste geral de acessibilidade aprovado na repetição isolada; a primeira execução falhou na asserção já conhecida/intermitente da posição de Ajustes rápidos (linha 49 do teste), fora da composição do Chat. `git diff --check` sem erros.

### Autoria, ações superiores, anexos e atalhos do Chat — 06/11/2026

Corrigida a ausência do identificador do usuário nos dados iniciais da página e na resposta de atualização do perfil. O servidor também retorna autoria booleana e nome do remetente nas mensagens; a interface trata identificadores numéricos/textuais no caminho de compatibilidade. Enviadas ficam à direita e recebidas à esquerda, com nome/horário e botões Editar/Traduzir acima do balão. Botões compactos mantêm texto visível, nome acessível completo e alvo mínimo de 32 px; demais botões mantêm 44 px. O alinhamento distingue os lados além das cores. Nomes, mensagens e nomes de arquivo ficam com `translate="no"`.

Adicionados anexos privados (até 3 de 2 MB cada), seleção nativa, lista antes do envio com remoção somente de itens ainda não enviados, validação de tipo real/extensão/tamanho/quantidade e download autorizado pela mesma regra da conversa. O caminho privado não é divulgado; não há exclusão/substituição de arquivos enviados. Download revalida o acesso, inclusive após revogação, e apresenta falhas antes de gerar o arquivo local. Campos de mensagem e seleção de arquivos têm instruções/erros associados, com anúncio de falhas. Etiquetas e erros pt/en/es incluídos. Arquivos não são enviados ao tradutor.

Enter envia; Ctrl + Shift + Enter insere uma nova linha/substitui a seleção na posição atual do cursor. Instrução visível associada ao textarea. Mantidos IME, colagem e limite de texto; bloqueados envios por repetição de tecla ou durante envio em andamento. Corrigida uma corrida na posição do cursor, usando inserção síncrona, e a precedência do CSS móvel do campo, para impedir que os novos botões reduzissem sua largura a uma coluna de caracteres. Busca, idiomas, tema, perfil e textos completos das ações permanecem disponíveis. Sem animações novas; paleta e regras de cores forçadas preservadas.

Verificações: build aprovado; 27 testes PHP relacionados, 266 asserções, incluindo identidade após recarregar/atualizar perfil, arquivo sem legenda, edição preservando anexos, downloads por autor/destinatário, isolamento entre empresas/conversas, convidados, revogação, validação de upload e limpeza de arquivos após falha de persistência. Noventa verificações específicas do Chat aprovadas em 320 px/leitura em 120% e 1440 px, incluindo alinhamento, nomes, ações acima, foco, IME, atalhos com digitação imediata após quebra, seleção/remoção antes do envio, upload, download e largura do campo. Capturas claras/escuras e móveis inspecionadas. Suíte responsiva com 323 verificações aprovada; idiomas com 144 verificações sem falhas, preservando os itens de revisão preexistentes en/es. `git diff --check` sem erros.

Os arquivos e mensagens dos testes são fictícios; nenhuma conversa, concessão ou arquivo real de usuário foi criado. Sem revisão humana com leitor de tela, dispositivos físicos, zoom real ou pessoas com deficiência; emulação e resultados automatizados não comprovam conformidade integral WCAG 2.2 AA. A tradução real ainda depende do serviço configurado.

Teste geral de acessibilidade aprovado após o build final, em execução isolada: teclado, Tab/Shift+Tab, Escape/retorno de foco, movimento reduzido e ajustes de leitura. `git diff --check` final sem erros.

### Tradução no próprio balão e redução de elementos visuais — 06/11/2026

A tradução agora ocupa o texto do próprio balão, com indicação curta de tradução automática e alternância Ver original/Ver tradução. Não há mais texto duplicado nem separador de tradução. A alternância é local e mantém o foco no botão; o original salvo, a autoria, as versões e o prazo de edição não são alterados. O formulário de edição abre sempre o texto original; a tradução só aparece quando sua versão corresponde à mensagem atual. Texto traduzido recebe `lang` de destino e permanece com `translate="no"`.

Retiradas bordas permanentes das ações Editar/Traduzir, preservando texto visível, nomes acessíveis, alvos mínimos de 32 px e destaque de foco/hover. A indicação de tradução e o acesso ao histórico ficaram mais compactos. Os avisos de anexos/edição foram agrupados no elemento nativo Limites e edição; atalho de teclado, rótulos e erros continuam visíveis. As descrições permanecem associadas ao campo e ao botão de anexos, inclusive quando os detalhes estão recolhidos. Sem novas animações; paleta e regras de cores forçadas preservadas. Traduções pt/en/es incluídas.

Verificações: build aprovado; 104 verificações específicas do Chat aprovadas em 320 px/leitura em 120% e 1440 px, incluindo tradução inline, alternância sem alterar o original, edição do original, idioma do trecho traduzido, acesso aos limites e fluxos anteriores de envio/anexos/atalhos/permissões. Captura escura desktop da tradução inline inspecionada. Suíte responsiva aprovada com 323 verificações; idiomas com 144 verificações sem falhas e os mesmos itens preexistentes de revisão en/es. `git diff --check` sem erros. A tradução foi simulada nos testes; nenhum texto real de usuário foi enviado a um serviço externo.

Não houve revisão humana com leitor de tela, dispositivos físicos, zoom real ou pessoas com deficiência. Os resultados automatizados não comprovam conformidade integral WCAG 2.2 AA.

Teste geral de acessibilidade aprovado em execução isolada após o build: teclado, Tab/Shift+Tab, Escape/retorno de foco, movimento reduzido e ajustes de leitura.

### Editor de texto com recursos equivalentes aos controles do Teams — 06/11/2026

Integrado Tiptap/ProseMirror à composição e à edição, com barra recolhível por Formatar texto: negrito, itálico, sublinhado, tachado, listas com marcadores/números, destaque, cor e tamanho, citação, link e bloco de código. Mais opções inclui desfazer/refazer, limpar formatação, código em linha, divisória e recuos de listas. A barra usa botões nativos com nomes acessíveis/estado pressionado, seletores com rótulos, alvos de pelo menos 32 px e foco visível. A composição expandida usa a largura do painel e pode rolar verticalmente no desktop; o celular mantém as ações e reorganiza a barra. Os atalhos de envio e as regras de anexos/edição permanecem. Documentação de cada controle e referências primárias em `docs/chat-formatacao.md`.

A janela de links usa `useModalFocus.js`, nome acessível, foco inicial no endereço, contenção de Tab, Escape e retorno ao botão de origem. O portal fora do formulário evita envio acidental ao aplicar um link e aplica a escala/fonte de leitura também fora de `#root`. Texto selecionado mantém seus estilos ao receber um link. Endereços e limite de texto têm erros associados aos campos. Nomes, conteúdo, código e histórico mantêm `translate="no"`; interface pt/en/es incluída. A paleta de texto se adapta ao tema e o destaque força um primeiro plano legível, mesmo dentro de trechos coloridos. Cores forçadas usam cores do sistema. Sem animações novas; o suporte global a movimento reduzido é preservado.

Persistência em documento estruturado opcional, com esquema restrito e renderização por elementos React, sem HTML arbitrário. O servidor gera o texto simples canônico, rejeita nós/atributos/URLs fora do esquema e limita tamanho, caracteres, profundidade e quantidade de nós. Espaços entre trechos e recuos de código são preservados por exceção específica de limpeza de strings no caminho `body_format.*`. Estilos ficam nas revisões e mudanças somente de formatação também respeitam autoria, concorrência e prazo original de 24 horas. Mensagens antigas continuam literais. A tradução usa texto simples e Ver original recupera a formatação preservada. A biblioteca do editor fica em um chunk próprio, carregado no Chat, sem aviso de tamanho no build. Migration de formatação aplicada no ambiente local.

Verificações: build aprovado; 31 testes PHP relacionados, 331 asserções, incluindo esquema malicioso, protocolos/atributos inválidos, limites, multipart com anexo, revisões, espaços, recuos, prazo e compatibilidade com mensagens antigas. Chat: 108 verificações anteriores e 100 específicas de formatação, em 320 px com Verdana/leitura em 120% e 1440 px. Testados todos os controles, envio/edição com estilos, preservação ao inserir link, ciclo de foco/Tab/Escape do diálogo, código literal sem execução, colagem, Ctrl+B/I/U, limite com 5.000 emojis, temas claro/escuro, cores forçadas/movimento reduzido e idiomas pt/en/es. Capturas claras/escuras e móveis inspecionadas. Suíte responsiva compartilhada aprovada com 323 verificações; idiomas com 144 verificações sem falhas, preservando os itens de revisão en/es já registrados. Teste geral de acessibilidade aprovado em execução isolada após o build. `git diff --check` sem erros.

Conferidas por cálculo 25 combinações das novas cores de texto e destaque sobre superfícies claras/escuras, com contraste mínimo de 5,85:1. Esse cálculo não cobre todos os estados/composições do aplicativo. Os testes usam mensagens e arquivos fictícios; não houve tradução real de conteúdo de usuário nem integração de dados com o Teams. Revisão humana com leitor de tela, dispositivos físicos, zoom real e pessoas com deficiência permanece pendente. Testes automatizados e emulação não comprovam conformidade integral WCAG 2.2 AA.

### Imagens no Chat — 06/11/2026

Adicionado botão Enviar imagem com texto visível, ícone decorativo e seleção nativa de JPG/JPEG, PNG e WebP. Prévia antes do envio, descrição opcional de até 500 caracteres com rótulo visível/nome acessível, erros associados e remoção apenas de itens ainda não enviados. A descrição é usada no texto alternativo da imagem; sem descrição, há identificação contextual pelo nome do arquivo. Nomes, descrições e imagens mantêm `translate="no"`; rótulos e falhas pt/en/es incluídos. O fluxo aceita imagem sem legenda ou com texto formatado, mantendo Enter/Ctrl+Shift+Enter, limites de três anexos de 2 MB e retenção de mensagens/anexos.

A imagem enviada aparece no balão e conserva o download do original. Prévia e download verificam autenticação, empresa, participantes e acesso atual da conversa; gerentes administradores não recebem leitura de conversas alheias. A prévia só serve raster validado, com tipo seguro, `nosniff`, política restritiva de conteúdo e cache privado sem armazenamento. Upload valida estrutura real e dimensões de até 8192 × 8192 pixels; arquivos inválidos recebem instrução de correção. Prévia local usa URL de objeto com limpeza ao encerrar/remover a seleção, sem envio a serviços externos. Falha de prévia é identificada por texto, sem retirar o link de download.

Os controles se reorganizam com o editor no celular e a prévia respeita a largura do balão, com proporção preservada. Sem animações/GIF novos; paleta/foco e cores forçadas preservados. Corrigido um foco adiado na abertura da edição rica que podia interferir na seleção imediata do texto. O catálogo de traduções foi separado em chunk próprio para preservar o build sem aviso de tamanho do pacote principal, mantendo o carregamento normal da interface.

Verificações: build aprovado; 33 testes PHP relacionados, 363 asserções, incluindo upload real de imagem de teste, descrição, prévia privada/download, participantes, convidados, terceiros/gerência, outra empresa, revogação, imagens falsas, dimensões e descrição excedidas, documento não servível como imagem e entrada de arquivo inválida sem erro 500. Navegador: 116 verificações do Chat e 100 do editor, incluindo o botão de imagem/seleção nativa, prévia local, descrição/alt, envio sem texto, decodificação da imagem enviada, download disponível e ausência de transbordamento em 320 px/leitura em 120% e 1440 px. Captura móvel inspecionada. Suíte responsiva aprovada com 323 verificações; idiomas com 144 verificações sem falhas e os itens preexistentes de revisão en/es. `git diff --check` sem erros.

A imagem dos testes é fictícia; não houve envio de fotos reais nem análise de conteúdo por serviço externo. A descrição opcional depende de conteúdo adequado informado por quem envia; o nome do arquivo sozinho não descreve necessariamente o conteúdo visual. Revisão humana com leitor de tela, pessoas com deficiência, dispositivos físicos e zoom real continua pendente. Testes automatizados e emulação não comprovam conformidade integral WCAG 2.2 AA.

Teste geral de acessibilidade aprovado após o build, em execução isolada: teclado, Tab/Shift+Tab, Escape/retorno de foco, movimento reduzido e ajustes de leitura.

### Chat ocupando a área útil da janela — 06/11/2026

Adicionada uma classe de estado apenas na página do Chat, com regras em `responsive.css` para preencher a área abaixo dos cabeçalhos no desktop. Retiradas as margens externas do painel e o limite de altura de 1.000 px. A altura considera `100dvh` e a escala real de leitura do `#root`; cabeçalho, navegação, título, busca, perfil e ações existentes permanecem disponíveis. A lista de conversas e o histórico têm rolagem própria. A conversa permite rolagem adicional se a soma dos controles exceder a altura disponível, preservando seu acesso por teclado; a área de solicitações também rola internamente. O menu lateral mantém rolagem para seus itens. Telas estreitas ou baixas preservam o reflow e a rolagem da página. Sem alterações de textos, cores, traduções, animações ou regras de negócio.

Verificações específicas: build aprovado; 134 verificações do Chat e 100 do editor aprovadas. Conferidos os limites esquerdo/direito/inferior do painel em relação à área útil, ausência de transbordamento da página e alcance do botão de imagem por foco em 1440×1000, 1100×720 e 1920×1200, com leitura em 100%/120%. Mantidos os testes móveis de 320 px/leitura em 120%, nomes longos, envio, imagens, anexos, formatação e tradução. Captura escura desktop expandida inspecionada. `git diff --check` sem erros.

Revisão humana com leitor de tela, dispositivos físicos, zoom real e pessoas com deficiência continua pendente. Os resultados automatizados não comprovam conformidade integral WCAG 2.2 AA.

Verificações finais: suíte responsiva compartilhada aprovada com 323 verificações (orientação vertical/horizontal, nomes longos e leitura em 100%/120%); teste geral de acessibilidade aprovado após o build, em execução isolada, incluindo teclado, Tab/Shift+Tab, Escape/retorno de foco e ajustes de leitura.

### Indicadores e avisos de notificação — 07/11/2026

- Sinos superior, lateral e acesso recente exibem indicador de não lidas com nome acessível; o sino superior informa a contagem. O aviso de chegada usa região de status sem mover o foco, permite abrir a caixa ou fechar por botão/Escape e preserva o texto do usuário com translate="no". Incluídas traduções en/es.
- O servidor guarda a última consulta autenticada de notificações por usuário e mantém o corte anterior durante a sessão. A primeira utilização após instalar a mudança estabelece esse histórico sem anunciar todo o acervo antigo. Apenas não lidas, não arquivadas e do próprio usuário entram no aviso. Consultas a cada 15 segundos enquanto a aba está visível e ao retornar a ela; não há push em tempo real.
- O navegador guarda os IDs já anunciados para evitar repetição ao recarregar. Outro navegador pode anunciar novamente. Fechar o aviso não marca a notificação como lida.
- Verificações: build; InboxTest (5 testes, 37 asserções); scripts/test-notifications.mjs (320/1440px, leitura 120%, indicadores, chegada, Escape, repetição, leitura e limites do aviso); script geral de acessibilidade (teclado/foco e ajustes de leitura). Revisão humana com leitor de tela e pessoas com deficiência permanece pendente; estes testes não demonstram conformidade integral WCAG.
- Responsividade compartilhada: npm run test:responsive passou com 323 verificações, 320–1440px, orientação vertical/horizontal, nomes longos e leitura 100%/120%. A primeira tentativa encontrou timeout na carga da fixture durante execução concorrente; repetição após estabilizar o build passou.

### Ícones da barra lateral recolhida — 07/11/2026

- Corrigida regressão introduzida pelo contêiner do indicador de notificações: regras que ocultam rótulos agora selecionam apenas o span direto de texto e excluem notification-icon. Ícones e bolinhas permanecem visíveis no menu recolhido, expandido por ponteiro ou por foco e nos acessos recentes.
- Build e teste de notificações passaram, incluindo novas verificações de visibilidade dos ícones com menu recolhido e expandido pelo teclado. Inspecionada captura em 1440px. Avaliação humana com leitor de tela permanece pendente; não constitui certificação WCAG.
- Verificações adicionais concluídas: teste geral de acessibilidade por teclado/foco e npm run test:responsive (323 verificações, sem erros, 320–1440px, orientações vertical/horizontal e leitura 100%/120%).

### Botão para abrir chat na notificação — 07/11/2026

- Ação alinhada à esquerda com o texto e os metadados, sem a altura mínima vazia do parágrafo; separados texto, botão e tabela com margens de 16/24px. Estilo explícito em temas claro/escuro, altura mínima de 44px e quebra de texto em telas estreitas. Alteração restrita ao detalhe da notificação de chat.
- Build e script geral de acessibilidade passaram. Verificação no navegador de alinhamento, espaçamento, foco e ausência de recorte em 320/1440px, tema escuro e leitura 120%. Revisão humana com leitor de tela permanece pendente; não se declara conformidade integral a partir destes resultados.

### Padrão dos detalhes de notificações — 07/11/2026

- Todos os tipos da caixa de entrada usam espaçamento natural do texto, sem a reserva vazia de 100px. Ações de acesso a projeto e chat compartilham alinhamento, margens, altura mínima de 44px, tipografia, borda e cores nos temas claro/escuro. Seletores restritos à caixa de entrada preservam o botão de acesso usado em outras páginas e seu modal.
- Build, git diff --check e script geral de acessibilidade passaram. Verificação no navegador de 24 combinações de detalhes (tarefa, documento, menção, atualização, solicitação de acesso e chat), 320/1440px, temas claro/escuro e leitura 120%: ações visualmente idênticas, alinhamento, espaçamento, foco e limites horizontais. Revisão humana com leitor de tela e pessoas com deficiência segue pendente; não se declara conformidade integral WCAG.

### Cor, reunião e vínculos de eventos — 07/11/2026

- Formulário Novo evento inclui seletor de seis cores com nomes, link HTTP/HTTPS opcional e seletores de projeto/tarefa opcionais. Escolher tarefa preenche seu projeto; trocar projeto limpa a tarefa e filtra as opções. Cor aparece como faixa no calendário e na lista móvel, com nome textual nos detalhes (sem substituir título, hora ou origem). Link e vínculos são exibidos nos detalhes; conteúdo do usuário marcado translate="no". Traduções pt/en/es preservadas.
- Servidor valida formato da cor/link, empresa e permissões de projeto/tarefa e coerência entre ambos. O vínculo não concede permissões nem compartilha o evento. Eventos de provedores conectados recebem o link na descrição e mantêm cor/vínculos locais por ID externo; reuniões Teams continuam com geração de link quando não há link informado. Cores/vínculos não alteram o cadastro remoto de projetos/tarefas nem são sincronizados como categorias do provedor.
- Novos erros associados aos campos; modal mantém useModalFocus e botão bloqueia envios duplicados. Build, CalendarTest (5 testes/41 asserções, incluindo isolamento e sincronização Google simulada) e scripts/test-calendar-details.mjs passaram: criação, persistência simulada, cor, link, filtros e vínculo automático, Escape/retorno de foco, 320/1440px e leitura 120%. Fluxos reais Google/Microsoft dependem de contas conectadas e não foram exercitados; revisão humana com leitor de tela/pessoas com deficiência permanece pendente. Não se declara conformidade integral WCAG.
- Regressões adicionais: CalendarIntegrationTest passou (6 testes/28 asserções com provedores simulados), script geral de acessibilidade passou após o build final e npm run test:responsive passou (323 verificações, sem erros; telas estreitas, horizontal, nomes longos e leitura 120%).

### Seleção de várias agendas — 07/11/2026

- Campo Agenda substituído por grupo semântico de caixas nativas (fieldset/legend), rótulos visíveis, alvos de 44px, seleção por teclado, erro anunciado e associado ao grupo. Spot marcado inicialmente; Google e Microsoft exigem conexão em Apps. Outlook/Teams usam a mesma agenda: ambos selecionados geram uma única reunião Teams no Outlook. Traduções pt/en/es adicionadas.
- Envio por lote mantém validação de conteúdo/permissões anterior. Chave por formulário, registro persistente por destino e trava por usuário/lote impedem repetir destinos já gravados, inclusive em novas tentativas. O formulário apresenta resultado por agenda e conserva dados da tentativa; falhas explicitamente rejeitadas podem ser repetidas, resultados remotos incertos não são reenviados automaticamente. Uma reunião Teams criada antes das demais cópias fornece seu link a elas quando o usuário não informou outro link. Reabrir Novo evento inicia outra gravação independente.
- Build e 16 testes PHP/109 asserções passaram: calendários anteriores, seleção múltipla, deduplicação Outlook/Teams, repetição após falha parcial, isolamento por usuário, recusa de conteúdo alterado na mesma chave e resultado remoto incerto. Scripts test-calendar-details.mjs e test-calendar-multiple.mjs passaram em 320/1440px, leitura 120%: seleção por teclado, obrigatoriedade, envio de várias opções, status por destino, manutenção do conteúdo, repetição e retorno ao estado inicial. Contas Google/Microsoft reais não foram usadas; os provedores foram simulados. Revisão humana com leitor de tela/pessoas com deficiência permanece pendente, sem declaração de conformidade integral WCAG.
- Verificações finais: script geral de acessibilidade aprovado; npm run test:responsive aprovado com 323 verificações e nenhum erro (320–1440px, vertical/horizontal, nomes longos e leitura 100%/120%).

### Edição de eventos — 07/11/2026

- Detalhes do evento oferecem Editar evento para registros criados pelo Spot. Modal reutiliza campos de criação com valores atuais (incluindo segundos e fuso do perfil), título próprio, erros associados, foco contido, Escape e retorno ao botão de edição. Agenda fica fixa: alterações afetam somente a cópia selecionada; outras cópias permanecem independentes. Novo evento continua vazio após uma edição.
- PATCH valida proprietário, datas, cor, URL e acesso aos vínculos. Atualiza o registro existente; Google/Outlook/Teams criados pelo Spot recebem PATCH no identificador remoto, sem novo POST. Recusa do provedor preserva dados locais. Descrição canônica e identificador de edição são devolvidos na agenda. Em reuniões Microsoft online, mudanças da descrição são inseridas em seção identificada, preservando o corpo original e as instruções de ingresso; sucessivas alterações substituem essa seção. Conteúdo anterior do provedor é preservado e pode continuar visível na agenda externa. Eventos importados sem registro local oferecem edição na agenda de origem via htmlLink/webLink, separado do link de ingresso da reunião.
- Documentação primária consultada: Google Calendar Events patch (https://developers.google.com/workspace/calendar/api/v3/reference/events/patch) e Microsoft Graph Update event (https://learn.microsoft.com/en-us/graph/api/event-update?view=graph-rest-1.0), em especial preservação do corpo das reuniões online.
- Build e conjunto PHP de agenda aprovados (21 testes/134 asserções); CalendarEditTest repetido após ajuste final de preservação de HTML. Scripts test-calendar-edit.mjs, test-calendar-details.mjs e test-calendar-multiple.mjs passaram (320/1440px, leitura 120%; edição em tema escuro, valores/fuso, validação, PATCH, manutenção de vínculos, Escape e retorno do foco). Provedores simulados, sem testes em contas reais; revisão humana com leitor de tela/pessoas com deficiência permanece pendente. Não se declara conformidade integral WCAG.
- Verificações finais de edição: script geral de acessibilidade aprovado e npm run test:responsive aprovado com 323 verificações, sem erros (320–1440px, vertical/horizontal, nomes longos e leitura 100%/120%).

### Exclusão de eventos — 07/11/2026

- Detalhes dos eventos criados pelo Spot oferecem Excluir evento, com confirmação em alertdialog nomeado, nome do evento e agenda visíveis, texto explicando a exclusão apenas da cópia selecionada, foco inicial em Cancelar, contenção de Tab/Shift+Tab e Escape/retorno de foco via useModalFocus. Após excluir e remover o acionador, o foco segue para Novo evento. Erros anunciados e botão bloqueado durante o envio; respostas tardias não movem o foco para fora de outro modal. Traduções pt/en/es incluídas.
- DELETE valida proprietário, remove da agenda Google/Microsoft/Teams quando conectado e somente depois elimina o registro local. Recusa ou falha de conexão mantém o registro local; ausência remota 404/410 permite concluir a remoção local numa repetição. Outras cópias, projetos e tarefas são preservados. Eventos importados continuam oferecendo acesso à agenda de origem, agora com rótulo Editar ou excluir na agenda de origem.
- Build, git diff --check e conjunto PHP da agenda passaram (25 testes/157 asserções). Verificações específicas de exclusão: autorização, preservação de outras cópias, DELETE remoto com ID codificado, falhas/desconexão e evento já removido. Scripts test-calendar-delete.mjs e test-calendar-edit.mjs passaram em 320/1440px, tema escuro e leitura 120%: confirmação, Cancelar/Escape sem requisição, foco inicial/retorno, Tab, erro e repetição, manutenção de outras cópias e edição. Provedores foram simulados; contas reais e revisão humana com leitor de tela/pessoas com deficiência permanecem pendentes. Não se declara conformidade integral WCAG a partir destas verificações.
- Verificações finais: script geral de acessibilidade aprovado; npm run test:responsive aprovado com 323 verificações sem erros (320–1440px, vertical/horizontal, nomes longos e leitura 100%/120%).

### Espaçamento dos detalhes do evento — 07/11/2026

- Painel usa intervalos consistentes entre origem, título, descrição, cor, horários, link e ações. Rótulos internos deixaram de herdar margens de linhas completas; cor e texto ficam alinhados, com pontuação agrupada. Botões Editar/Excluir compartilham tipografia, altura mínima de 44px e largura, em duas colunas no desktop e uma coluna em telas estreitas.
- Build e scripts de edição/exclusão passaram. Verificação de geometria e captura inspecionada em tema escuro, 320/1440px e leitura 120%: texto da cor alinhado, botões da mesma largura, sem recorte do painel. Script de detalhes de evento e verificação geral de acessibilidade executados após o ajuste final. Alteração restrita ao painel do evento; revisão humana com leitor de tela/pessoas com deficiência permanece pendente, sem declaração de conformidade integral WCAG.

### Alterar agendas durante a edição — 07/11/2026

- Substitui a restrição anterior de manter a agenda fixa. Caixas podem ser marcadas/desmarcadas na edição; o grupo mostra as cópias vinculadas já existentes. Agendas Google/Microsoft novas exigem conexão em Apps. Cópias originalmente criadas no mesmo envio são agrupadas, inclusive por migração dos registros antigos; eventos independentes permanecem separados.
- Agendas mantidas recebem atualização; novas recebem uma cópia; desmarcadas são removidas somente depois de todas as agendas desejadas terem sido salvas. Ao menos uma seleção é obrigatória. Texto visível explica remoções e o comportamento Outlook/Teams (mesma agenda; reunião online existente é preservada). Microsoft existente pode ser promovido a reunião Teams sem criar outro registro.
- Operação persistente por usuário/tentativa registra cada etapa, protege o grupo contra processamento concorrente e mantém repetição segura após remoção do registro original. Sucessos não são repetidos; criações remotas incertas não são reenviadas automaticamente. Falhas apresentam resultado por agenda e mantêm o formulário da tentativa. Retomar uma criação antiga reutiliza uma cópia já adicionada por edição, sem sobrescrever suas alterações. As cópias desmarcadas só são removidas ao salvar; fechar/cancelar antes do envio não altera destinos.
- Build e testes PHP da agenda passaram (incluindo adição, atualização, remoção, autorização, seleção vazia, preservação da origem em falhas, repetição após remoção da origem, Teams/Outlook e proteção contra duplicação entre criação/edição). Scripts test-calendar-agendas.mjs, test-calendar-edit.mjs, test-calendar-multiple.mjs e test-calendar-details.mjs passaram em 320/1440px e leitura 120%; exclusão repetida após tornar a espera pelo foco compatível com requestAnimationFrame também passou. Script geral de acessibilidade aprovado. Provedores simulados; contas reais e revisão humana com leitor de tela/pessoas com deficiência permanecem pendentes, sem declaração de conformidade integral WCAG.
- Resultado final: 32 testes PHP/220 asserções aprovados; npm run test:responsive aprovado com 323 verificações sem erros (320–1440px, vertical/horizontal, nomes longos e leitura 100%/120%).

### Visão do projeto com tarefas e documentos — 09/11/2026

- Detalhes do projeto reorganizados com cabeçalho (cliente/status), cartões de tarefas/documentos, espaçamento consistente para configuração, despesas e histórico; duas colunas no desktop e uma no celular. Tarefas exibem contagem, progresso textual/nativo, status, responsável, prazo e descrição expansível com controle nativo. Documentos exibem nome, tamanho/data locais e download com nome acessível. Cada seção tem busca própria; estados de carregamento/erro/repetição, vazio e anúncio de resultados são distintos. Textos pt/en/es e translate="no" para conteúdo de usuários.
- Novo GET projects/{project}/resources verifica empresa e acesso ao projeto, aplica a visibilidade de tarefas próprias/participação ou tasks.view_others e mantém a política existente de documentos do próprio usuário. Somente metadados necessários são enviados; caminhos internos, e-mails e valores financeiros não fazem parte da resposta. Downloads usam a rota existente com validação do proprietário. Contagens refletem os itens disponíveis para a pessoa, não conteúdos privados de outros usuários. Respostas de projetos anteriores são descartadas e atualizações automáticas respeitam a visibilidade da aba.
- Build, git diff --check e testes PHP ProjectResourcesTest/ProjectConfigurationTest/DocumentAssignmentTest passaram (8 testes/90 asserções): isolamento por projeto/empresa/usuário, participação em tarefas, visibilidade de gerente/analista, privacidade dos documentos e download autorizado. Test-management.mjs passou com criação/edição/finalização/reabertura, tarifas e cadastro de clientes preservados. Fixture compartilhada ganhou resposta vazia para o endpoint novo.
- Test-project-resources.mjs passou em 320/1440px, temas claro/escuro e leitura 120%: nomes longos, buscas, descrição por teclado, download acessível, erro/repetição, troca de projeto e limites horizontais. Captura em tema escuro inspecionada. Npm run test:responsive passou com 323 verificações, incluindo orientação horizontal e leitura 120%. Script geral de acessibilidade passou após o build final; a primeira execução concorrente apresentou a oscilação já observada no posicionamento de ajustes de leitura (linha 47), e a execução isolada passou.
- Limitações: não houve revisão humana com leitor de tela ou pessoas com deficiência; as verificações automatizadas não demonstram conformidade integral WCAG. Documentos permanecem privados ao proprietário conforme o comportamento atual do aplicativo; esta mudança não concede compartilhamento adicional.

### Alinhamento de Novo projeto e Portfólios — 09/11/2026

- As duas telas usam as mesmas margens de conteúdo e espaçamento superior em desktop/celular. Portfólios sem acesso agora usa o cabeçalho compartilhado da área de trabalho; filtros sem dados não aparecem enquanto o acesso está restrito. O fluxo autorizado mantém indicadores e filtros. Painéis de restrição compartilham posição inicial, largura, altura mínima no desktop, tipografia e agrupamento de ícone/mensagem; Solicitações de acesso permanece disponível conforme a permissão existente.
- Build e git diff --check passaram. Medições no navegador em 320/1440px e leitura 100%/120% confirmaram margens, posição inicial e largura iguais entre os dois painéis, altura igual no desktop e ausência de transbordamento horizontal; conteúdo pode aumentar a altura no celular para evitar recortes. Captura do Portfólios restrito em tema escuro inspecionada.
- Script test-project-access.mjs passou (59 verificações, sem erros), incluindo solicitação, teclado/modal e acessos autorizado/visual/dados; script geral de acessibilidade passou. Revisão humana com leitor de tela/pessoas com deficiência permanece pendente, sem declaração de conformidade integral WCAG.
- Responsividade compartilhada: npm run test:responsive passou com 323 verificações, sem erros, incluindo 320–1440px, vertical/horizontal, nomes longos e leitura 100%/120%.

### Realce de foco dos campos com ícone — 09/11/2026

- Indicador de foco dos inputs em .input-wrap transferido para o contêiner completo (ícone + campo). Removido somente o contorno interno duplicado; preservados cursor, seleção nativa, digitação/colagem e foco próprio dos botões de senha. Contorno externo acompanha as bordas arredondadas, usa azul visível em fundos claros/escuros e a cor Highlight no modo de cores forçadas, inclusive com tema escuro.
- Build e git diff --check passaram. No navegador, verificadas seleção/substituição por Ctrl+A, foco externo único, foco do botão de senha, cores forçadas e ausência de recorte horizontal em 320/1440px, leitura 120% e temas claro/escuro. Cor de realce do sistema comparada ao contorno computado no tema escuro; captura do campo de login inspecionada.
- Npm run test:responsive passou com 323 verificações, sem erros. Script geral de acessibilidade aprovado após o build final; execução concorrente anterior apresentou novamente a oscilação no posicionamento dos ajustes de leitura (linha 47), e a repetição isolada passou. Revisão humana com leitor de tela/pessoas com deficiência permanece pendente, sem declaração de conformidade integral WCAG.

### E-mail de recuperação e acessibilidade antes do login — 09/11/2026

- E-mail de redefinição recebeu template próprio com identidade Spot, título/ação claros, validade conforme configuração do broker, orientações de segurança, link alternativo e versão em texto simples. HTML usa tabelas de apresentação, textos legíveis, CTA amplo, URLs quebráveis e lang adequado, sem depender de imagens externas. Assunto e conteúdo disponíveis em pt/en/es. Recuperação continua usando token nativo; URL carrega o idioma para a tela de redefinição. Mensagens de confirmação/erro/sucesso das telas de recuperação foram traduzidas, e token inválido/expirado retorna orientação para pedir outro link.
- Seleção explícita no pedido determina o idioma daquele e-mail; na ausência dela usa a preferência da conta. Pedidos anônimos não modificam o idioma cadastrado. Preferência da conta é salva somente após autenticação válida ou alteração autenticada de idioma; a API só altera o usuário atual e valida as três opções. Campo preferred_locale incluído no perfil/sessão e migração aplicada. Resposta para conta inexistente permanece genérica.
- Telas públicas de login, recuperação, redefinição, primeiro acesso, convite e verificação oferecem Idioma e Acessibilidade antes de autenticar. Modal reutiliza useModalFocus (nome, foco inicial, Tab/Shift+Tab, Escape/retorno), com escala 90–120%, quatro fontes, tema claro/escuro/sistema, contraste alto na área pública, redução de movimento e links sublinhados. Preferências de leitura ficam no navegador; o zoom nativo continua disponível. Movimento do sistema e cores forçadas são preservados. Tema claro teve contraste e quebra do título corrigidos no celular; nomes das ações permanecem visíveis.
- Build, git diff --check e PHP LocalizedResetMailTest/PasswordResetTest/SessionApiAuthTest/ProfileTest passaram (16 testes/82 asserções). Cobertura: língua solicitada/preferida, não alteração anônima de conta, autenticação antes de persistir preferência, isolamento, enumeração genérica, HTML/texto/link/validade e token utilizado apenas uma vez. Scripts públicos verificam idioma e payload, persistência, fonte/tamanho, tema/contraste/movimento/links, sucesso em espanhol, teclado/modal, 320/1440px e prévias dos três e-mails em 320/700px.
- Npm run test:responsive passou com 323 verificações; script de idiomas passou com 144 verificações e nenhuma falha. Permanecem os oito itens de revisão preexistentes de en/es: % concluído, Não informado: 1, Permissões e Gerenciar permissões. Verificação geral de acessibilidade passou em repetição isolada após uma oscilação do posicionamento de ajustes rápidos (linha 49), já observada em execuções anteriores. Prévia do e-mail e login móvel inspecionadas visualmente.
- Limitações: os testes usam notificações/e-mails simulados; não houve envio real nem revisão em Gmail/Outlook/leitor de tela ou com pessoas com deficiência. O e-mail novo se aplica a recuperação de senha; os demais modelos transacionais não foram redesenhados. Não se declara conformidade integral WCAG com base nestas verificações.

### Idiomas do login no padrão do sistema — 09/11/2026

- Seletor de idioma público substituído pelo mesmo componente LanguageSwitcher usado no cabeçalho autenticado, com bandeiras pt/en/es, nomes acessíveis, title e aria-pressed. A ação por botão nativo funciona com teclado e mantém idioma/persistência/envio de locale existentes. O componente compartilhado evita divergência visual entre login e sistema.
- Controles públicos ficaram compactos, alinhados à largura do cartão e sobre o fundo da página, substituindo a faixa azul de largura total. No celular o grupo de idiomas e o botão Acessibilidade se organizam em linhas sem ocultar o texto da ação. Grupo com contraste alto/cores forçadas conserva indicação de seleção. Painel/modal de leitura permanece disponível.
- Build, git diff --check, test-public-accessibility.mjs (incluindo seleção por Espaço, persistência, recuperação e modal), script geral de acessibilidade, 144 verificações de idiomas e 323 verificações de responsividade passaram. Telas de login inspecionadas em 320/1440px; testes de leitura 120% e telas em orientação horizontal permanecem cobertos pela suíte. Prévia de e-mails no teste agora é gerada com conta/token sintéticos por scripts/render-reset-mail-preview.php, sem depender de arquivos temporários preparados manualmente e sem enviar e-mails.
- Limitações mantidas: revisão humana com leitor de tela/pessoas com deficiência pendente; os testes automatizados não demonstram conformidade integral WCAG. Permanecem os oito itens de revisão de traduções preexistentes citados na seção anterior.

### Controles de acesso dentro do cartão — 09/11/2026

- Idioma e Acessibilidade movidos para dentro do painel de formulário do cartão Spot, antes do título, com a mesma área de conteúdo, fundo herdado e separador discreto. Removido o espaço reservado para a barra externa; mantidas bandeiras, seleção ativa, tradução e preferências. Aplicado também aos cartões de recuperação, redefinição, primeiro acesso, verificação e convite.
- Modal de acessibilidade renderizado por portal no root para não ser recortado pelo overflow/transform do cartão. Preservados contexto visual de contraste/tema, nome do diálogo, foco inicial, contenção de Tab, Escape e retorno ao botão dentro do cartão. Link Primeiro acesso recebeu cor legível no tema escuro.
- Build, git diff --check, test-public-accessibility.mjs e script geral de acessibilidade passaram; testes verificam que os controles pertencem ao cartão e ficam dentro de seus limites e que o diálogo usa o portal. Npm run test:responsive aprovado com 323 verificações sem erros (telas estreitas, horizontal, nomes longos e leitura 120%). Capturas em 320/1920px no tema escuro inspecionadas, mantendo a identidade visual do Spot.
- Revisão humana com leitor de tela/pessoas com deficiência permanece pendente, sem declaração de conformidade integral WCAG a partir das verificações automatizadas.

### Separação do link Primeiro acesso — 09/11/2026

- Botões Google/Microsoft e link Primeiro acesso agrupados no mesmo fluxo visual para evitar sobreposição causada pelos deslocamentos relativos do desktop. Link centralizado em linha própria, com intervalo de 16px e alvo de ao menos 44px, preservando o visual do Spot e o foco por teclado.
- Build, git diff --check, teste de acesso público e script geral de acessibilidade aprovados. Medições em 320/1440/1920px e leitura 100%/120% confirmaram separação, centralização, alvo, foco e ausência de transbordamento horizontal. Captura do grupo em tema escuro inspecionada. Revisão humana com leitor de tela/pessoas com deficiência permanece pendente; não se declara conformidade integral WCAG.
- Npm run test:responsive aprovado com 323 verificações sem erros, incluindo orientação horizontal, telas estreitas, nomes longos e leitura 120%.

## Remoção da solicitação pública de ativação — 09/11/2026

Removidos os links “Primeiro acesso” dos logins principal e de estoque e o endpoint público de solicitação de link. A página com token continua permitindo ativar a conta provisionada pelo administrador. As URLs sem token exibem o login, sem formulário de solicitação.

Verificações: build aprovado; 11 testes de AdministrativeProvisioningTest aprovados (72 asserções), incluindo indisponibilidade da geração pública e ativação administrativa; navegador automatizado confirmou ausência dos links e do botão de solicitação nas entradas públicas e presença da ativação com token. O teste `scripts/test-accessibility.mjs` falhou na linha 47, na posição de um menu fora deste fluxo. Não foi feita revisão humana de leitor de tela, zoom ou navegação completa por teclado nesta alteração; estes resultados não demonstram conformidade WCAG integral.

## Layout da recuperação por idioma — 09/11/2026

A tela de recuperação usa as mesmas linhas para idioma/acessibilidade em pt/en/es, reserva espaço para títulos, descrições e respostas traduzidas, e permite quebra de texto nos botões. A ilustração acompanha o cartão sem determinar sua altura. As regras ficam em `resources/css/responsive.css`, limitadas a `.forgot-password-card`.

Build, teste de acessibilidade por teclado/modal e teste de acessibilidade pública aprovados. `npm run test:responsive` passou com 323 verificações em 320–1440 px, orientação horizontal/vertical e leitura em 100%/120%, incluindo dados longos no dashboard. Revisão visual pontual da recuperação em desktop; não houve revisão humana com leitor de tela nem avaliação integral de contraste nesta mudança, que preserva a paleta existente. Ampliações e fontes além dos cenários testados podem aumentar os blocos para manter o texto legível.

`node scripts/test-recovery-layout.mjs` aprovado: compara posição e dimensões do cartão, controles, cabeçalho, formulário, retorno e ilustração nos três idiomas, com mensagem de sucesso e e-mail longo; cinco tamanhos de 320 a 1920 px, incluindo horizontal, em 100% e 120%, sem rolagem horizontal da página.

### Mais espaçamento na recuperação — 09/11/2026

Aumentados os intervalos entre idioma e acessibilidade (24 px), controles e cabeçalho (36 px), ícone e título (32 px), título e descrição (24 px), campo e envio (32 px), além da mensagem, retorno e marca. As três traduções compartilham as mesmas regras; mantém-se o crescimento vertical e a rolagem em telas baixas. Build aprovado. Sem nova revisão humana de leitor de tela ou contraste; alteração restrita a espaçamento.

Verificação deste ajuste: comparação pt/en/es em cinco tamanhos e leitura 100%/120% aprovada; suíte responsiva aprovada (323 verificações); inspeção visual pontual em desktop. O teste geral de acessibilidade falhou na linha 47, na posição de um menu fora da recuperação. Não se declara conformidade integral.

### Reequilíbrio da recuperação — 09/11/2026

Substituídas as regras acumuladas de espaçamento por um bloco único: coluna do formulário mais larga, controles em uma linha no desktop, margens de 16–28 px e formulário em grid com intervalo de 20 px. Título de 28 px e descrição próximos; removidas as reservas excessivas de linhas no desktop. Em telas estreitas, controles empilhados e reserva de texto compatível com pt/en/es e leitura ampliada. A ilustração acompanha a altura do conteúdo. Inspeção visual pontual em espanhol, tema escuro, 1920 × 968; sem revisão humana com leitor de tela nesta alteração.

Validação do reequilíbrio: build, comparação pt/en/es em cinco tamanhos e leitura 120%, teclado/modal e 323 verificações de responsividade aprovados. A primeira execução responsiva falhou ao carregar a fixture do dashboard durante rebuild; repetida após estabilizar os arquivos gerados, passou. Inspeção visual adicional em 375 px. Nenhuma certificação WCAG é inferida desses resultados.

## Padronização dos e-mails — 09/11/2026

Ativação, convites, notificações imediatas e resumos passam a compartilhar o layout da redefinição de senha, com versões em texto simples, pt/en/es, links alternativos, HTML escapado e tabelas de apresentação. Build e 36 verificações de prévias em 320/700 px aprovados. Escopo, comandos e limites em `docs/padrao-emails.md`; sem envio real nem revisão em clientes de e-mail/leitor de tela. Os testes de teclado/modal da aplicação não se aplicam a esta alteração exclusiva de e-mails.

## Bandeiras na tradução do chat — 10/11/2026

Controle nativo select substituído pelo grupo de três botões já usado para idiomas. Rótulo visível “Traduzir para” associado por `aria-labelledby`; botões nomeados, `aria-pressed`, foco visível e alvo de 44 px. Estado marcado por contorno, inclusive em cores forçadas. A seleção não altera o idioma da aplicação.

Build e teste principal do chat aprovados (142 verificações), cobrindo seleção com Space e tradução para espanhol em 320/1440 px, com leitura 120% e nome longo no cenário estreito. Testes gerais de teclado/modal e acessibilidade pública aprovados. Inspeção visual pontual da captura do chat em tema escuro no desktop. O teste adicional de formatação de mensagens falhou repetidamente em `scripts/test-chat-formatting.mjs:72` (lista esperava dois itens, encontrou zero); essa verificação não cobre o seletor de idiomas. Sem revisão humana com leitor de tela nesta alteração; não se declara conformidade integral.

Suíte responsiva também aprovada: 323 verificações entre 320–1440 px, orientações vertical/horizontal e leitura 100%/120%.

### Bandeiras no cabeçalho da conversa — 10/11/2026

Movido o grupo para a direita do cabeçalho, oposto à identidade do contato, com quebra de linha em telas estreitas. Rótulo e nomes acessíveis preservados. Build, teste do chat (146 verificações, incluindo localização do grupo, teclado, tradução e 320 px com leitura 120%/nome longo) e teste geral de teclado/modal aprovados. Inspeção visual das capturas de 320/1440 px em tema escuro; sem revisão humana com leitor de tela. Alteração restrita ao cabeçalho do chat, sem mudança de layout compartilhado.

### Cabeçalho mais baixo no chat — 10/11/2026

Retirado o texto visual “Traduzir para”, mantendo o nome do grupo para leitores de tela. Padding vertical do cabeçalho reduzido para 10 px em desktop/celular; alvos das bandeiras preservados em 44 px. Mudança restrita ao chat, sem afetar navegação compartilhada. Sem nova revisão humana de leitor de tela ou contraste.

Build e 146 verificações do chat aprovados (incluindo tradução por teclado e tela estreita em 120%). O teste geral de acessibilidade falhou na linha 47, na verificação de posição de um menu fora do chat, já observada anteriormente. Não se declara conformidade integral.

## Menu de solicitação de chat — 10/11/2026

Menu `details`/`summary` na aba de solicitações, sem modal, com select e textarea nativos, rótulos visíveis, estado ocupado, erro de motivo associado e resultado assíncrono anunciado. Motivo pessoal marcado `translate="no"`; strings pt/en/es. Lista omite contatos já autorizados ou pendentes, orientando quando não há revisor ou candidatos.

Validação: build aprovado; `ChatTest` com 23 testes/314 asserções aprovado; navegador do chat com 158 verificações aprovado (menu por Space, pessoa/motivo, envio simulado, pendência, ausência de revisor, aprovação/revogação e pt/en/es, em 320/1440 px, 120% e nome longo no cenário estreito). Teste geral de teclado/modal aprovado. Idiomas: 144 verificações, sem falhas, com oito apontamentos de revisão preexistentes em outras áreas. Inspeção visual das capturas desktop/claro e celular/escuro; sem novas solicitações reais, revisão humana de leitor de tela, dispositivos físicos ou avaliação integral de contraste. Nenhuma mudança de layout compartilhado; não se declara conformidade integral com WCAG.

### Pedido visível e acompanhamento — 10/11/2026

O formulário da aba de solicitações deixa de ficar em `details`: pessoa, motivo e envio ficam visíveis quando há candidatos/revisor. Acrescentada a área “Acompanhar solicitações”, com filtro nativo por situação e mensagem para filtro sem resultados. Rótulos visíveis, pt/en/es e regras de acesso preservados. Build aprovado; 162 verificações do chat passaram, incluindo envio, remoção de candidatos pendentes, filtro vazio e retorno ao pedido pendente, 320/1440 px e leitura 120% no cenário estreito. Teste geral de teclado/modal aprovado. Inspeção visual pontual da nova captura desktop/claro. Alteração local do chat, sem revisão humana com leitor de tela ou certificação WCAG integral.

## Identificação no primeiro acesso — 10/11/2026

A ativação compara somente o e-mail com a conta vinculada ao token, sem exigir coincidência do nome. Comparação desconsidera maiúsculas e espaços nas extremidades; o nome administrativo é preservado. E-mail divergente gera exclusivamente o erro do campo: “Por gentileza, insira o e-mail correspondente ao do convite enviado!”. Mensagem traduzida pt/en/es, com `aria-invalid` e associação ao aviso por `aria-describedby`. Validações de token/validade/uso e senha confirmada permanecem.

Build e 11 testes de provisionamento aprovados (74 asserções), cobrindo nome diferente aceito, e-mail diferente recusado sem consumir token, sessão e uso único. Navegador com resposta simulada confirmou texto e associação do erro ao campo nos três idiomas. Teste geral de teclado/modal aprovado. Sem revisão humana com leitor de tela nesta alteração; sem conformidade integral inferida.


## Perfis de aprovação do chat e gerente obrigatório — 10/11/2026

Textos pt/en/es atualizados para autorização por administrador ou gerente administrador da mesma empresa, inclusive fora da equipe do projeto. Formulário de projeto marca o gerente como obrigatório, acompanhado de mensagem de validação traduzida. Nenhuma alteração na navegação ou no layout compartilhado.

Build, teste de teclado/modal, 168 verificações do chat (320/1440 px, 120% no cenário estreito, tradução en/es e envio simulado) e navegador da gestão de projetos aprovados. Regras do servidor verificadas nos testes descritos em `docs/chat.md`; há limitações na execução conjunta da suíte completa por colisões de fixtures. Sem revisão humana com leitor de tela ou certificação WCAG integral nesta alteração.


## Destinatário da aprovação de chat — 10/11/2026

Novo select nativo obrigatório “Enviar aprovação para” nos dois formulários, com rótulo visível, nomes pessoais sem tradução e erro associado por `aria-invalid`/`aria-describedby`. Acompanhamento mostra “Enviado para”. Strings pt/en/es incluídas. Layout local do chat preservado, sem mudança de layout compartilhado.

Build, 25 testes do servidor (364 asserções) e 170 verificações do navegador aprovados, incluindo destino enviado, nomes preservados, celular em 120% e idiomas. Inspeção visual pontual da captura desktop/claro. O teste geral de teclado/modal falhou em `scripts/test-accessibility.mjs:47`, na posição de um menu fora do chat, falha observada em execuções anteriores. Sem revisão humana com leitor de tela nem conformidade WCAG integral declarada.

## Revisão de prontidão para produção — 10/11/2026

Resultados consolidados em `docs/revisao-producao-2026-11-10.md`. A rodada completa aprovou 36 suítes de navegador, 323 verificações responsivas, tutorial pt/en/es atualizado e testes de teclado/modal. O teste de posição do painel passou a comparar medidas do mesmo instante, evitando inconsistência entre leituras durante alteração de zoom/fonte. Foram removidos os apontamentos de tradução da rodada de idiomas e a exigência de sobrenome no cadastro administrativo.

Os testes do tutorial confirmam ausência de escrita em APIs durante o passeio e visibilidade dos passos segundo as permissões, incluindo chat e escolha de aprovador. Formatação do chat foi verificada repetidamente após a correção de foco. Não houve auditoria humana com leitor de tela, avaliação integral de contraste ou teste em todos os navegadores/dispositivos; não se declara conformidade integral WCAG.

## Bandeiras e configuração Google — 10/11/2026

O seletor de idioma usa SVGs locais, com imagens decorativas e nomes acessíveis nos botões. Estado pressionado, foco e uso por teclado foram preservados. Build, 323 verificações responsivas, 144 de idiomas e teste de teclado/modal passaram. Três SVGs locais e troca de idioma por teclado também foram verificados diretamente. Não houve teste físico em outros sistemas, leitor de tela ou Google no domínio real; ver [procedimento e limites](dominio-google-e-bandeiras.md).

## Ampliação das fontes de leitura — 11/11/2026

Adicionadas Open Sans, Lato, Montserrat e Atkinson Hyperlegible, além das opções existentes. A lista comum está em `resources/js/readingFonts.js`; seleção, persistência local e restauração continuam no provider de leitura usado nas áreas pública e autenticada.

As quatro novas famílias são distribuídas com o build, com arquivos locais e `font-display: swap`, sem consultas ao Google Fonts/CDN durante o uso. Pesos regulares/negritos e variantes itálicas estão incluídos; Open Sans e Montserrat usam arquivos variáveis. As licenças das distribuições estão em `public/font-licenses/`. Pacotes obtidos pelo [Fontsource](https://fontsource.org/docs/getting-started/install); não se atribui a nenhuma fonte, isoladamente, conformidade de acessibilidade ou benefício clínico.

O teste de teclado/modal agora percorre as oito opções em 1440, 390 e 320 px com leitura em 120%, verifica a família aplicada, carrega explicitamente as novas faces com texto acentuado e testa persistência de Atkinson e restauração do padrão. Acesso público, temas, contraste, redução de movimento e preferências também foram verificados. Não houve ensaio com pessoas com deficiência ou homologação em dispositivos físicos; permanece necessária a revisão humana descrita neste documento. Arial, Verdana e Georgia continuam com alternativas de fonte do sistema.

Resultado desta alteração: build aprovado; 323 verificações de responsividade e 144 de idiomas sem falhas; testes de teclado/modal, carregamento das novas fontes e acesso público aprovados. A instalação das dependências reportou zero vulnerabilidades. A suíte PHP não foi repetida, pois a alteração é de interface e assets.
