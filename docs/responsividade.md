# Responsividade do Spot

Revisão: 28/10/2026. As regras compartilhadas estão em `resources/css/responsive.css`, importado depois dos estilos dos componentes e de acessibilidade. O ajuste considera a escala de leitura já disponível no Spot. O posicionamento dos popovers é compartilhado em `resources/js/usePopoverPosition.js`.

## O que mudou

- Navegação: até 1100 px, o menu lateral passa a ser uma gaveta com rótulos visíveis, rolagem própria, fechamento por Escape e retorno do foco. O fundo de fechamento cobre a tela. A gaveta fechada não recebe foco.
- Cabeçalho: em celular/tablet, usa duas linhas e mantém a pesquisa disponível. Idiomas e tema ficam em Ajustes rápidos para liberar espaço, mantendo as funções disponíveis. Os atalhos de aplicativos e o gerenciamento de integrações também permanecem acessíveis; os popovers acompanham o botão e se ajustam ao espaço disponível.
- Home: colunas se reorganizam, nomes longos quebram linha e a situação do projeto ocupa uma linha própria no celular, evitando sobreposição. Times e ações de tarefas também se ajustam.
- Formulários: campos e grupos viram uma coluna em telas menores. Campos de texto e seleção usam fonte de 16 px e altura mínima de 44 px nos layouts mobile; ações importantes mantêm texto, sem depender apenas de ícones.
- Modais: largura e altura respeitam a tela e a escala de leitura. Conteúdo longo rola dentro do diálogo; não exige ampliar a página para alcançar os botões. Inclui eventos, tarefas, clientes, convites, permissões, câmera, integrações e confirmações.
- Agenda: até 620 px, o calendário mensal é apresentado como lista de compromissos ordenada por data/hora. Os eventos abrem os mesmos detalhes, e os controles de mês continuam disponíveis. Em tablet, a grade mensal se ajusta à largura.
- Projetos, clientes, documentos, financeiro e auditoria: tabelas extensas mantêm rolagem horizontal local, preservando as colunas e ações. A página inteira não precisa rolar lateralmente. O quadro Kanban também possui rolagem própria e alinhamento das colunas ao deslizar.
- Perfil: as abas mantêm rótulos legíveis e rolagem própria. Foto e opções de alteração continuam disponíveis no celular. Segurança e notificações reorganizam seus campos.
- Portfólios: indicadores, configuração e gráficos se reorganizam em uma coluna; filtros e botões quebram linha.
- Login, primeiro acesso, convites, recuperação de senha e estoque: cartões se ajustam à tela; ilustrações do login não reservam largura nos dispositivos menores.
- Conteúdo e traduções: novos rótulos e a agenda mobile têm suporte no catálogo pt/en/es.

O CSS não usa ocultação global de rolagem para mascarar problemas. Apenas áreas com conteúdo tabular ou colunas extensas têm rolagem horizontal própria. O zoom nativo do navegador permanece disponível.

## Como verificar

Com o servidor local na porta 8000 e as dependências instaladas:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run test:responsive
```

O teste usa Playwright, com dados simulados, e verifica:

- 320×740, 390×844, 768×1024, 1024×768, 1440×900 e 844×390, além de 320×740, 390×420, 740×320, 1280×900 e 1440×900 com tamanho de leitura em 120% e Verdana.
- Celulares com emulação mobile/touch e viewport configurado. As fixtures incluem a mesma declaração de viewport usada pela aplicação.
- Quatorze páginas principais, sete entradas públicas, dados preenchidos, nomes longos e ausência de rolagem horizontal da página.
- Limites dos controles e dos diálogos, funcionamento da busca, menu, tema, agenda, edição de tarefas, cadastro de clientes, convites, abas do perfil e configuração do portfólio.

As verificações complementares são:

```bash
node scripts/test-accessibility.mjs
node scripts/test-tasks.mjs
node scripts/test-management.mjs
node scripts/test-errors.mjs
node scripts/test-browser-navigation.mjs
node scripts/test-languages.mjs
node scripts/test-tutorial.mjs
```

O teste de tarefas agora aguarda a página preservada após recarregar, em conformidade com o histórico de navegação já implementado.

## Evidências e limites

- [Home em 320 px com leitura em 120%](assets/responsividade/home-320-120.png).
- [Home em tela horizontal com leitura em 120%](assets/responsividade/home-740-horizontal-120.png).

A verificação automatizada cobre Chromium com emulação de dispositivos e tamanhos indicados; não substitui avaliação em aparelhos físicos, teclado virtual real ou testes completos em Safari/Firefox. Integrações externas, permissões de câmera e o acesso ao servidor pela rede dependem também do navegador e do ambiente de execução. Tabelas e Kanban exigem rolagem dentro de suas próprias áreas quando o conteúdo não couber.

## Resultado da revisão

- Build de produção aprovado.
- Teste de responsividade: 239 verificações, nenhum erro, incluindo retrato, paisagem e tela com altura reduzida.
- Testes de teclado, tarefas, gestão de projetos, telas de erro e histórico do navegador aprovados.
- Idiomas: 144 verificações sem falhas; permanecem os avisos anteriores de revisão de alguns textos, documentados na revisão de acessibilidade.
- Tutorial: 645 verificações, nenhum erro.
- `git diff --check` sem erros.
