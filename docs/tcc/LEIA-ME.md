# Material de defesa do TCC — SPOT

Comece pela apresentação e ensaie com o roteiro. Os slides têm **15 páginas**, com fala estimada de **14min10s** e margem de 50 segundos até o limite de 15 minutos. O tempo final depende do ensaio; perguntas da banca não estão incluídas.

- **SPOT-Defesa-TCC-15min.pptx** — apresentação editável, com falas, tempos e fontes nas notas de cada slide.
- **SPOT-Defesa-TCC-15min.pdf** — versão para projetar sem PowerPoint.
- **ROTEIRO-14min10s.txt** — fala e tempo de cada slide.
- **GUIA-COMPLETO-TCC-SPOT.txt** — texto solicitado, com pesquisa, comparativo, arquitetura, caminhos por tela, regras e perguntas da banca.
- **GUIA-COMPLETO-TCC-SPOT.docx / .pdf** — versões para edição e leitura, com tabelas e gráficos completos.
- **GUIA-COMPLETO-TCC-SPOT.md** — versão navegável em editores Markdown.
- **dados_pesquisa.json / .csv** — valores transcritos das fontes, com base e diferenças numéricas explícitas.
- **assets/graficos/** — 14 gráficos em PNG e SVG, para edição/reutilização.
- **assets/validacao.txt** — verificações efetivamente executadas nesta entrega.
- **assets/rotas.json** — inventário atual obtido do Laravel.

O guia trata de duas pesquisas: o survey com 94 participantes e o estudo de sistemas/IA da monografia. A análise técnica considera o código local em 26/10/2026, inclusive alterações ainda não commitadas. Não acessa outras conversas do Codex.

As imagens da aplicação usam interface real e dados fictícios com API simulada. As alternativas e frequências dos gráficos da pesquisa foram conferidas nas imagens do DOCX. Não foram inventados dados faltantes, depoimentos ou resultados de comparação. Os documentos originais de Downloads foram preservados.

Antes da defesa, revisar com a equipe os itens do guia sobre diferenças numéricas da pesquisa, PostgreSQL no lugar de SQLite, IA ainda não implementada e dependências das integrações externas. Nenhuma dessas observações altera a pesquisa ou o sistema original.

Para reproduzir a geração, instalar em ambiente de ferramentas separado: `python-pptx`, `python-docx`, `matplotlib` e `Pillow`. Executar `python3 docs/tcc/gerar_material.py` da raiz do projeto. `PyMuPDF` serve para conferir os PDFs; LibreOffice converte PPTX/DOCX para PDF. A captura opcional exige build local, Playwright e um servidor estático da pasta public; o script `capturar-telas.mjs` usa SPOT_BASE_URL e fixtures para isolar os dados.

## Revisão funcional desta entrega

A lista de implementação foi atualizada: notificações de atividade, lembretes de tarefas/agenda, resumos, silêncio e atualização automática dos painéis foram implementados e testados. A linha de IA saiu da lista; os dados originais da pesquisa permanecem preservados para rastreabilidade. O envio externo de atividades exige SMTP e `SPOT_ACTIVITY_EMAIL_ENABLED=true`; inicia desativado, sem envio retroativo, mantendo os avisos internos independentes. Os provedores exigem credenciais e consentimento. Detalhes em `OPERACAO-NOTIFICACOES.md`.

## Gráficos da apresentação

Slide 3: gráfico de linhas com percentuais por faixa etária (perfil ordenado; não é evolução temporal). Slide 5: gráfico de setores com a necessidade de software, calculada sobre 94, incluindo a diferença documental explicitada. As barras exibem contagem e porcentagem sobre 94. Os dois gráficos são editáveis no PowerPoint.
