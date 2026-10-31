# Tutorial do Spot — revisão de 28/10/2026

O conteúdo do passeio em `resources/js/tutorial/content.js` foi revisado a partir das telas implementadas. Mantidos os identificadores das etapas existentes para preservar o progresso salvo por conta. O índice oferece 63 etapas para administrador e 45 para o perfil de analista usado nos testes; a quantidade depende das permissões.

## Cobertura

- Home, navegação, busca, idioma, tema, notificações e filtros.
- Ajustes de leitura: tamanho de 90% a 120%, fontes locais, restauração e persistência no navegador; orientação de teclado.
- Projetos: identificação, responsáveis, planejamento, criação, edição e ações de finalizar, reabrir e excluir com justificativa de auditoria.
- Financeiro: custo/hora e vigência, despesas e aprovação, comparação entre planejado e realizado e aviso de atividades sem tarifa.
- Equipes e clientes: integrantes, papéis, consulta, cadastro e arquivamento conforme permissões.
- Tarefas: data e tipo de atividade, descrição, tempo e hora extra, responsável, transferência, autoria e participantes; detalhes, edição, exclusão, quadro, lista, calendário, filtros e alternativa ao arraste pelo teclado.
- Documentos: envio e limite de 10 MB, busca, download, exclusão e vínculo com projeto ou tarefa, preservando a privacidade do arquivo.
- Caixa de entrada: leitura, filtros, arquivamento, lixeira e restauração.
- Agenda e provedores externos; integrações condicionadas à configuração do ambiente.
- Portfólio: filtros, gráficos, planejamento e realização, personalização, agrupamentos, medidas, agregações e CSV.
- Histórico, perfil, convites, senha, dois fatores, sessões, notificações e horário silencioso.
- Cadastros administrativos, provisionamento de usuários e permissões.

Todas as etapas têm título, explicação e dica em pt/en/es. O passeio continua apenas explicativo: não abre formulários de gravação nem executa ações em nome da pessoa. Recursos de infraestrutura e estoque sem fluxo nessa interface não foram apresentados como telas disponíveis.

## Verificação e limites

Build de produção aprovado. Testes do tutorial cobrem etapas e destinos, idiomas, permissões, progresso, foco, teclado e celular; verificam que o passeio faz apenas leituras da API. Teste de acessibilidade cobre teclado, foco, movimento reduzido e ajustes de leitura. Validação usa dados simulados; não comprova configuração OAuth em produção ou entrega de e-mail. Não houve revisão com leitor de tela ou pessoas com deficiência, nem auditoria integral de contraste; não se declara conformidade WCAG integral. Nenhum layout compartilhado foi alterado.

Resultados: tutorial com 729 verificações e nenhuma falha; idiomas com 144 verificações e nenhuma falha. O relatório de idiomas ainda aponta textos existentes fora do tutorial para revisão em inglês/espanhol: “% concluído”, “Não informado: 1”, “Permissões” e “Gerenciar permissões”. `git diff --check` sem erros de espaçamento.
