# Spot — roteiro de apresentação de 15 minutos

**Duração total prevista:** 15:00. O roteiro está incluído nas notas de apresentador de cada slide do PowerPoint. Fale em ritmo natural e use os tempos como referência; os slides não foram feitos para serem lidos.

## 01. Abertura — 0:30

Olá. Em quinze minutos vou apresentar o Spot como produto e como sistema: que problema ele resolve, como um projeto percorre a plataforma, quais regras sustentam o resultado financeiro, como a solução foi construída e onde ela se posiciona diante de outras ferramentas. A ideia é sair com uma visão útil tanto para quem avalia o produto quanto para quem precisa operá-lo, mantê-lo ou evoluí-lo.

## 02. O produto em uma frase — 1:00

O Spot é uma plataforma de gestão de projetos orientada a operações de serviços. Ele combina quatro momentos que normalmente acabam espalhados entre planilhas e ferramentas: planejar um projeto, executar o trabalho, controlar mudanças e despesas, e entender o resultado. O diferencial de foco é ligar a atividade da equipe à visão de custo e margem. Isso não significa que ele seja um ERP completo ou uma ferramenta universal de colaboração; seu núcleo é o projeto e sua execução econômica.

## 03. O problema — 1:05

O problema que o Spot procura resolver não é simplesmente “falta de tarefas”. É a fragmentação do contexto: a proposta tem um valor, as horas são apontadas em outro lugar, as despesas chegam depois e o gestor só descobre o desvio quando consolida os dados. O Spot tenta encurtar essa distância mantendo cliente, equipe, tarifa, horas, despesas e situação do projeto no mesmo contexto. A pesquisa de mercado existente no repositório reforça temas como organização, usabilidade, integração e visibilidade financeira; os percentuais publicados nessa pesquisa devem ser apresentados sempre com seu tamanho e método de amostra, não como estatística geral do mercado.

## 04. Ciclo do projeto — 1:10

A jornada começa com o projeto comercial: cliente, proposta, valor contratado, datas e responsáveis. Antes da execução, configura-se a equipe, as funções, as tarifas com períodos de vigência e a composição tributária. Durante o trabalho, tarefas, horas extras e despesas alimentam a operação. Despesas podem passar por aprovação. A governança permite finalizar, reabrir ou excluir com justificativas e permissões. No fim, gestores analisam resultado do projeto e carteira. É importante notar que os dados não são só etapas de formulário: as decisões de acesso e os registros de auditoria acompanham os fluxos.

## 05. Capacidades do produto — 1:10

A experiência está organizada em áreas. Projetos concentram os dados comerciais e o ciclo de vida. Tarefas e equipes conectam pessoas ao trabalho e permitem diferentes formas de visualizar a execução. O financeiro e o portfólio mostram custos e indicadores agregados. A colaboração inclui agenda, caixa de entrada, convites e documentos. A administração cuida de usuários, parâmetros, permissões e integração de diretório. O estoque mantém cadastro de produtos e histórico de movimentações. Esse módulo é importante para conhecer a abrangência do repositório, mas é um domínio distinto e não deve ser confundido com o núcleo de gestão de projetos.

## 06. Regras de comportamento — 1:20

O comportamento do Spot depende de regras que vão além do que a tela mostra. Não há cadastro público: uma conta é provisionada e ativada por link de uso único. Os acessos respeitam empresa e vínculos com projeto. Em tarefas, permissões para ver, editar ou excluir trabalho de terceiros são tratadas separadamente. Tarifas têm vigência e não devem se sobrepor; o valor usado fica fotografado no registro de horas para preservar o histórico. Despesas pendentes ou rejeitadas não compõem o realizado. Um projeto finalizado torna-se somente leitura, e ações de finalização, reabertura e exclusão exigem justificativa. Essas regras precisam ser respeitadas em qualquer novo endpoint ou integração.

## 07. Resultado financeiro — 1:20

O motor financeiro calcula o projeto a partir do contrato, das horas e de eventos governados. Em linhas gerais, considera receita realizada, custos de mão de obra calculados com as tarifas capturadas, impostos, comissão e despesas aprovadas. Horas extras podem ter tarifas distintas de custo e faturamento. Se uma hora não possui tarifa aplicável, ela não desaparece: aparece como pendência e o resultado é sinalizado como parcial. Há ainda uma salvaguarda contra dupla contagem de tarefas que já estejam cobertas por apontamentos vinculados. Para o profissional de finanças, isso significa que é essencial validar as regras de tarifa, impostos e comissão na configuração e entender o estado de completude do resultado antes de usá-lo em decisão.

## 08. Arquitetura e stack — 1:00

A interface usa React 18 e Vite, mas é servida pela aplicação Laravel; não é necessário assumir dois produtos independentes para implantá-la. O backend usa PHP 8.3 e Laravel 13, com controllers, validações, modelos e ações de domínio. O banco documentado é PostgreSQL. Python com Pandas consolida indicadores de portfólio. O fluxo web principal usa sessão Laravel Sanctum e proteção CSRF. Socialite integra provedores OAuth; JWT permanece em rotas de compatibilidade. O processamento assíncrono de e-mails usa a fila do Laravel, e o scheduler executa rotinas. Em implantação, portanto, a equipe precisa cuidar de aplicação, banco, worker, agendador, SMTP e armazenamento compartilhado de sessão se houver múltiplas instâncias.

## 09. Segurança e operação — 1:10

A segurança começa com o provisionamento controlado e continua em cada requisição. Perfis e permissões limitam operações, projetos e dados financeiros. Há controle de sessões, opção de autenticação em duas etapas e login por Google ou Microsoft para contas já autorizadas. Os convites são escopados ao projeto e os tokens não são armazenados em texto puro. Auditoria registra ações importantes, não cada clique da interface. A retenção é configurável e depende do scheduler ativo. Um ponto operacional importante: Microsoft Entra ID exige credenciais e consentimento administrativo para consultar o diretório; ter login Microsoft configurado não habilita automaticamente a importação de usuários.

## 10. Posicionamento de mercado — 1:20

No mercado, a referência mais próxima é a categoria de gestão de trabalho e portfólio. O monday.com enfatiza flexibilidade, quadros, dashboards, carga de trabalho e soluções de portfólio; algumas capacidades avançadas dependem de planos específicos. O Teamwork.com se posiciona mais diretamente para agências e serviços a clientes, destacando horas faturáveis, orçamento, gestão de capacidade e rentabilidade. O Spot combina gestão de projeto com regras financeiras e governança ajustadas à operação interna. Esta é uma comparação funcional baseada em páginas oficiais, não uma avaliação de qualidade, preço ou escala. A oportunidade para o Spot está na aderência às regras locais e à integração entre execução e apuração; os desafios são competir com a maturidade, integrações, escalabilidade, ecossistemas e investimento contínuo dos SaaS estabelecidos.

## 11. Públicos profissionais — 1:15

O valor da plataforma aparece quando cada papel encontra informação útil sem receber acesso desnecessário. O analista precisa saber o que está atribuído e registrar a atividade. O gestor precisa entender andamento, capacidade, despesas e resultado. O administrador provisiona pessoas, configura parâmetros e ajusta permissões. Já o profissional técnico precisa conhecer rotas, migrações, jobs, scheduler, credenciais externas, logs e backups. Essa separação também orienta treinamento: não é preciso ensinar toda a plataforma a todos; ensina-se o fluxo e as decisões relevantes para cada papel.

## 12. Maturidade e limites — 1:15

É importante comunicar maturidade com precisão. O repositório contém fluxos implementados e testes automatizados, mas testes não comprovam sozinhos disponibilidade, segurança operacional, capacidade em escala ou adoção. E-mail depende de SMTP e worker. Diretório depende de tenant e consentimento. Em produção são necessários HTTPS, PostgreSQL operado, backups testados, monitoramento, gestão de segredos, política de atualização e processos supervisionados. Também existe JWT legado que deve ser acompanhado como compatibilidade. Portanto, a descrição correta é: núcleo funcional amplo e verificável no código, com uma etapa de validação e operação de produção que precisa ser tratada explicitamente.

## 13. Conclusão — 1:25

Para concluir: o Spot une o cadastro, a execução e a análise do projeto; preserva regras financeiras e de acesso; e oferece uma arquitetura web conhecida, baseada em Laravel, React e PostgreSQL. Seu posicionamento é mais específico que o de uma ferramenta genérica de tarefas, mas ainda precisa demonstrar valor e confiabilidade em uso real frente a soluções maduras. A evolução deve começar com piloto controlado, feedback dos diferentes perfis, indicadores de uso, monitoramento e rotina de backup e recuperação. Com isso, o Spot pode transformar uma necessidade operacional em uma plataforma consistente para acompanhar trabalho e resultado. Obrigado. Agora podemos aprofundar dúvidas sobre produto, arquitetura ou próximos passos.

## Referências para o slide de mercado

- monday.com, recursos de projetos: https://monday.com/projects/features
- monday.com, solução de portfólio: https://support.monday.com/hc/en-us/articles/13337066797202-The-portfolio-solution
- Teamwork.com, gestão para agências: https://www.teamwork.com/teams/agencies/

## Referências do projeto

- README e instruções: `README.md`
- Mapa do código: `docs/MAPA-DO-CODIGO.md`
- Adequação ao memorial: `docs/memorial/levantamento.md`
- Rotas API: `routes/api.php`

**Observação:** comparações de mercado resumem funcionalidades divulgadas por seus fornecedores. O estado do Spot descreve o código e os documentos deste repositório; não é certificação de produção nem avaliação independente de desempenho.
