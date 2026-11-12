# Levantamento consolidado do Spot

Data de elaboração: **12/11/2026**. Síntese das fontes locais inventariadas em [INVENTARIO-FONTES.md](INVENTARIO-FONTES.md), com hashes em [fontes.json](fontes.json). A análise é documental, com consultas pontuais ao código atual para estados de tarefas e perfis; não é nova auditoria funcional completa.

## 1. Identidade, contexto e autoria

**SPOT — Sistemas de Projetos, Organização e Tarefas** é uma aplicação web de gestão de projetos, equipes, execução e resultado financeiro gerencial, desenvolvida no contexto de uma necessidade da Computécnica. O TCC pertence ao Curso de Informática do Colégio Dr. Clóvis Bevilacqua, em Santo André, com identificação de 2026.

Os quatro autores registrados são Alexander Molany Longo, Lucas Rodrigues Soares, Pedro Galvão de Matos e Yasmin Regina de Souza. O orientador identificado na monografia é o Prof. João Carlos de Souza. Esses dados vêm da capa e folha de rosto; os documentos não sustentam uma divisão pessoal detalhada de autoria técnica, personalidade ou carga de trabalho.

O [memorial descritivo original](/home/alexander/Downloads/Controle%20de%20Projetos%20-%20Memorial%20Descritivo.docx), emitido em 15/03/2026, parte do orçamento aprovado de um projeto e pede acompanhamento de horas e despesas até a comparação entre resultado planejado e realizado. A monografia preliminar acrescenta as dores de interface antiga, restrições de acesso insuficientes, custos pouco integrados e dependência da solução anterior. São necessidades relatadas nas fontes; não se deduz delas uma medição de melhoria após implantação.

## 2. Fontes e precedência entre versões

| Família documental | Uso neste levantamento | Limite |
| --- | --- | --- |
| Originais em Downloads: monografias, capítulo da pesquisa e memorial | Contexto, autoria, requisitos e metodologia | Expectativas preliminares podem não corresponder ao produto entregue |
| [Documentação final](../../DOCUMENTACAO-FINAL.md), monografia integrada e documentação técnica | Base consolidada em 10/11/2026, commit `146bd986867ecb25bc75db53c6d1944c6d89ae66` | Não incorpora automaticamente alterações posteriores |
| [Guia completo](../GUIA-COMPLETO-TCC-SPOT.md) e guia de defesa | Pesquisa, regras, arquitetura e preparação para perguntas | Os dois guias atuais reproduzem o mesmo conteúdo; não são evidências independentes |
| Documentos de módulos, README e código atual | Complementos de 11–12/11 e regras específicas | Há trechos intermediários preservados que exigem leitura cronológica |
| [Revisão de produção](../../revisao-producao-2026-11-10.md), relatórios e manifestos | Evidências de execuções já registradas | Não são nova execução nesta entrega nem homologação do servidor |
| Slides, notas e roteiros | Preparação para apresentação | A apresentação tem 12 slides; o roteiro de 15 slides está desatualizado |
| Revisões, backups e ZIPs | Histórico e comparação de versões | Cópias e formatos diferentes não aumentam a amostra da pesquisa |
| Gráficos, capturas e logos | Recursos da apresentação e da documentação | Inventariados; sem nova validação visual de cada imagem |

O levantamento de 26/10/2026 continua útil como histórico. Seu alerta para não apresentar chat como pronto foi superado pela implementação e documentação posteriores. O texto inicial de elegibilidade de gerentes aceitava campos opcionais; a revisão final passou a exigir gerente do projeto na criação v1 e a preservar um gerente elegível na equipe. Para esses assuntos prevalecem os registros finais e posteriores, não o parágrafo histórico isolado.

A edição final identifica um commit anterior ao HEAD local `36caa41`, consultado nesta sessão. A documentação de 10/11 permanece vinculada ao seu próprio corte; os complementos não autorizam atribuir todos os resultados antigos ao código posterior.

## 3. Pesquisa que fundamentou o projeto

O [capítulo original](/home/alexander/Downloads/Capitulo_Levantamento_Pesquisa_ABNT.docx) declara um survey aplicado, descritivo, quali-quantitativo, com amostra de conveniência não probabilística, coletado entre **18/03 e 24/08/2026**, com **94 respostas válidas**. O formulário é descrito como contendo 15 questões fechadas e uma aberta. O material local transcrito contém 13 conjuntos de resultados gráficos, e não uma planilha de respostas individuais.

Os [dados transcritos](../dados_pesquisa.json) e [CSV](../dados_pesquisa.csv) preservam frequências e ressalvas:

| Achado documental | Contagem | Interpretação possível |
| --- | --- | --- |
| Pessoas empregadas | 88 de 94 | Caracterização da amostra |
| Dificuldade de organização: Sim + Absolutamente | 49 + 11 = 60; 63,8% da base | Justifica investigar organização de tarefas e responsabilidades |
| Dificuldade de alinhamento: Sim + Absolutamente | 31 + 8 = 39; 41,5% da base | Motiva acompanhamento e comunicação; não é unanimidade |
| Organização relacionada a faturamento: Sim + Absolutamente | 57 + 26 = 83; 88,3% da base | Percepção declarada; não demonstra aumento de faturamento causado pelo Spot |
| Dificuldade de custos extras: Sim + Absolutamente | 21 + 9 = 30; 31,9% da base | Fundamenta considerar despesas e custos |
| Necessidade de software dedicado: Sim + Absolutamente | 32 + 11 = 43; 45,7% da base | Concordância direta; não equivale a intenção de compra |
| Necessidade: concordância direta + Talvez | 43 + 33 = 76; 80,9% da base | Abertura ou concordância, sem converter Talvez em Sim |
| Sugestões abertas | 57 comentários declarados; 49 frequências publicadas | Diferença de oito unidades não reconciliada; categorias não são falas literais |

Há totais diferentes de 94 em várias questões. Em atuação profissional, por exemplo, as categorias somam 92; em interface, 90. Não foram completadas respostas ausentes nem criados depoimentos. A questão de ferramentas admite múltiplas menções, então suas frequências não compõem uma distribuição exclusiva de 100%.

As sugestões agrupadas incluem interface simples, controle financeiro, integrações/e-mail, painéis, tarefas/agenda e automação/IA. A versão implementada não demonstra agente generativo de planejamento, predição validada ou distribuição autônoma de trabalho. O discurso preliminar sobre IA precisa ser tratado como intenção/possibilidade, separado de tradução automática opcional e automações determinísticas.

## 4. Inventário funcional e aplicação ao TCC

| Área | O que a documentação registra | Como usar na reta final | Condição ou limite |
| --- | --- | --- | --- |
| Acesso e administração | Contas provisionadas, ativação, perfis, permissões e exceções | Criar quatro contas de demonstração e restringir o acesso ao projeto acadêmico | Sem cadastro público; não transformar a simulação em alteração de contas reais |
| Projetos | Cliente, proposta, responsáveis, datas, escopo, planejamento e governança | Um projeto claramente identificado como SIMULAÇÃO | Gerente do projeto elegível e ativo; proposta única na empresa |
| Equipes | Membros por projeto, papéis, atribuição, remoção e tarifas | Quatro autores na mesma equipe; coordenação definida | Perfil da conta e papel no projeto são conceitos diferentes |
| Tarefas | Responsável, participantes, descrição, prioridade, prazo, duração, atividade e histórico | Distribuir pesquisa, revisão, demonstração e entrega | Coparticipação não concede edição automaticamente |
| Quadro, lista e calendário | `todo`, `in_progress`, `review`, `done` | A fazer → Em andamento → Em revisão → Concluído | Não há estado nativo Bloqueado; registrar impedimento na descrição e no roteiro |
| Tarefas no projeto | Quadro incorporado em detalhes do projeto; filtro `project_id` | Reunião focada exclusivamente nas tarefas do TCC | Complemento de 12/11; títulos visuais de até 15 caracteres com acesso ao texto completo |
| Apontamentos | Trabalho próprio, data, duração e tarifa vigente para work logs | Opcionalmente demonstrar esforço sintético | Duração criada para o cenário não é esforço real nem medida de produtividade |
| Meus documentos | Upload, download, associação a projeto/tarefa, auditoria; até 10 MB | Cada autor organiza suas próprias cópias e vínculos | Arquivos privados ao proprietário; sem edição colaborativa ou versionamento documental nativo |
| Chat | Conversas entre duas pessoas autorizadas, texto, anexos, formatação, edições e versões | Revisão entre responsável e revisor | Não é chat de grupo; equipe compartilhada permite conversa direta entre elegíveis |
| Aprovação de chat | Solicitação direcionada a administrador/gerente administrador fora do vínculo permitido | Apenas demonstrar se houver interlocutor externo de teste | Não criar pedidos para pessoas reais; não presumir acesso do orientador |
| Agenda | Eventos internos e integrações Google/Microsoft conforme configuração | Reunião inicial, checkpoints e ensaio como eventos internos | Integrações e reuniões externas dependem de OAuth e consentimentos |
| Caixa de entrada | Avisos, leitura, arquivamento e solicitações | Acompanhar atribuições e prazos | Não substitui um canal livre de mensagens |
| Notificações | Catálogo de eventos, silêncio, resumos, fila e agendador | Preferir avisos internos no ensaio | E-mail de atividade depende de ativação e SMTP; fila também atende ativação e convites |
| Home | Resumo de projetos e tarefas acessíveis | Visão de entrada de cada autor | Não é inventário completo de todas as tarefas/projetos |
| Portfólios | Indicadores, filtros, gráficos e exportação CSV | Demonstrar acompanhamento autorizado | Visibilidade e concessões são específicas; percentual do roteiro não é automaticamente progresso do projeto |
| Financeiro | Contrato, tarifas, impostos, comissão, despesas e resultado gerencial | Parte opcional para explicar o produto; fora do núcleo acadêmico | Sem receita de cliente ou ganho financeiro inventado para o TCC |
| Solicitações de indicadores | Analista pede acesso a destinatário elegível; concessão limitada | Exemplo opcional de governança | Permissão de relatório não transforma analista em gestor nem libera toda a empresa |
| Clientes e parâmetros | Cadastros, arquivamento, status, situação, atividades e despesas | Usar somente parâmetros exigidos em base de demonstração | Não cadastrar o colégio como cliente contratante real |
| Histórico e auditoria | Autor, evento, data e alterações nos fluxos cobertos | Explicar rastreabilidade a partir de ações realmente realizadas no ensaio | Não reconstruir retroativamente um histórico que nunca existiu |
| Perfil e experiência | Foto, idioma pt/en/es, tema, fuso, leitura e tutorial | Cada pessoa adapta a interface e aprende o fluxo | Parte das preferências é local ao navegador |
| Estoque | Produtos, categorias e movimentações em módulo adicional | Fora da simulação do TCC | Não presumir integração automática com custo do projeto ou isolamento equivalente ao núcleo |

Fontes principais: [guia completo](../GUIA-COMPLETO-TCC-SPOT.md), [tarefas](../../quadro-de-tarefas.md), [documentos](../../vinculos-de-documentos.md), [chat](../../chat.md), [notificações](../../notificacoes-e-atualizacao-automatica.md), [solicitações de indicadores](../../solicitacoes-acesso-indicadores.md) e [memorial implementado](../../memorial/levantamento.md).

### Regras que afetam diretamente a simulação

O gerente deve ser administrador, gestor-administrador ou gestor ativo, ativado e elegível na mesma empresa. Na criação v1, o gerente indicado entra como `manager`; a lista de membros iniciais complementa a equipe. O trecho antigo de cadastro que dizia não adicionar gerente automaticamente foi superado pela regra final. Conferir a equipe após criar o projeto, sem pressupor que selecionar um perfil global ajusta todos os papéis locais.

Analistas operam nas tarefas que podem consultar e editar. Participar da equipe não equivale a ver todas as atividades dos colegas. Para a revisão cruzada deste roteiro, o revisor deve ser adicionado como participante da tarefa; ele comunica o parecer ao responsável, que atualiza o próprio cartão. Uma visão consolidada pela coordenação exige suas permissões específicas.

O módulo Meus documentos não é uma biblioteca compartilhada do TCC. Para trocar uma cópia, usar anexo de chat autorizado dentro dos formatos/limites aceitos ou um canal já acordado pela equipe; o autor destinatário pode manter sua própria cópia. Não há edição simultânea da monografia dentro do Spot.

Finalizar um projeto altera os vínculos e bloqueia operações. Reabrir não restaura automaticamente todos os acessos anteriores. Por isso o cenário permanece aberto enquanto houver pendências e só prevê finalização após conferência explícita.

## 5. Arquitetura e operação

A documentação final descreve **Laravel 13, PHP, React 18, Vite, PostgreSQL e relatórios Python/pandas**. Trata-se de aplicação integrada, com backend responsável por autorização e seleção dos dados. O processo Python recebe JSON e devolve JSON para cálculos/agrupamentos; não é um serviço autônomo de IA.

O [mapa do código](../../MAPA-DO-CODIGO.md) identifica as entradas ativas: `resources/js/app.jsx`, shell em `App.jsx`, rotas Laravel, controladores, regras em `app/Domain`, modelos, migrations e componentes especializados. As principais entidades incluem usuários/empresas/perfis/permissões, projetos/membros, tarefas/participantes/apontamentos, documentos, despesas/tarifas, eventos, notificações e conversas/mensagens/versões.

A implantação documentada usa Docker Compose com aplicação web, PostgreSQL, worker e scheduler, além de HTTPS por Caddy. Há roteiros para domínio, Google/Microsoft, diagnóstico de e-mail e fotos OAuth. Isso descreve requisitos de configuração; os arquivos locais não comprovam o estado atual de uma VM Azure.

O núcleo usa sessão/Sanctum e proteção CSRF, com controle por empresa, projeto e operação. Há autenticação por senha, OAuth autorizado, recuperação, 2FA no escopo documentado e gestão de sessões. A compatibilidade JWT do estoque é um fluxo distinto. Nenhum desses controles representa certificação de segurança.

## 6. Evidências: respeitar data e escopo

| Fonte local | Registro | Como interpretar |
| --- | --- | --- |
| [Revisão de 10/11](../../revisao-producao-2026-11-10.md) | 256 testes PHP; 3.487 asserções; 36 suítes de navegador; 323 verificações responsivas; 756 de tutorial; 144 de idiomas | Resultado daquela rodada, também com teste PHP em imagem Docker |
| [Chat, complemento de 11/11](../../chat.md) | 261 testes PHP; 3.580 asserções; 174 verificações específicas de chat | Rodada posterior relacionada ao contato administrativo direto |
| [Mensagens de erro, 11/11](../../verificacao-mensagens-de-erro.md) | 266 testes PHP; 4.060 asserções; verificações de formulários, erros, teclado, idiomas e responsividade | Registro completo mais recente localizado; não reexecutado aqui |
| [Tarefas no projeto, 12/11](../../quadro-de-tarefas.md) | 14 testes pertinentes; 144 asserções; build e suítes de navegador documentadas | Evidência específica; não chamar de nova rodada completa de toda a aplicação |
| Esta entrega | Reprodução de 102 eventos sintéticos para 28 tarefas; inventário e checagens documentais | Consistência do exercício offline, sem testar servidor, UI ou APIs do Spot |

Não somar testes/asserções de execuções diferentes para alegar maior cobertura. As capturas e testes usam dados sintéticos e frequentemente APIs simuladas. Nenhum total comprova eficácia com usuários, conformidade WCAG integral, ausência de defeitos ou resultado financeiro.

## 7. Acessibilidade, idiomas e recuperação de erros

Há rótulos, navegação por teclado, foco visível, hook de foco para fluxos compatíveis, Escape/retorno do foco, recursos de leitura, respeito a movimento reduzido e responsividade. O objetivo registrado é WCAG 2.2 AA, com pendências de contraste sistemático, leitores de tela, dispositivos reais e avaliação humana. Os testes automatizados não permitem declarar conformidade integral.

Português, inglês e espanhol são preservados. Formatos locais e conteúdos de usuário devem manter identidade e não sofrer tradução automática indiscriminada. O tutorial é opcional e varia pelas permissões. Erros de validação devem manter dados preenchidos e orientar a correção; o complemento de 11/11 documenta associação de mensagens por campo no editor compartilhado.

Fontes: [acessibilidade](../../acessibilidade-e-inclusao.md), [responsividade](../../responsividade.md), [tutorial](../../tutorial-atualizado.md), [telas de erro](../../telas-de-erro.md), [catálogo de erros](../../mensagens-de-erro.md) e [verificação](../../verificacao-mensagens-de-erro.md).

## 8. Pendências concretas para a reta final

1. **Apresentação e fala:** sincronizar o PPTX de 12 slides com a documentação final e os complementos de 11–12/11. `roteiro-tempos.json` e `ROTEIRO-14min10s.txt` refletem sequência antiga de 15 slides. O material de revisão também registra uma fase de 11 slides e outra de 12; não misturar esses tempos.
2. **Normalização institucional:** confirmar exigência e tratamento de folha de aprovação, catalogação e modelo do colégio. A [conferência ABNT local](../../CONFERENCIA-ABNT-2026-11-10.md) não registra aprovação institucional integral. Esta análise não reconferiu normas externas.
3. **Data e divisão real do trabalho:** não foi identificada uma data efetiva de defesa nem evidência suficiente para atribuir responsabilidades individuais reais. O cronograma usa D, sem inventar data.
4. **Demonstração:** preparar contas e dados sintéticos separados; manter acesso aos documentos correto; evitar depender de Google/Microsoft/SMTP para o percurso principal.
5. **Homologação e produção:** responsáveis antigos, HTTPS, backup/restauração, volumes, filas e consentimentos precisam ser verificados no destino. A revisão local não comprova que esses itens estejam resolvidos na VM atual.
6. **Avaliação com usuários:** falta medição pós-uso do sistema pela equipe ou cliente, incluindo usabilidade, acessibilidade e eventuais efeitos sobre organização e tempo.

## 9. O que a simulação acrescenta ao TCC

O cenário usa o próprio processo de conclusão do TCC como exemplo de aplicação do Spot: decompor entregas, atribuir responsáveis, revisar tarefas, organizar compromissos e consultar histórico. É uma demonstração coerente com as funções documentadas.

Ela permite explicar o caminho entre problema e recurso sem fabricar uma implantação anterior. Para transformar o exemplo em estudo de uso real, será necessário executar o percurso com participantes, registrar datas e procedimentos verdadeiros e analisar resultados observados. Os perfis, diálogos e horas a seguir permanecem dados sintéticos até lá.
