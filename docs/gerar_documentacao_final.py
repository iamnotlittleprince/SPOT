#!/usr/bin/env python3
"""Gera monografia e documentação técnica a partir do inventário e evidências locais.
Ferramentas separadas da aplicação: python-docx, Pillow; conversão por LibreOffice/UNO.
Não altera código, banco, pesquisa bruta nem os documentos originais em Downloads.
"""
from pathlib import Path
import json, hashlib, subprocess, html, re, unicodedata
from datetime import date
from docx import Document
from docx.shared import Cm, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.section import WD_SECTION_START
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from PIL import Image

ROOT=Path(__file__).resolve().parent.parent
D=ROOT/'docs'; AS=D/'assets/documentacao-final'; F=D/'fontes-documentais'
AUTHORS=['Alexander Molany Longo','Lucas Rodrigues Soares','Pedro Galvão de Matos','Yasmin Regina de Souza']
INSTITUTION='Colégio Dr. Clóvis Bevilacqua'; CITY='Santo André'; YEAR='2026'; ACCESS='10 nov. 2026'
REPO='https://github.com/iamnotlittleprince/SPOT'
COMMIT=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
PERMALINK=f'{REPO}/tree/{COMMIT}'
ROUTES=json.loads((F/'rotas-versao-final.json').read_text())
SURVEY=json.loads((D/'tcc/dados_pesquisa.json').read_text())
CODECITE='(Longo et al., 2026c)'
RESEARCHCITE='(Longo et al., 2026a)'

# Referências bibliográficas: datas de publicação não identificadas não são inventadas.
REFERENCES=[
('ABNT14724','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 14724:2024','Informação e documentação — Trabalhos acadêmicos — Apresentação. Rio de Janeiro: ABNT, 2024. Versão corrigida em 1 abr. 2025.',''),
('ABNT6023','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 6023:2025','Informação e documentação — Referências — Elaboração. Rio de Janeiro: ABNT, 2025.',''),
('ABNT6024','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 6024:2012','Informação e documentação — Numeração progressiva das seções de um documento — Apresentação. Rio de Janeiro: ABNT, 2012a.',''),
('ABNT6027','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 6027:2012','Informação e documentação — Sumário — Apresentação. Rio de Janeiro: ABNT, 2012b.',''),
('ABNT6028','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 6028:2021','Informação e documentação — Resumo, resenha e recensão — Apresentação. Rio de Janeiro: ABNT, 2021.',''),
('ABNT10520','ASSOCIAÇÃO BRASILEIRA DE NORMAS TÉCNICAS.','ABNT NBR 10520:2023','Informação e documentação — Citações em documentos — Apresentação. Rio de Janeiro: ABNT, 2023.',''),
('AWS','AMAZON WEB SERVICES.','Cloud computing services','AWS, c2026.','https://aws.amazon.com/'),
('ASANA','ASANA.','Asana work management','Asana, c2026.','https://asana.com/product'),
('JIRA','ATLASSIAN.','Jira','Atlassian, c2026a.','https://www.atlassian.com/software/jira'),
('TRELLO','ATLASSIAN.','Trello','Atlassian, c2026b.','https://trello.com/'),
('CLICKUP','CLICKUP.','ClickUp','ClickUp, c2026.','https://clickup.com/'),
('DOCKER','DOCKER.','Docker Compose','Docker, c2026.','https://docs.docker.com/compose/'),
('LARAVEL','LARAVEL.','Laravel 13.x documentation','Laravel, c2026.','https://laravel.com/docs/13.x'),
('LIBRE','LIBRETRANSLATE.','Translate text','LibreTranslate, [20--].','https://docs.libretranslate.com/api/operations/translate/'),
('PESQUISA','LONGO, Alexander Molany; SOARES, Lucas Rodrigues; MATOS, Pedro Galvão de; SOUZA, Yasmin Regina de.','Levantamento de pesquisa de campo do SPOT','Santo André: [s. n.], 2026a. Documento de trabalho fornecido pela equipe; arquivo Capitulo_Levantamento_Pesquisa_ABNT.docx.',''),
('ORIGINAL','LONGO, Alexander Molany; SOARES, Lucas Rodrigues; MATOS, Pedro Galvão de; SOUZA, Yasmin Regina de.','Monografia SPOT: documentação integrada','Santo André: Colégio Dr. Clóvis Bevilacqua, 2026b. Versão preliminar fornecida pela equipe; arquivo Monografia_SPOT_documentacao_integrada.docx.',''),
('CODE','LONGO, Alexander Molany; SOARES, Lucas Rodrigues; MATOS, Pedro Galvão de; SOUZA, Yasmin Regina de.','SPOT: sistemas de projetos, organização e tarefas','2026c. Código-fonte. Versão consultada: commit '+COMMIT+'.','https://github.com/iamnotlittleprince/SPOT/tree/'+COMMIT),
('REACT','META PLATFORMS.','React 18: quick start','Meta, [20--].','https://18.react.dev/learn'),
('MONDAY','MONDAY.COM.','monday.com','monday.com, [20--].','https://monday.com/'),
('NOTION','NOTION.','Notion','Notion, c2026.','https://www.notion.com/'),
('PANDAS','PANDAS DEVELOPMENT TEAM.','pandas 2.3.3 documentation','pandas, c2026.','https://pandas.pydata.org/pandas-docs/version/2.3/'),
('POSTGRES','POSTGRESQL GLOBAL DEVELOPMENT GROUP.','PostgreSQL 17 documentation','PostgreSQL, c2026.','https://www.postgresql.org/docs/17/'),
('UFABC','UNIVERSIDADE FEDERAL DO ABC. Sistema de Bibliotecas.','Normalização','Santo André: UFABC, [20--].','https://portal.biblioteca.ufabc.edu.br/servicos/normalizacao'),
('UFES','UNIVERSIDADE FEDERAL DO ESPÍRITO SANTO. Sistema Integrado de Bibliotecas.','Normalização','Vitória: UFES, 2026. Atualizado em 28 out. 2026.','https://biblioteca.ufes.br/manuais-de-normalizacao'),
('VITE','VITE.','Getting started','Vite, [20--].','https://vite.dev/guide/'),
('WCAG','WORLD WIDE WEB CONSORTIUM.','Web Content Accessibility Guidelines (WCAG) 2.2','W3C Recommendation, 12 dez. 2024.','https://www.w3.org/TR/WCAG22/'),
]
REFERENCES.sort(key=lambda x: unicodedata.normalize('NFKD',x[1]+' '+x[2]).casefold())

# Blocos estruturados são a fonte comum das versões DOCX e HTML.
def H(n,t):return {'type':'h','level':n,'text':t}
def P(t):return {'type':'p','text':t}
def T(title,headers,rows,source='Elaboração própria com base no código-fonte do SPOT (Longo et al., 2026c).'):
 return {'type':'table','title':title,'headers':headers,'rows':rows,'source':source,'numeric':any('Frequência' in h for h in headers)}
def I(title,path,source):return {'type':'figure','title':title,'path':str(path),'source':source}
def L(t,url):return {'type':'link','text':t,'url':url}

TECHNOLOGIES=[['Servidor','PHP 8.3; Laravel 13','Rotas, validação, autorização, transações, filas e correio.'],['Interface','React 18.3.1; Vite 8.3.5','Componentes, estado, navegação da aplicação e compilação dos assets.'],['Persistência','PostgreSQL; imagem PostgreSQL 17','Banco relacional único; SQLite não é o banco da versão final.'],['Analytics','Python 3; pandas 2.3.3 na imagem verificada','Consolidação de indicadores; requirements.txt admite pandas >=2.2 e <3.0.'],['Editor do chat','Tiptap / ProseMirror','Documento estruturado, listas, links e atalhos, com validação no servidor.'],['Autenticação','Sessão/Sanctum e JWT','Sessão da aplicação principal; JWT nas rotas de compatibilidade e estoque.'],['Operação','Docker Compose; FrankenPHP/Caddy','Serviços web, worker, scheduler e PostgreSQL; certificados dependem do domínio real.']]
RULES=[
['RN01','Provisionamento','Somente usuário com alçada administrativa provisiona conta e gera link de ativação; não há solicitação pública de primeiro acesso.'],
['RN02','Ativação','O e-mail deve corresponder à conta do token; o nome não precisa coincidir. Token deve estar válido e não utilizado; senha precisa de confirmação.'],
['RN03','Reenvio','Uma transição de outro estado para aguardando ativação gera novo link e agenda e-mail; editar uma conta já pendente não repete o envio.'],
['RN04','Projeto','Gerente do projeto obrigatório na criação v1; o responsável entra como manager na equipe. Não se permite remover ou rebaixar o último gerente elegível da equipe.'],
['RN05','Visibilidade','Empresa atual, perfis, participação e exceções de permissão limitam os dados e as operações. Interface não substitui autorização do servidor.'],
['RN06','Chat direto','Exige vínculo ativo de analyst ou manager dos dois usuários em um mesmo projeto, ou autorização específica válida para o par.'],
['RN07','Pedido de chat','Pessoa, motivo e aprovador são obrigatórios. O aprovador é administrador ou gerente administrador ativo da mesma empresa, diferente do solicitante, inclusive fora do projeto.'],
['RN08','Decisão','Novo pedido vai apenas ao aprovador escolhido; só ele aprova/recusa. Uma pendência por par impede duplicação, inclusive inversa. Pedidos antigos sem destinatário mantêm a compatibilidade.'],
['RN09','Restrições do chat','Convidados e contas inativas não usam o chat. Aprovar não concede leitura de conversas de terceiros. Revogação preserva histórico.'],
['RN10','Mensagens','Apenas autor edita até 24 horas do envio original; versões concorrentes são verificadas. Original e revisões são preservados.'],
['RN11','Tradução','Bandeiras pt/en/es escolhem apenas destino de tradução da mensagem; original preservado. Serviço externo configurado é necessário.'],
['RN12','Recuperação','Intervalo de 60 segundos entre gerações para o mesmo e-mail; link vale 60 minutos. Nova geração substitui a anterior, sem divulgar existência da conta.'],
['RN13','Projetos finalizados','Restrições a alterações e preservação do histórico seguem os endpoints e permissões aplicáveis.'],
['RN14','Despesas e tarifas','Despesas passam pelo fluxo de revisão; custos e preços usam tarifas e vigências, sem sobreposições indevidas.'],
['RN15','Notificações','Preferências, canal, frequência e silêncio condicionam entrega; processamento em fila e deduplicação evitam reenvios indevidos.'],
]
DATA_GROUPS=[
['Identidade e acesso','users, profiles, permissions, company_user_profiles, user_permission_overrides','Contas, empresa atual, perfis e exceções.'],
['Organização e projetos','companies, organizations, clients, projects, project_members','Responsáveis, contrato, equipe e vínculos.'],
['Execução e finanças','tasks, work_logs, project_expenses, analyst_project_rates, project_taxes','Atividades, horas, despesas, tarifas e composição financeira.'],
['Convites e ativação','project_invitations, user_activation_tokens','Tokens armazenados como hash, validade e uso/revogação.'],
['Chat','chat_conversations, chat_access_requests, chat_messages, chat_message_revisions, chat_attachments','Par de usuários, aprovador escolhido/decisor, versões e anexos privados.'],
['Agenda e notificações','calendar_events, calendar_deliveries, calendar_event_changes, inbox_items, notification_deliveries, notification_mail_batches','Eventos, participantes/grupos, alertas, entregas e resumos.'],
['Operação','jobs, failed_jobs, audit_logs e tabelas de sessão/cache','Fila, falhas, rastreabilidade e suporte ao runtime.'],
['Estoque','categories, products, stock_movements','Camada de compatibilidade com autenticação JWT e movimentação atômica.']]
TRACE=[
['Identidade','app/Http/Controllers/Api/AdminUserController.php; FirstAccessController.php','AdministrativeProvisioningTest; ActivationStatusEmailTest'],
['Projetos/equipe','ProjectController.php; TeamController.php; ProjectTeamManagers.php','RequiredProjectManagerTest; InitialProjectTeamTest'],
['Chat e aprovação','app/Domain/Chat/ChatAccess.php; ChatController.php; ChatPage.jsx','ChatTest; scripts/test-chat.mjs'],
['Editor e original','ChatRichEditor.jsx; ChatFormattedText.jsx; chatFormatting.js','scripts/test-chat-formatting.mjs'],
['Correio e idioma','app/Mail; app/Domain/Mail; resources/views/mail','LocalizedResetMailTest; StandardizedMailTest'],
['Notificações/agenda','NotificationCenter; SendNotificationBatch; CalendarController','NotificationDeliveryTest; testes de agenda'],
['Acessibilidade/tutorial','useModalFocus.js; usePopoverPosition.js; tutorial/content.js','test-accessibility.mjs; test-public-accessibility.mjs; test-tutorial.mjs'],
['Analytics','ProjectAnalyticsController.php; scripts/project_analytics.py; PortfolioChart.jsx','FinancialReportTest; test-portfolio.mjs'],
['Implantação','Dockerfile; docker-compose.yml; routes/console.php','ProductionSchedulerTest; verify-container-tests.py; check-production.php']]


def architecture(prefix):
 return [H(1,f'{prefix} ARQUITETURA E AMBIENTE TECNOLÓGICO'),
 P('O SPOT organiza interface, API, regras de domínio e persistência no mesmo repositório, com responsabilidades separadas. O navegador apresenta o estado e envia requisições; o servidor valida e autoriza cada operação; classes de domínio e modelos implementam invariantes e transações. A integração não depende de um servidor de inferência para executar as funções centrais '+CODECITE+'.'),
 H(2,f'{prefix}.1 Componentes e versões'),
 P('A documentação oficial do Laravel descreve o ciclo de requisição e os serviços de uma aplicação PHP; o React estrutura a interface em componentes, enquanto o Vite fornece o servidor de desenvolvimento e a construção dos recursos estáticos (Laravel, 2026; Meta Platforms, [20--]; Vite, [20--]). As versões do produto são obtidas dos manifestos, lockfiles e verificações do projeto, não da versão mais recente anunciada nos sites.'),
 T('Tecnologias da versão consultada',['Camada','Tecnologia','Aplicação'],TECHNOLOGIES),
 I('Organização das principais camadas do SPOT',AS/'arquitetura.png','Elaboração própria a partir do código-fonte (Longo et al., 2026c).'),
 H(2,f'{prefix}.2 Banco relacional e concorrência'),
 P('O PostgreSQL é a persistência única da versão final. Referências a SQLite na monografia preliminar descrevem um estado anterior e foram retiradas da descrição do sistema atual. As migrations definem evolução de tabelas e relacionamentos; transações e bloqueios de linha protegem decisões, movimentações e alterações de equipe. A documentação PostgreSQL fundamenta o uso de integridade e controle transacional (PostgreSQL Global Development Group, 2026).'),
 P('Na alteração de equipe, um bloqueio no registro do projeto serializa operações que poderiam remover simultaneamente os últimos responsáveis. No chat, o par de usuários é representado por uma conversa e os pedidos pendentes são conferidos sob transação. Esses controles sustentam o comportamento verificado nos testes; não constituem, por si, um ensaio de escalabilidade '+CODECITE+'.'),
 H(2,f'{prefix}.3 Comunicação e implantação'),
 P('A aplicação principal utiliza JSON e rotas versionadas sob /api/v1, com sessão, cookies e proteção CSRF nas operações mutáveis. A compatibilidade por token e o estoque utilizam JWT em suas rotas. OAuth conecta provedores externos; sua homologação requer credenciais e permissões reais. Falhas de validação são apresentadas próximas aos formulários e não devem expor SQL, traces ou segredos.'),
 P('O Docker Compose descreve serviços e volumes da aplicação (Docker, 2026). O empacotamento do SPOT inclui web, worker e scheduler, além do PostgreSQL. E-mails usam a fila emails; atividades e limpezas periódicas são agendadas dentro do processo PHP. O arquivo .env e dados de usuários não integram a imagem '+CODECITE+'.')]


def implementation(prefix):
 return [H(1,f'{prefix} REQUISITOS, REGRAS E FUNCIONALIDADES'),
 H(2,f'{prefix}.1 Requisitos rastreáveis'),
 P('Os requisitos foram derivados das necessidades registradas pela equipe e refinados por comportamento observável no código. Requisito funcional descreve uma operação ou restrição; requisito não funcional trata de qualidades e condições de uso. A entrega inclui autenticação, governança, projetos, tarefas, horas, finanças, comunicação e operação, com rastreabilidade entre regra, componente e teste '+CODECITE+'.'),
 T('Regras centrais verificadas',['Identificação','Tema','Regra'],RULES),
 H(2,f'{prefix}.2 Identidade, convite e primeiro acesso'),
 P('O primeiro acesso é iniciado por provisionamento administrativo. O link utiliza token com hash persistido, validade e controle de uso. A identificação da ativação compara somente o e-mail com a conta associada ao convite, desconsiderando caixa e espaços nas extremidades; não exige que o nome digitado coincida com o cadastro. O nome administrativo é preservado. Quando o e-mail diverge, a interface orienta: Por gentileza, insira o e-mail correspondente ao do convite enviado! A validade do token e a confirmação da senha continuam obrigatórias.'),
 P('Uma nova entrada da conta em aguardando ativação revoga links anteriores ainda não usados, cria outro token e agenda a mensagem após confirmar a transação. A validade da ativação administrativa é de 48 horas. Edição de cargo ou perfil de uma conta já pendente não é um novo evento de reenvio. A recuperação de senha é um fluxo diferente: o link vale 60 minutos e há intervalo de 60 segundos entre gerações para a mesma conta '+CODECITE+'.'),
 H(2,f'{prefix}.3 Projetos, equipe e execução'),
 P('O cadastro v1 exige gerente do projeto elegível. O usuário selecionado entra na equipe como manager, sem duplicação se também constar na seleção inicial. Equipes, atribuições e remoções de membros preservam ao menos um gerente ativo elegível, sincronizando o campo de referência quando necessário. Perfis de administrador, gerente administrador e gerente podem ser elegíveis para a função, conforme as regras do servidor.'),
 P('Projetos concentram contrato, situação, datas, membros e planejamento. Tarefas oferecem visualizações de trabalho e apontamentos; tarifas possuem vigência, e despesas passam por revisão. Finalização e reabertura seguem autorização e preservação do histórico. O módulo de documentos mantém suas regras de privacidade: associar um arquivo a projeto ou tarefa não equivale a compartilhá-lo com toda a equipe '+CODECITE+'.'),
 H(2,f'{prefix}.4 Chat e aprovação direcionada'),
 P('O chat é individual e restrito à empresa atual. Há acesso direto quando os dois usuários possuem participação ativa, não revogada, com papel analyst ou manager em um mesmo projeto. Fora desse vínculo, o formulário solicita pessoa, motivo e o destinatário em Enviar aprovação para. A lista contém administradores e gerentes administradores ativos e elegíveis da mesma empresa, excluindo o solicitante; podem estar fora do projeto.'),
 P('O campo assigned_reviewer_id registra quem recebeu o novo pedido; reviewer_id registra quem decidiu. Somente o escolhido recebe o aviso e pode aprovar ou recusar. A autorização vale para o par, e não libera a leitura de conversas de terceiros. Pedidos antigos sem atribuição mantêm compatibilidade com o fluxo anterior. Uma pendência por par impede duplicação, inclusive em sentido inverso; pedidos recusados ou revogados permitem nova tentativa conforme a elegibilidade '+CODECITE+'.'),
 P('Apenas o autor pode editar uma mensagem, até 24 horas do envio original. As revisões são preservadas e uma versão é conferida para evitar sobrescrita concorrente. Anexos usam acesso autorizado e descrições de imagem podem ser informadas. O editor fornece formatação e atalhos; Enter envia e Ctrl + Shift + Enter insere nova linha. A existência de autorização de revisão não concede acesso ao conteúdo de uma conversa alheia.'),
 I('Solicitação de chat com escolha do aprovador',AS/'chat-aprovador.png','Interface do build local, com massa sintética e API simulada; captura de teste, 10 nov. 2026.'),
 H(2,f'{prefix}.5 Agenda, notificações e e-mails'),
 P('A agenda reúne eventos internos e conexões externas disponíveis. Grupos, participantes, fuso e detalhes são validados. As notificações consideram preferências por canal, silêncio, frequência e deduplicação. Resumos diários ou semanais e envios imediatos são processados em lotes e filas, com tratamento de falha e nova tentativa '+CODECITE+'.'),
 P('Ativação, convite, recuperação e notificações compartilham o layout de correio: cabeçalho Spot, título, ação, link alternativo e rodapé, com alternativa em texto simples. Textos de estrutura possuem pt/en/es; dados escritos por pessoas são preservados e escapados no HTML. Disponibilidade de SMTP, reputação do remetente e apresentação em clientes de e-mail reais exigem homologação separada.'),
 H(2,f'{prefix}.6 Portfólio e processamento analítico'),
 P('Os relatórios consolidam situação, custos, receitas, margens, horas e despesas, usando processos Python com pandas. A biblioteca fornece estruturas e operações de análise de dados (Pandas Development Team, 2026). No SPOT, os cálculos dependem dos registros e permissões existentes; falta de tarifa ou base de cálculo não é convertida automaticamente em evidência de lucro ou desempenho.'),
 P('Painéis permitem indicadores e diferentes visualizações, agregações e exportação CSV. Resultados parciais são identificados. Uma falha do detalhamento financeiro é tratada localmente sem cobrir todos os outros indicadores com uma tela global de erro. O resultado financeiro é gerencial, depende da qualidade dos dados e não equivale a demonstração contábil auditada '+CODECITE+'.'),
 H(2,f'{prefix}.7 Idiomas, acessibilidade e tutorial'),
 P('A interface dispõe de português, inglês e espanhol, ajustes de fonte, escala e tema, indicação de foco e respeito à redução de movimento e cores forçadas. O objetivo de acessibilidade é WCAG 2.2 nível AA, referência que abrange diferentes necessidades de uso; resultados automatizados não substituem avaliação humana nem certificam conformidade integral (World Wide Web Consortium, 2024).'),
 P('O tutorial é opcional e seleciona as etapas conforme permissões e disponibilidade do chat. Explica navegação, projetos, execução, segurança e solicitações, sem enviar formulários de negócio. A mudança de idioma dentro do passeio não grava alterações na conta. A posição dos destaques é verificada para telas disponíveis e áreas restritas. Nomes e conteúdos pessoais usam marcação para impedir tradução automática da interface '+CODECITE+'.'),
 H(2,f'{prefix}.8 Tradução automática e escopo de IA'),
 P('As bandeiras no cabeçalho da conversa escolhem apenas o destino da tradução da mensagem, sem alterar o idioma global. O texto traduzido é apresentado no balão, identificado como automático, com acesso ao original. A integração opcional usa o endpoint de tradução do LibreTranslate (LibreTranslate, [20--]). Credenciais e endereço do serviço permanecem no servidor.'),
 P('É necessário distinguir tradução automática de agentes de planejamento ou geração de conteúdo. A versão final não demonstra um agente generativo para estimar projetos, redistribuir equipes ou tomar decisões gerenciais. Automação de notificações, regras determinísticas e consolidação com pandas também não são, por si, implementação de inteligência artificial. A pesquisa de tecnologias de IA é referência de possibilidades, não prova de entrega dessas funções.')]


def data_api(prefix):
 blocks=[H(1,f'{prefix} MODELO DE DADOS E CONTRATOS DE API'),
 P('O esquema é descrito por migrations versionadas. As entidades agrupam identidade, organização, trabalho, finanças, comunicação e operação. O quadro a seguir é um mapa de domínio; o apêndice de migrations e os arquivos do repositório são a referência para nomes, tipos, nulabilidade, índices e chaves exatos '+CODECITE+'.'),
 T('Grupos de dados',['Domínio','Entidades principais','Responsabilidade'],DATA_GROUPS),
 H(2,f'{prefix}.1 Vínculos e integridade'),
 P('Uma empresa limita as contas e os projetos visíveis. Projetos relacionam responsáveis e membros; tarefas, horas e despesas referenciam o contexto de execução. Conversas representam um par ordenado de usuários, enquanto solicitações, mensagens, revisões e anexos mantêm relações separadas. Tokens de acesso não são armazenados em texto simples. O responsável escolhido para aprovação não é confundido com o usuário que concluiu a decisão.'),
 P('A preservação do histórico é parte do domínio: revogar autorização não remove mensagens; finalização limita operações sem apagar a trajetória do projeto; exclusão de registros com dependências segue restrições e regras aplicáveis. A normalização lógica evita repetir a mesma credencial, perfil ou estado em entidades sem necessidade, embora haja snapshots controlados para rastreabilidade de valores e decisões.'),
 H(2,f'{prefix}.2 Contratos e erros'),
 P(f'O inventário exportado pelo Laravel contém {len(ROUTES)} rotas no ambiente consultado, incluindo infraestrutura e framework. Esse total não representa {len(ROUTES)} funcionalidades de negócio. O apêndice discrimina rotas da aplicação e o manifesto preserva o critério de extração.'),
 P('Respostas de validação usam a estrutura errors e os códigos HTTP compatíveis com o problema. Requisições sem autorização, recursos não visíveis, conflito de estado e indisponibilidade têm tratamentos distintos. O cliente de sessão inicializa CSRF antes das mutações e só publica eventos de atualização após sucesso; logout não dispara recarga automática de dados privados. Validações críticas continuam no servidor '+CODECITE+'.'),
 T('Rastreabilidade entre implementação e testes',['Área','Artefatos','Verificação'],TRACE)]
 return blocks


def quality(prefix):
 return [H(1,f'{prefix} VERIFICAÇÃO, RESULTADOS E LIMITES'),
 P('A evidência técnica corresponde à execução registrada em 10 de novembro de 2026. O código consultado foi identificado por commit e acompanhado de logs e inventários. Resultados antigos de 52 testes e 262 asserções pertencem a uma edição anterior e não descrevem a versão final. A quantidade de testes não deve ser interpretada como quantidade de funcionalidades nem como cobertura total '+CODECITE+'.'),
 T('Síntese da verificação da versão final',['Verificação','Resultado','Limite'],[
 ['PHPUnit local','256 testes; 3.487 asserções; aprovado','Schema spot_testing; dados sintéticos.'],
 ['PHPUnit em contêiner','256 testes; 3.487 asserções; aprovado','PHP 8.3 e PostgreSQL descartável; serviços externos simulados.'],
 ['Navegador','36 suítes aprovadas','Chromium; não representa todos os navegadores.'],
 ['Responsividade','323 verificações aprovadas','320–1440 px; orientação vertical/horizontal; leitura 100%/120%.'],
 ['Tutorial','756 verificações; sem erros','Regras de navegação, permissões, idioma e ausência de mutações de negócio.'],
 ['Idiomas','144 verificações; sem falhas ou apontamentos','Cobertura dos percursos automatizados; não certifica toda expressão possível.'],
 ['Chat/editor','170 verificações; formatação 3 × 100','Inclui atalhos, destinatário e preservação do original.'],
 ['Correio','36 prévias de layout','Seis variantes; três idiomas; duas larguras; sem destinatários reais.'],
 ['Build e dependências','Build e auditorias sem avisos na rodada final','Situação verificada na data de corte, não garantia permanente.'],
 ['Docker','Imagem construída e rotas públicas com HTTP 200','Não é ensaio de certificado, rede de produção ou SMTP real.'],
 ]),
 H(2,f'{prefix}.1 Cobertura comportamental'),
 P('Os testes incluem autorização, isolamento de empresa, identidade, ativação e reenvio, recuperação, mudanças de projeto, gerente obrigatório, acesso a documentos, despesas, tarifas e notificações. No chat, verificam participação, aprovação selecionada, autoaprovação proibida, duplicatas, edição, versões, anexos e revogação. Nos testes de correio, a entrega usa transporte em memória; não há envio para destinatários reais.'),
 P('Na interface, foram usados nomes longos, tela estreita, orientação horizontal, tema, escala de leitura, fluxo por teclado, foco em modais, retorno de foco e tradução. O teste do editor foi repetido após corrigir a disputa de foco. O passeio do tutorial foi percorrido segundo permissões, inclusive quando relatórios não estão disponíveis '+CODECITE+'.'),
 H(2,f'{prefix}.2 Ameaças à validade e critérios de aceite'),
 P('A avaliação automatizada comprova apenas os cenários exercitados no ambiente descrito. Não houve comprovação empírica de redução de custo, aumento de produtividade ou satisfação após implantação. Não foram realizados testes de carga com volume real, auditoria ofensiva completa, homologação com todos os clientes de e-mail ou avaliação humana integral com leitor de tela.'),
 P('Para aceite operacional, devem ser confirmados domínio e HTTPS, segredos, banco e migrations, armazenamento, backup e restauração, workers e scheduler. Recursos Google, Microsoft, SMTP e tradução dependem das permissões e serviços reais. A existência de uma integração no código não equivale à autorização de um tenant nem à disponibilidade do provedor. Não se declara conformidade integral WCAG ou liberação incondicional para produção.')]


def operation(prefix):
 return [H(1,f'{prefix} IMPLANTAÇÃO E CONTINUIDADE'),
 H(2,f'{prefix}.1 Preparação e configuração'),
 P('A implantação proposta usa Docker Compose com serviços web, worker, scheduler e PostgreSQL. O domínio precisa apontar para o servidor e o proxy depende de condições reais para emitir HTTPS. O arquivo .env.production é preenchido a partir do modelo; APP_KEY e JWT_SECRET devem ser configuradas e preservadas nas atualizações. A chave JWT é necessária para o estoque e a autenticação por token. Segredos não integram o Git nem os documentos de consulta.'),
 P('O build inclui os arquivos estáticos, dependências PHP de produção e ambiente Python para os relatórios. Backups, uploads locais, evidências de testes, .env e public/hot são excluídos do artefato. Uploads reais permanecem nos volumes de armazenamento. Migrations são aplicadas após backup e revisão; o seed da conta inicial não deve ser executado como rotina de atualização '+CODECITE+'.'),
 H(2,f'{prefix}.2 Validação do servidor de destino'),
 P('O verificador scripts/check-production.php é somente leitura: verifica ambiente, debug, origem HTTPS, cookies, chaves, correio configurado, existência de assets, diretórios graváveis e conexão/migrations. Sua saída não expõe credenciais nem envia mensagens. Mesmo com saída zero, é necessária a homologação externa e a conferência dos responsáveis nos dados transferidos.'),
 P('A revisão local identificou três projetos antigos sem o campo de gerente do projeto preenchido, embora todos possuíssem gerente ativo na equipe. Não houve escolha automática de responsáveis. Esse achado refere-se ao conjunto local revisado e deve ser reavaliado na base que será implantada; não é uma característica fixa do software.'),
 H(2,f'{prefix}.3 Operação e manutenção'),
 P('Workers devem consumir emails e default, e o scheduler deve ser executado por minuto. Há controles de nova tentativa e consulta de falhas. Backups devem incluir banco e arquivos, com restauração efetivamente testada. Uma mudança de chave pode afetar dados e jobs criptografados; atualizar a aplicação não exige substituir as chaves existentes.'),
 P('A manutenção deve preservar rastreabilidade entre alteração, teste, documentação e release. A equipe ainda precisa concluir a validação humana de acessibilidade, os testes com provedores reais e a observação sob carga de uso. O custo de infraestrutura, correio, manutenção e suporte continua existindo; ausência de uma assinatura de software de terceiros não significa operação sem custos.'),
 H(2,f'{prefix}.4 Consulta ao código e reprodução'),
 P('O repositório abaixo permite consultar a estrutura, as regras, as migrations e os testes. A referência de versão evita atribuir à monografia funcionalidades introduzidas posteriormente. A data de acesso foi registrada e a disponibilidade de consulta depende das permissões configuradas no GitHub.'),
 L('Repositório do SPOT: '+REPO,REPO), L('Versão do código analisada: '+COMMIT,PERMALINK),
 P('O commit consultado corresponde ao HEAD remoto confirmado durante a revisão. A documentação gerada nesta atualização pode ainda estar como alteração local; ela não é considerada publicada apenas por existir na pasta do projeto. O manifesto de fontes e a pasta de evidências registram a procedência da edição.')]


def appendices():
 app_routes=[r for r in ROUTES if r['uri'].startswith(('api/','auth/','first-access','accept-invitation','forgot-password','reset-password','profile'))]
 blocks=[H(1,'APÊNDICE A — INVENTÁRIO DAS ROTAS DA APLICAÇÃO'),P('O quadro foi gerado a partir de artisan route:list --json. Rotas de infraestrutura fora dos prefixos relacionados à aplicação não são reproduzidas; o inventário integral permanece no arquivo de fontes documentais.'),T('Rotas inventariadas',['Método','URI','Nome'],[[r['method'],r['uri'],r.get('name') or '—'] for r in app_routes])]
 files=sorted((ROOT/'database/migrations').glob('*.php'))
 blocks += [H(1,'APÊNDICE B — EVOLUÇÃO DO ESQUEMA E ARTEFATOS'),P('As migrations abaixo registram a evolução disponível na revisão; a ordem é a do nome dos arquivos, e não uma afirmação de que todas foram introduzidas na mesma etapa. A criação de uma coluna não prova a homologação do recurso correspondente.'),T('Migrations disponíveis',['Arquivo'],[[p.name] for p in files]),T('Documentos operacionais complementares',['Arquivo','Finalidade'],[['docs/chat.md','Regras e testes do chat.'],['docs/padrao-emails.md','Modelos e prévias de mensagens.'],['docs/acessibilidade-e-inclusao.md','Escopo e limites das verificações.'],['docs/revisao-producao-2026-11-10.md','Evidência e condições para homologação.'],['docs/implantacao-docker.md','Procedimento de implantação.'],['docs/fontes-documentais/manifesto-versao-final.json','Commit, hashes e fontes desta edição.']])]
 return blocks


def research():
 blocks=[H(1,'4 LEVANTAMENTO DE PESQUISA DE CAMPO'),H(2,'4.1 Procedimento e base documental'),
 P('O levantamento foi caracterizado no documento fornecido pela equipe como pesquisa aplicada, descritiva, quali-quantitativa, conduzida por formulário eletrônico entre 18 de março e 24 de agosto de 2026. O texto declara 15 questões fechadas, uma aberta e 94 respostas válidas. A amostra foi de conveniência, não probabilística; os resultados descrevem participantes e não permitem generalização estatística a todas as empresas '+RESEARCHCITE+'.'),
 P('A edição final preserva as frequências recuperadas no capítulo da pesquisa e no arquivo dados_pesquisa.json. Não houve acesso à planilha individual de respostas. Treze conjuntos gráficos foram recuperados; o material não permite reconstruir todas as perguntas declaradas separadamente. Os totais de algumas figuras diferem de 94, sem indicação suficiente para distinguir não resposta, filtro ou erro documental.'),
 P('Os percentuais desta edição usam 94 como base declarada, com uma casa decimal. Quando alternativas não são exclusivas, as frequências não devem somar o total de participantes. Nos demais casos, a diferença até 94 é explicitada como não discriminada e não é convertida em uma categoria inventada.'),H(2,'4.2 Resultados quantitativos')]
 comments={
 'idade':'A distribuição permite discutir diversidade etária de uso; não mede habilidades digitais individuais nem autoriza definir uma interface para uma única faixa. O documento também informa 88 participantes empregados, equivalentes a 93,6% da base, sem detalhar os demais estados de emprego.',
 'atuacao':'A maioria das pessoas nas categorias exibidas não atua diretamente em gestão de projetos. Isso fundamenta a prioridade de linguagem clara e tutorial, mas não comprova que a interface final foi validada por esse grupo.',
 'ferramentas':'As menções indicam coexistência de ferramentas de escritório e soluções específicas. Trata-se de uma pergunta de menções não exclusivas; não há base para inferir migração integral dos participantes para o SPOT.',
 'software':'As respostas mostram situações diversas de disponibilidade de software. A distribuição não identifica quais ferramentas atendem a cada organização nem constitui comparação de custo total.',
 'satisfacao':'A presença de satisfação parcial e ausência de ferramenta sugere oportunidades de melhoria. Não é avaliação posterior à implantação do SPOT e não deve ser apresentada como índice de satisfação com o produto.',
 'interface':'A percepção de interface é um dado descritivo do cenário anterior relatado. A responsividade e o tutorial do SPOT são decisões de implementação, cuja eficácia com usuários precisa de ensaio próprio.',
 'externos':'Os fluxos externos motivam observar fronteiras de acesso e responsabilidade. As alternativas foram mantidas literalmente, sem converter o termo absolutamente em uma medida numérica de intensidade.',
 'internos':'A ocorrência de projetos internos sustenta o tratamento de equipes e vínculos. O resultado não define por si as permissões; estas foram especificadas nas regras de negócio e verificadas no código.',
 'organizacao':'A dificuldade declarada de organização apoia a prioridade de projetos, tarefas e acompanhamento. Não foi medido o tempo antes/depois da adoção da solução.',
 'alinhamento':'A distribuição de respostas sobre alinhamento não estabelece causalidade. O chat com aprovação específica responde à necessidade de comunicação sem liberar acesso irrestrito entre empresas.',
 'faturamento':'A percepção de relação entre organização e faturamento não é uma mensuração financeira. Os indicadores implementados oferecem apoio gerencial, sem provar aumento de receita decorrente do software.',
 'custos':'A questão de custos adicionais motivou a rastreabilidade de despesas e planejamento. As frequências não permitem calcular perdas monetárias nem retorno do investimento.',
 'necessidade':'As respostas sobre necessidade de solução dedicada orientam a investigação, sem constituir estimativa de adesão ao SPOT. Não há cruzamento individual disponível para afirmar preferência em subgrupos específicos.'}
 for index,q in enumerate(SURVEY['questoes'],1):
  total=sum(q['valores']);multiple=bool(q.get('multipla') or q['id']=='ferramentas')
  rows=[[lab,str(v),f'{v/94*100:.1f}%'.replace('.',',')] for lab,v in zip(q['rotulos'],q['valores'])]
  blocks += [H(3,f'4.2.{index} {q["titulo"]}'),T(q['titulo'],['Alternativa','Frequência','% da base 94'],rows,'Dados da pesquisa da equipe (Longo et al., 2026a); percentuais calculados nesta edição.')]
  chart=D/'tcc/assets/graficos'/f'{q["id"]}.png'
  if chart.exists():blocks.append(I(q['titulo'],chart,'Dados de Longo et al. (2026a), reconstituídos pela equipe; base declarada de 94 participantes.'))
  blocks.append(P(('As frequências são menções não exclusivas e somam '+str(total)+'.' if multiple else f'As categorias exibidas somam {total}; a diferença de {94-total} em relação à base de 94 não foi discriminada na fonte.' if total!=94 else 'As categorias exibidas somam os 94 participantes declarados.')+' '+comments[q['id']]))
 blocks += [H(2,'4.3 Resultados qualitativos e inconsistências'),T('Sugestões abertas recuperadas',['Tema','Frequência publicada'],[[q['tema'],str(q['frequencia'])] for q in SURVEY['qualitativa']['categorias']],'Documento da pesquisa (Longo et al., 2026a); frequências temáticas, não falas literais.'),
 P('O texto original declara 57 comentários, enquanto a tabela temática soma 49 frequências. Sem os registros individuais e o procedimento de codificação, não é possível reconciliar as oito unidades de diferença nem verificar sobreposição. Não foram criadas categorias ou depoimentos para completar o total.'),
 H(2,'4.4 Relação entre achados e requisitos'),
 T('Da pesquisa às decisões de implementação',['Achado documental','Resposta técnica','Evidência e limite'],[['Interface e organização','Navegação, tarefas e tutorial em três idiomas','Testes de interface; eficácia com público real pendente.'],['Integração e comunicação','Correio, agenda, notificações e chat com aprovador','Fluxos testados com serviços simulados; homologação externa pendente.'],['Controle financeiro','Horas, tarifas, despesas e portfólio','Cálculos e acesso cobertos por testes; não demonstra ganho financeiro.'],['Automação e IA','Regras de notificação e tradução opcional','Não se afirma implementação de agente gerencial ou IA generativa.']],'Elaboração própria a partir da pesquisa (Longo et al., 2026a) e do código (Longo et al., 2026c).')]
 return blocks


def mono_blocks():
 return [H(1,'1 INTRODUÇÃO'),
 P('O sistema denominado Sistemas de Projetos, Organização e Tarefas (SPOT) é uma aplicação web desenvolvida no contexto do Curso de Informática do Colégio Dr. Clóvis Bevilacqua para apoiar necessidades de gestão relatadas no trabalho da Computécnica. O problema reúne organização de atividades, delimitação de responsabilidades, comunicação e acompanhamento dos custos de execução. A monografia preliminar registra a intenção de modernizar a experiência de uso e reduzir a dependência de soluções externas; a edição final descreve o que o código efetivamente demonstra (Longo et al., 2026b).'),
 H(2,'1.1 Problema de pesquisa'),P('Como integrar o planejamento, a execução e o acompanhamento gerencial de projetos em uma aplicação web que preserve responsabilidades e restrições de acesso entre pessoas e equipes? A pergunta não pressupõe que uma nova interface, isoladamente, aumente a produtividade. A resposta é examinada pela especificação, implementação e verificação de comportamentos, e não por uma promessa de resultado financeiro.'),
 H(2,'1.2 Objetivo geral'),P('Desenvolver e documentar uma aplicação integrada de projetos, organização e tarefas, com regras de identidade e autorização, execução rastreável e informações de apoio à gestão, verificando a implementação por testes reproduzíveis.'),
 H(2,'1.3 Objetivos específicos'),P('Identificar necessidades na pesquisa documental e de campo; modelar entidades e responsabilidades; implementar autenticação, projetos, equipes, tarefas, horas e despesas; organizar comunicação, notificações e agenda; consolidar indicadores; disponibilizar recursos de idiomas e acessibilidade; verificar regras e interface; e registrar condições de implantação e continuidade.'),
 H(2,'1.4 Justificativa e delimitação'),P('A contribuição é a tradução de necessidades operacionais em dados relacionados e regras verificáveis. Projetos precisam de responsáveis; comunicação fora de um time precisa de autorização; custos dependem de registros e tarifas. O produto busca coordenar esses elementos em um fluxo coerente. A pesquisa fornece contexto, mas não substitui avaliação posterior de uso.'),
 P('O recorte técnico é o commit '+COMMIT+', confirmado no repositório remoto em 10 de novembro de 2026. São descritos recursos presentes e verificados nessa revisão. Integrações que dependem de terceiros são distinguidas de homologação real; não se afirma implantação concluída nem conformidade integral de acessibilidade. O estudo de soluções e IA é preservado como fundamentação, com atualização da relação entre propostas e entrega.'),
 H(2,'1.5 Organização do trabalho'),P('O texto apresenta fundamentos e estudo de soluções, metodologia, pesquisa de campo, arquitetura, funcionalidades, modelo de dados, verificação, implantação e conclusão. Os apêndices permitem localizar rotas e migrations. As referências reúnem as fontes efetivamente consultadas, com acesso ao repositório e à versão do código.'),
 H(1,'2 FUNDAMENTAÇÃO E ESTUDO DE SOLUÇÕES'),H(2,'2.1 Gestão integrada e rastreabilidade'),P('A coordenação de projetos depende de informações consistentes sobre escopo, pessoas, prazos, execução e custos. Neste trabalho, integração significa relacionar esses registros e aplicar regras de acesso em suas operações. Uma visualização pode resumir dados, mas a coerência depende de sua origem, validação e atualização; por isso o desenho do sistema inclui histórico, auditoria e vinculação entre entidades.'),
 H(2,'2.2 Aplicações web, componentes e persistência'),P('A separação entre componentes de interface e regras de servidor permite modificar apresentação sem deslocar decisões de segurança ao navegador. Rotas e validação no Laravel, componentes React e construção com Vite dão suporte a essa organização; o projeto relacional e o controle transacional mantêm os vínculos persistidos (Laravel, 2026; Meta Platforms, [20--]; Vite, [20--]; PostgreSQL Global Development Group, 2026).'),
 H(2,'2.3 Levantamento documental de sistemas'),P('O estudo preliminar abrangeu ferramentas de gestão e serviços de infraestrutura. Nesta revisão, foram consultadas páginas oficiais para registrar a finalidade geral das soluções sem atribuir preços, superioridade ou desempenho não medidos. A descrição é documental; não houve ensaio comparativo controlado, contratação nem avaliação de todos os planos comerciais.'),
 T('Soluções consultadas e papel no estudo',['Solução','Aspecto observado','Fonte'],[['monday.com','Organização de trabalho, painéis e integrações','monday.com ([20--])'],['Asana','Coordenação de trabalho e fluxos de equipe','Asana (2026)'],['Jira','Organização e acompanhamento de trabalho em equipes','Atlassian (2026a)'],['Trello','Quadros, organização de tarefas e automações','Atlassian (2026b)'],['ClickUp','Reunião de projetos, documentos e comunicação','ClickUp (2026)'],['Notion','Organização de conhecimento e trabalho','Notion (2026)'],['AWS','Infraestrutura e serviços de nuvem; não é equivalente direto a um gerenciador de tarefas','Amazon Web Services (2026)']],'Elaboração própria a partir das páginas oficiais indicadas nas referências, consultadas em 10 nov. 2026.'),
 P('O SPOT não é apresentado como substituto universal dessas ferramentas. Sua contribuição é o ajuste das regras ao contexto estudado, com rastreabilidade entre cadastro, permissões, execução e finanças. O estudo de mercado orienta critérios de organização, sem sustentar comparação causal de qualidade ou custo.'),
 H(2,'2.4 Inteligência artificial, automação e tradução'),P('O estudo original de tecnologias de IA não deve ser confundido com uma lista de recursos entregues. A implementação final utiliza regras determinísticas e processamento analítico; dispõe também de tradução automática opcional de mensagens por serviço configurado. Não foram demonstrados agentes generativos de planejamento, predição validada de custos ou decisões gerenciais autônomas. A categoria automação e IA da pesquisa continua preservada como sugestão, sem alteração do dado original '+RESEARCHCITE+'.'),
 H(2,'2.5 Acessibilidade e documentação'),P('O desenho acessível considera modos distintos de interação e compreensão, tendo WCAG 2.2 AA como objetivo e avaliação humana como etapa necessária (World Wide Web Consortium, 2024). A organização deste texto utiliza as normas da Associação Brasileira de Normas Técnicas (ABNT) para apresentação, citações, referências, numeração, sumário e resumo (Associação Brasileira de Normas Técnicas, 2012a, 2012b, 2021, 2023, 2024, 2025), apoiadas pelas orientações institucionais de bibliotecas universitárias (Universidade Federal do Espírito Santo, 2026; Universidade Federal do ABC, [20--]).'),
 H(1,'3 METODOLOGIA'),H(2,'3.1 Natureza e procedimentos'),P('O trabalho reúne desenvolvimento aplicado de software, pesquisa descritiva com participantes e estudo documental. O levantamento com usuários foi recebido como fonte primária da equipe; o estudo de ferramentas foi confrontado com páginas oficiais; a implementação foi analisada por rotas, migrations, controladores, componentes, dependências e testes. A distinção entre essas fontes evita apresentar opinião de participante como resultado de uma medição de software.'),
 H(2,'3.2 Critério de versão e evidência'),P('A revisão técnica utiliza o commit '+COMMIT+' e evidências registradas em output/production-review. A documentação inclui inventário de rotas e manifesto das fontes. Um teste aprovado é tratado como evidência do cenário exercitado. Build, análise de dependências e smoke test de imagem são verificações diferentes e não equivalem a homologação completa '+CODECITE+'.'),
 H(2,'3.3 Cuidados éticos e limitações'),P('Foram preservados os totais e as inconsistências documentais da pesquisa. Não houve reconstrução de respostas individuais nem criação de depoimentos. As capturas da aplicação empregam dados sintéticos e APIs simuladas, sem expor credenciais ou mensagens reais. Resultados financeiros, produtividade e satisfação pós-implantação não foram medidos, de modo que não se atribui ao produto um efeito que não foi observado.'),
 ]+research()+architecture('5')+implementation('6')+data_api('7')+quality('8')+operation('9')+[
 H(1,'10 CONSIDERAÇÕES FINAIS'),P('A versão final do SPOT materializa uma aplicação integrada para identidade, projetos, equipes, execução, finanças e comunicação. A governança é parte funcional da solução: projeto exige responsável, acesso é verificado no servidor e a conversa externa depende de autorização direcionada. O resultado acadêmico é uma base implementada, documentada e verificável, relacionada às necessidades e sugestões da pesquisa.'),
 P('A verificação registrou aprovação dos testes de servidor e navegador, construção do frontend e da imagem, e auditorias de dependências sem avisos na rodada final. Esses resultados demonstram os cenários cobertos, com dados sintéticos e serviços simulados; não provam ausência de defeitos, ganho financeiro ou conformidade integral das diretrizes de acessibilidade. A revisão também identifica ações de configuração, responsáveis antigos e serviços reais antes de liberar produção.'),
 P('A continuidade deve incluir homologação com usuários e tecnologias assistivas, validação das integrações no domínio definitivo e observação sob carga. O repositório e o identificador de versão permitem consultar a implementação e acompanhar evolução posterior sem confundi-la com o recorte desta monografia. Dessa forma, o trabalho articula pesquisa, construção e evidência, preservando os limites de cada uma dessas dimensões.')]+appendices()


def technical_blocks():
 return [H(1,'1 IDENTIFICAÇÃO E ESCOPO'),P('Este relatório técnico complementa a monografia do SPOT e descreve a versão consultada em 10 de novembro de 2026. A base é o commit '+COMMIT+', disponível no repositório configurado pela equipe. Foram considerados código, configuração, migrations, rotas, testes e evidências de prontidão; arquivos de pesquisa e versões anteriores permanecem preservados.'),P('As descrições indicam comportamento presente e condições de operação. Não se declara publicação concluída, entrega de IA gerencial, resultado financeiro causado pelo produto ou conformidade integral de acessibilidade. O sistema utiliza PostgreSQL; referências anteriores a SQLite não descrevem a versão atual.'),H(1,'2 ORGANIZAÇÃO DOCUMENTAL E NORMALIZAÇÃO'),P('A apresentação adota folha A4, margens superior/esquerda de 3 cm e inferior/direita de 2 cm, corpo Arial 12, espaçamento de 1,5 e organização em seções. Citações são indiretas no sistema autor-data; referências são alinhadas à esquerda e em espaçamento simples. Resumo, sumário e numeração seguem as normas correspondentes. Não foram inventados dados de aprovação institucional.'),P('Adotam-se as normas da Associação Brasileira de Normas Técnicas (ABNT). As referências consideram ABNT NBR 14724:2024, NBR 6023:2025, NBR 10520:2023, NBR 6024:2012, NBR 6027:2012 e NBR 6028:2021 (Associação Brasileira de Normas Técnicas, 2012a, 2012b, 2021, 2023, 2024, 2025). As bibliotecas UFES e UFABC foram consultadas para confirmar as atualizações e orientações de normalização (Universidade Federal do Espírito Santo, 2026; Universidade Federal do ABC, [20--]).')]+architecture('3')+implementation('4')+data_api('5')+quality('6')+operation('7')+[H(1,'8 SÍNTESE TÉCNICA'),P('O código e os percursos automatizados estão verificados para homologação, com correções de dependências, isolamento de testes, tutorial, editor e empacotamento. A decisão de liberar produção depende de configurar o ambiente final, conferir os responsáveis antigos e validar integrações e operação reais. O roteiro em docs/implantacao-docker.md e o verificador de produção auxiliam essa etapa sem expor segredos nem enviar mensagens por conta própria.')]+appendices()


SIGLAS=[('ABNT','Associação Brasileira de Normas Técnicas'),('API','Application Programming Interface'),('CSRF','Cross-Site Request Forgery'),('CSV','Comma-Separated Values'),('HTML','Hypertext Markup Language'),('HTTP','Hypertext Transfer Protocol'),('HTTPS','Hypertext Transfer Protocol Secure'),('IA','Inteligência artificial'),('JSON','JavaScript Object Notation'),('JWT','JSON Web Token'),('PHP','PHP: Hypertext Preprocessor'),('SMTP','Simple Mail Transfer Protocol'),('SQL','Structured Query Language'),('TCC','Trabalho de Conclusão de Curso'),('TI','Tecnologia da informação'),('WCAG','Web Content Accessibility Guidelines')]


def field(p,instruction):
 r=OxmlElement('w:r');begin=OxmlElement('w:fldChar');begin.set(qn('w:fldCharType'),'begin');r.append(begin);p._p.append(r)
 r=OxmlElement('w:r');t=OxmlElement('w:instrText');t.set(qn('xml:space'),'preserve');t.text=instruction;r.append(t);p._p.append(r)
 r=OxmlElement('w:r');sep=OxmlElement('w:fldChar');sep.set(qn('w:fldCharType'),'separate');r.append(sep);p._p.append(r)
 p.add_run('')
 r=OxmlElement('w:r');end=OxmlElement('w:fldChar');end.set(qn('w:fldCharType'),'end');r.append(end);p._p.append(r)

def reference_title_parts(title):
 return (title,'','') if title.startswith('ABNT NBR ') else title.partition(':')


def plain_text(p,text):
 for part in re.split(r'(et al\.)',text):
  run=p.add_run(part)
  if part=='et al.':run.italic=True
 return p


def link(p,text,url):
 rid=p.part.relate_to(url,'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',is_external=True)
 h=OxmlElement('w:hyperlink');h.set(qn('r:id'),rid);r=OxmlElement('w:r');props=OxmlElement('w:rPr');c=OxmlElement('w:color');c.set(qn('w:val'),'000000');props.append(c);u=OxmlElement('w:u');u.set(qn('w:val'),'single');props.append(u);r.append(props);t=OxmlElement('w:t');t.text=text;r.append(t);h.append(r);p._p.append(h)

def dimensions(section):
 section.page_width=Cm(21);section.page_height=Cm(29.7);section.top_margin=Cm(3);section.bottom_margin=Cm(2);section.left_margin=Cm(3);section.right_margin=Cm(2);section.header_distance=Cm(2);section.footer_distance=Cm(1.2)

def set_styles(doc):
 for s in doc.styles:
  if s.type==1:
   s.font.name='Arial';s.font.size=Pt(12);s.font.color.rgb=RGBColor(0,0,0)
 normal=doc.styles['Normal'];normal.paragraph_format.line_spacing=1.5;normal.paragraph_format.first_line_indent=Cm(1.25);normal.paragraph_format.alignment=WD_ALIGN_PARAGRAPH.JUSTIFY;normal.paragraph_format.space_after=Pt(0);normal.paragraph_format.widow_control=True
 for n in [1,2,3]:
  s=doc.styles[f'Heading {n}'];s.font.size=Pt(12);s.font.bold=n<3;s.font.italic=n==3;s.paragraph_format.line_spacing=1.5;s.paragraph_format.first_line_indent=Cm(0);s.paragraph_format.space_before=Pt(0 if n==1 else 18);s.paragraph_format.space_after=Pt(18);s.paragraph_format.keep_with_next=True
  if n==1:s.paragraph_format.page_break_before=True
 for name in ['Caption']:
  s=doc.styles[name];s.font.size=Pt(10);s.font.bold=False;s.font.italic=False;s.paragraph_format.line_spacing=1;s.paragraph_format.first_line_indent=Cm(0);s.paragraph_format.space_before=Pt(8);s.paragraph_format.space_after=Pt(6);s.paragraph_format.keep_with_next=True
 for caption_style in ['LegendaFigura','LegendaQuadro','LegendaTabela']:
  if caption_style not in doc.styles:doc.styles.add_style(caption_style,1)
  doc.styles[caption_style].base_style=doc.styles['Caption']
  doc.styles[caption_style].font.size=Pt(10)
  doc.styles[caption_style].font.name='Arial'
 for name in ['Fonte','Referencia','Natureza','PreTitulo']:
  if name not in doc.styles:doc.styles.add_style(name,1)
  doc.styles[name].font.name='Arial'
  doc.styles[name].base_style=normal
 st=doc.styles['Fonte'];st.font.size=Pt(10);st.paragraph_format.line_spacing=1;st.paragraph_format.first_line_indent=Cm(0);st.paragraph_format.space_after=Pt(12)
 st=doc.styles['Referencia'];st.font.size=Pt(12);st.paragraph_format.line_spacing=1;st.paragraph_format.first_line_indent=Cm(0);st.paragraph_format.alignment=WD_ALIGN_PARAGRAPH.LEFT;st.paragraph_format.space_after=Pt(12)
 st=doc.styles['Natureza'];st.font.size=Pt(12);st.paragraph_format.line_spacing=1;st.paragraph_format.left_indent=Cm(8);st.paragraph_format.first_line_indent=Cm(0)
 st=doc.styles['PreTitulo'];st.font.size=Pt(12);st.font.bold=True;st.paragraph_format.alignment=WD_ALIGN_PARAGRAPH.CENTER;st.paragraph_format.first_line_indent=Cm(0);st.paragraph_format.space_after=Pt(24)


def centered(doc,text,bold=False,after=12):
 p=doc.add_paragraph();p.alignment=WD_ALIGN_PARAGRAPH.CENTER;p.paragraph_format.first_line_indent=Cm(0);p.paragraph_format.space_after=Pt(after);p.add_run(text).bold=bold;return p

def frontmatter(doc,title,kind,abstract_pt,abstract_en,has_tables=False):
 dimensions(doc.sections[0]);set_styles(doc)
 centered(doc,INSTITUTION.upper(),True);centered(doc,'CURSO DE INFORMÁTICA',after=24)
 centered(doc,'\n'.join(a.upper() for a in AUTHORS),after=72)
 centered(doc,title.upper(),True,after=110);centered(doc,CITY);centered(doc,YEAR)
 sec=doc.add_section(WD_SECTION_START.NEW_PAGE);dimensions(sec)
 numbering=OxmlElement('w:pgNumType');numbering.set(qn('w:start'),'1');sec._sectPr.append(numbering)
 sec.header.is_linked_to_previous=False;sec.footer.is_linked_to_previous=False
 centered(doc,'\n'.join(a.upper() for a in AUTHORS),after=72);centered(doc,title.upper(),True,after=36)
 nature=('Trabalho de Conclusão de Curso apresentado ao Curso de Informática do '+INSTITUTION+', como requisito parcial para a obtenção do título de Técnico em Informática.' if kind=='mono' else 'Documentação técnica complementar ao Trabalho de Conclusão de Curso SPOT, apresentada ao Curso de Informática do '+INSTITUTION+'.')
 doc.add_paragraph(nature,style='Natureza');doc.add_paragraph('Orientador: Prof. João Carlos de Souza.',style='Natureza')
 centered(doc,CITY,after=6).paragraph_format.space_before=Pt(96);centered(doc,YEAR)
 for heading,text,keywords in [('RESUMO',abstract_pt,'Palavras-chave: gestão de projetos; aplicação web; controle de acesso; análise de dados; testes de software.'),('ABSTRACT',abstract_en,'Keywords: project management; web application; access control; data analysis; software testing.')]:
  doc.add_page_break();doc.add_paragraph(heading,style='PreTitulo');p=doc.add_paragraph(text);p.paragraph_format.first_line_indent=Cm(0);doc.add_paragraph(keywords).paragraph_format.first_line_indent=Cm(0)
 for title in ['LISTA DE FIGURAS','LISTA DE QUADROS']+(['LISTA DE TABELAS'] if has_tables else []):
  doc.add_page_break();doc.add_paragraph(title,style='PreTitulo')
 doc.add_page_break();doc.add_paragraph('LISTA DE SIGLAS',style='PreTitulo')
 for abbreviation,meaning in SIGLAS:
  p=doc.add_paragraph(abbreviation+' — '+meaning);p.paragraph_format.first_line_indent=Cm(0)
 doc.add_paragraph('SUMÁRIO',style='PreTitulo')
 sec=doc.add_section(WD_SECTION_START.NEW_PAGE);dimensions(sec);sec.header.is_linked_to_previous=False;sec.footer.is_linked_to_previous=False
 for element in list(sec._sectPr):
  if element.tag==qn('w:pgNumType'):sec._sectPr.remove(element)
 doc.styles['Header'].font.size=Pt(10);doc.styles['Header'].font.name='Arial'
 p=sec.header.paragraphs[0];p.style=doc.styles['Header'];p.paragraph_format.space_before=Pt(0);p.paragraph_format.space_after=Pt(0);p.paragraph_format.line_spacing=1;p.alignment=WD_ALIGN_PARAGRAPH.RIGHT;p.paragraph_format.first_line_indent=Cm(0);field(p,'PAGE')
 for run in p.runs:run.font.size=Pt(10)


def write_docx(base,title,kind,blocks,abstract_pt,abstract_en):
 doc=Document();frontmatter(doc,title,kind,abstract_pt,abstract_en,any(b.get('numeric') for b in blocks))
 # Primeira seção textual já tem quebra de seção; evita página em branco adicional.
 first=True;table_index=figure_index=numeric_index=0
 for block in blocks:
  typ=block['type']
  if typ=='h':
   p=doc.add_paragraph(block['text'],style=f'Heading {block["level"]}')
   if not re.match(r'^\d', block['text']):p.alignment=WD_ALIGN_PARAGRAPH.CENTER
   if first:p.paragraph_format.page_break_before=False;first=False
  elif typ=='p':plain_text(doc.add_paragraph(),block['text'])
  elif typ=='link':
   p=doc.add_paragraph();p.paragraph_format.first_line_indent=Cm(0);p.alignment=WD_ALIGN_PARAGRAPH.LEFT;link(p,block['text'],block['url'])
  elif typ=='table':
   if block.get('numeric'):
    numeric_index+=1;caption=f'Tabela {numeric_index}'
   else:
    table_index+=1;caption=f'Quadro {table_index}'
   doc.add_paragraph(f'{caption} — {block["title"]}',style='LegendaTabela' if block.get('numeric') else 'LegendaQuadro')
   table=doc.add_table(rows=1,cols=len(block['headers']));table.style='Table Grid';table.autofit=False
   if block.get('numeric'):
    borders=OxmlElement('w:tblBorders')
    for edge in ['top','bottom','left','right','insideH','insideV']:
     border=OxmlElement('w:'+edge);border.set(qn('w:val'),'single' if edge in ['top','bottom'] else 'nil');border.set(qn('w:sz'),'4');borders.append(border)
    table._tbl.tblPr.append(borders)
   available=16/len(block['headers'])
   for cell,header in zip(table.rows[0].cells,block['headers']):
    cell.text=header
    if block.get('numeric'):
     borders=OxmlElement('w:tcBorders');border=OxmlElement('w:bottom');border.set(qn('w:val'),'single');border.set(qn('w:sz'),'4');borders.append(border);cell._tc.get_or_add_tcPr().append(borders)
   repeat=OxmlElement('w:tblHeader');table.rows[0]._tr.get_or_add_trPr().append(repeat)
   for row in block['rows']:
    cells=table.add_row().cells
    for cell,value in zip(cells,row):cell.text=str(value)
   for index,row in enumerate(table.rows):
    cant=OxmlElement('w:cantSplit');row._tr.get_or_add_trPr().append(cant)
    for cell in row.cells:
     cell.width=Cm(available)
     for p in cell.paragraphs:
      p.paragraph_format.first_line_indent=Cm(0);p.paragraph_format.line_spacing=1;p.paragraph_format.space_after=Pt(4);p.alignment=WD_ALIGN_PARAGRAPH.LEFT
      for run in p.runs:run.font.size=Pt(10);run.bold=index==0
   plain_text(doc.add_paragraph(style='Fonte'),'Fonte: '+block['source'])
  elif typ=='figure':
   figure_index+=1;doc.add_paragraph(f'Figura {figure_index} — {block["title"]}',style='LegendaFigura')
   p=doc.add_paragraph();p.alignment=WD_ALIGN_PARAGRAPH.CENTER;p.paragraph_format.first_line_indent=Cm(0);p.paragraph_format.space_after=Pt(6);p.paragraph_format.keep_with_next=True
   with Image.open(block['path']) as image:w,h=image.size
   width=min(16,12*w/h)
   if 'mobile' in block['path']:width=min(width,5)
   picture=p.add_run().add_picture(block['path'],width=Cm(width));picture._inline.docPr.set('descr',block['title'])
   plain_text(doc.add_paragraph(style='Fonte'),'Fonte: '+block['source'])
 # Referências antecedem os apêndices no texto final; movidas antes durante composição.
 doc.core_properties.title=title;doc.core_properties.author='; '.join(AUTHORS);doc.core_properties.subject='SPOT — versão '+COMMIT;doc.core_properties.language='pt-BR'
 settings=doc.settings.element;update=OxmlElement('w:updateFields');update.set(qn('w:val'),'true');settings.append(update)
 doc.save(base.with_suffix('.docx'))


def ref_blocks(include_market=True):
 refs=REFERENCES if include_market else [r for r in REFERENCES if r[0] not in ['AWS','ASANA','JIRA','TRELLO','CLICKUP','MONDAY','NOTION','PESQUISA','ORIGINAL']]
 return [{'type':'references','items':refs}]

# Implementação de referências como parágrafos próprios para conservar títulos em negrito.
_original_writer=write_docx

def write_document(base,title,kind,blocks,pt,en):
 # Renderizador usa um bloco sentinela, expandido após a gravação inicial com preservação da ordem.
 expanded=[];refs=[]
 for b in blocks:
  if b['type']=='references':
   expanded.append(H(1,'REFERÊNCIAS'));expanded.append(P('__REFERENCIAS_A_SUBSTITUIR__'));refs=b['items']
  else:expanded.append(b)
 _original_writer(base,title,kind,expanded,pt,en)
 doc=Document(base.with_suffix('.docx'))
 for p in list(doc.paragraphs):
  if p.text!='__REFERENCIAS_A_SUBSTITUIR__':continue
  for key,author,ref_title,desc,url in refs:
   np=p.insert_paragraph_before(style='Referencia');np.add_run(author+' ');main_title,sep,subtitle=reference_title_parts(ref_title);np.add_run(main_title).bold=True
   if sep:np.add_run(sep+subtitle)
   np.add_run('. '+desc)
   if url:np.add_run(' Disponível em: ');link(np,url,url);np.add_run('. Acesso em: '+ACCESS+'.')
  p._element.getparent().remove(p._element)
 doc.save(base.with_suffix('.docx'))
 write_html(base,title,kind,blocks,pt,en)


def write_html(base,title,kind,blocks,pt,en):
 e=html.escape;parts=['<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>'+e(title)+'</title><style>@page{size:A4;margin:3cm 2cm 2cm 3cm}body{font:12pt Arial,sans-serif;line-height:1.5;color:#111;text-align:justify}p{text-indent:1.25cm}h1,h2,h3{font-size:12pt;text-align:left}h1{break-before:page}h3{font-weight:normal;font-style:italic}.cover{text-align:center;min-height:23cm;break-after:page;display:flex;flex-direction:column;justify-content:space-between}.cover p{text-indent:0}table{border-collapse:collapse;width:100%;font-size:10pt;line-height:1.15;margin:12pt 0}td,th{border:1px solid #555;padding:6pt;text-align:left;overflow-wrap:anywhere}thead{display:table-header-group}tr{break-inside:avoid}img{max-width:100%;max-height:12cm;object-fit:contain}.caption,.source{font-size:10pt;line-height:1;text-indent:0}.ref{line-height:1;text-indent:0;text-align:left;margin-bottom:12pt}a{color:#111;overflow-wrap:anywhere}.toc p{text-indent:0}.page{break-before:page}</style></head><body>']
 parts+=['<section class="cover"><p>'+e(INSTITUTION.upper())+'<br>CURSO DE INFORMÁTICA</p><p>'+'<br>'.join(e(a.upper()) for a in AUTHORS)+'</p><p><strong>'+e(title.upper())+'</strong></p><p>'+CITY+'<br>2026</p></section>', '<section class="page"><h1>RESUMO</h1><p>'+e(pt)+'</p><p>Palavras-chave: gestão de projetos; aplicação web; controle de acesso; análise de dados; testes de software.</p></section>', '<section class="page"><h1>ABSTRACT</h1><p>'+e(en)+'</p><p>Keywords: project management; web application; access control; data analysis; software testing.</p></section>','<nav class="toc"><h1>SUMÁRIO</h1>']
 for index,b in enumerate(blocks):
  if b['type']=='h':parts.append(f'<p><a href="#s{index}">{e(b["text"])}</a></p>')
  elif b['type']=='references':parts.append(f'<p><a href="#s{index}">REFERÊNCIAS</a></p>')
 parts.append('</nav>')
 for title,typ in [('LISTA DE FIGURAS','figure'),('LISTA DE QUADROS','table')]+([('LISTA DE TABELAS','numeric')] if any(b.get('numeric') for b in blocks) else []):
  parts.append('<section class="page"><h1>'+title+'</h1>');number=0
  for index,b in enumerate(blocks):
   if (b['type']==typ and not b.get('numeric')) or (typ=='numeric' and b.get('numeric')):
    number+=1;label=('Figura' if typ=='figure' else 'Tabela' if typ=='numeric' else 'Quadro')+f' {number} — '+b['title']
    parts.append(f'<p style="text-indent:0"><a href="#item{index}">{e(label)}</a></p>')
  parts.append('</section>')
 nav_start=next(i for i,v in enumerate(parts) if v.startswith('<nav class="toc">'))
 nav_end=next(i for i,v in enumerate(parts) if v=='</nav>')
 nav=parts[nav_start:nav_end+1];parts=parts[:nav_start]+parts[nav_end+1:]+nav
 ti=fi=ni=0
 for index,b in enumerate(blocks):
  typ=b['type']
  if typ=='h':parts.append(f'<h{b["level"]} style="text-align:{"left" if b["text"][0].isdigit() else "center"}" id="s{index}">{e(b["text"])}</h{b["level"]}>')
  elif typ=='p':parts.append('<p>'+e(b['text'])+'</p>')
  elif typ=='link':parts.append('<p><a href="'+e(b['url'])+'">'+e(b['text'])+'</a></p>')
  elif typ=='table':
   if b.get('numeric'):
    ni+=1;caption=f'Tabela {ni}'
   else:
    ti+=1;caption=f'Quadro {ti}'
   parts.append(f'<p class="caption" id="item{index}">{caption} — {e(b["title"])}</p><table><thead><tr>'+''.join('<th>'+e(v)+'</th>' for v in b['headers'])+'</tr></thead><tbody>')
   parts.extend('<tr>'+''.join('<td>'+e(str(v))+'</td>' for v in row)+'</tr>' for row in b['rows']);parts.append('</tbody></table><p class="source">Fonte: '+e(b['source'])+'</p>')
  elif typ=='figure':
   fi+=1;path=Path(__import__('os').path.relpath(b['path'],base.parent)).as_posix();parts.append(f'<p class="caption" id="item{index}">Figura {fi} — {e(b["title"])}</p><p style="text-indent:0;text-align:center"><img src="{e(path)}" alt="{e(b["title"])}"></p><p class="source">Fonte: {e(b["source"])}</p>')
  elif typ=='references':
   parts.append(f'<h1 style="text-align:center" id="s{index}">REFERÊNCIAS</h1>')
   for key,author,ref_title,desc,url in b['items']:
    parts.append('<p class="ref">'+e(author)+' <strong>'+e(reference_title_parts(ref_title)[0])+'</strong>'+e(''.join(reference_title_parts(ref_title)[1:]))+'. '+e(desc)+((' Disponível em: <a href="'+e(url)+'">'+e(url)+'</a>. Acesso em: '+ACCESS+'.') if url else '')+'</p>')
 parts.append('</body></html>');base.with_suffix('.html').write_text('\n'.join(parts),encoding='utf-8')


def normalize_blocks(blocks):
 leads={
  'FUNDAMENTAÇÃO E ESTUDO DE SOLUÇÕES':'A fundamentação relaciona organização do trabalho, componentes de software e soluções consultadas. A distinção entre levantamento documental e validação prática delimita as conclusões possíveis.',
  'METODOLOGIA':'Os procedimentos distinguem dados de participantes, documentos e evidências de implementação. São apresentados os critérios de análise e os limites das fontes disponíveis.',
  'LEVANTAMENTO DE PESQUISA DE CAMPO':'O levantamento descreve percepções dos participantes e orienta a relação entre necessidades e requisitos. A apresentação mantém as frequências recuperáveis e registra as diferenças documentais.',
  'REQUISITOS, REGRAS E FUNCIONALIDADES':'As funcionalidades são descritas por operações, critérios de acesso e condições de funcionamento. As regras estabelecem as responsabilidades dos perfis e os controles aplicados pelo servidor.',
  'IMPLANTAÇÃO E CONTINUIDADE':'A entrada em operação depende do ambiente de destino e da homologação das integrações. Esta seção organiza os requisitos de configuração, validação e manutenção.',
  'PREPARAÇÃO PARA A DEFESA':'A demonstração deve utilizar dados sintéticos e acompanhar a versão analisada. O roteiro e as questões de ensaio distinguem resultados verificados de pendências de homologação.',
  'Resultados quantitativos':'As distribuições a seguir correspondem aos treze conjuntos quantitativos recuperados. Tabelas e figuras mantêm as categorias publicadas e permitem conferir as frequências com a base declarada.'}
 result=[];counts={'figure':0,'table':0,'numeric':0};expanded=set()
 definitions={'SPOT':'sistema Sistemas de Projetos, Organização e Tarefas (SPOT)','HTML':'Hypertext Markup Language (HTML)','SMTP':'Simple Mail Transfer Protocol (SMTP)','PHP':'PHP: Hypertext Preprocessor (PHP)','API':'interface de programação de aplicações (API)','JSON':'JavaScript Object Notation (JSON)','CSRF':'Cross-Site Request Forgery (CSRF)','JWT':'JSON Web Token (JWT)','SQL':'Structured Query Language (SQL)','IA':'inteligência artificial (IA)','WCAG':'Web Content Accessibility Guidelines (WCAG)','CSV':'Comma-Separated Values (CSV)','HTTP':'Hypertext Transfer Protocol (HTTP)','HTTPS':'Hypertext Transfer Protocol Secure (HTTPS)','TCC':'Trabalho de Conclusão de Curso (TCC)'}
 for i,original in enumerate(blocks):
  b=dict(original)
  if b['type']=='p':
   for abbreviation,definition in definitions.items():
    pattern=r'(?<![\w/.-])'+abbreviation+r'(?![\w/.-])'
    if abbreviation not in expanded and re.search(pattern,b['text']):
     if '('+abbreviation+')' not in b['text']:
      b['text']=re.sub(pattern,lambda _:definition,b['text'],count=1)
     expanded.add(abbreviation)
   def order(match):
    parts=match.group(1).split('; ')
    return '('+'; '.join(sorted(parts,key=lambda v:unicodedata.normalize('NFKD',v).casefold()))+')' if all(re.search(r', (?:\d{4}|\[20--)',part) for part in parts) else match.group(0)
   b['text']=re.sub(r'\(([^()]*; [^()]*)\)',order,b['text'])
  if b['type'] in ['table','figure']:
   typ='numeric' if b.get('numeric') else b['type'];counts[typ]+=1
   label={'numeric':'Tabela','table':'Quadro','figure':'Figura'}[typ]
   result.append(P({'numeric':f'Os dados correspondentes constam da Tabela {counts[typ]}.','table':f'As informações correspondentes estão organizadas no Quadro {counts[typ]}.','figure':f'A representação correspondente é apresentada na Figura {counts[typ]}.'}[typ]))
  result.append(b)
  if b['type']=='h' and i+1<len(blocks) and blocks[i+1]['type']=='h':
   key=re.sub(r'^\d+(?:\.\d+)* ', '',b['text'])
   if key not in leads:raise ValueError('Seção sem texto de abertura: '+key)
   result.append(P(leads[key]))
 return result


def assemble(blocks,market):
 # Referências após a conclusão e antes dos apêndices, como elementos pós-textuais.
 at=next(i for i,b in enumerate(blocks) if b['type']=='h' and b['text'].startswith('APÊNDICE'))
 return normalize_blocks(blocks[:at]+ref_blocks(market)+blocks[at:])


def manifest():
 files=subprocess.check_output(['git','ls-files','app','resources','routes','config','database','scripts','tests','composer.json','composer.lock','package.json','package-lock.json','Dockerfile','docker-compose.yml','requirements.txt'],cwd=ROOT,text=True).splitlines()
 digest=hashlib.sha256(); hashes={}
 for name in sorted(files):
  p=ROOT/name
  if p.is_file():
   sha=hashlib.sha256(p.read_bytes()).hexdigest();hashes[name]=sha;digest.update((name+'\0'+sha+'\n').encode())
 original=[]
 for name in ['Monografia_SPOT_documentacao_integrada.docx','Capitulo_Levantamento_Pesquisa_ABNT.docx']:
  p=Path('/home/alexander/Downloads')/name
  if p.exists():original.append({'arquivo':name,'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'preservado':True})
 result={'data_corte':'2026-11-10','commit':COMMIT,'repositorio':REPO,'permalink':PERMALINK,'origem_remota_confirmada':True,'code_sha256':digest.hexdigest(),'arquivos_codigo':len(hashes),'hashes':hashes,'fontes_originais':original,'rotas_totais':len(ROUTES),'normas':['NBR 14724:2024','NBR 6023:2025','NBR 10520:2023','NBR 6024:2012','NBR 6027:2012','NBR 6028:2021'],'validacao':{'php_tests':256,'php_assertions':3487,'browser_suites':36,'responsive_checks':323,'tutorial_checks':756,'language_checks':144}}
 (F/'manifesto-versao-final.json').write_text(json.dumps(result,ensure_ascii=False,indent=2))
 return result


def main():
 F.mkdir(exist_ok=True);AS.mkdir(exist_ok=True)
 pt='Este trabalho apresenta o sistema Sistemas de Projetos, Organização e Tarefas (SPOT), aplicação web desenvolvida para apoiar necessidades de organização, execução, comunicação e acompanhamento gerencial de projetos no contexto da Computécnica. A investigação reúne levantamento de campo de caráter descritivo, com 94 respostas declaradas e amostra de conveniência, estudo documental de soluções e análise da implementação. Os dados da pesquisa são preservados, incluindo diferenças entre totais de figuras e o número informado de participantes, sem reconstrução de respostas individuais. A versão técnica consultada utiliza Laravel 13, React 18, PostgreSQL e processamento analítico com Python e pandas. As funcionalidades abrangem identidade, perfis e permissões, gerente obrigatório de projeto, tarefas, horas, despesas, agenda, notificações, e-mails e chat com escolha de aprovador administrativo. A interface dispõe de português, inglês e espanhol, recursos de leitura e tutorial condicionado às permissões. A revisão registrou 256 testes de servidor e 3.487 asserções aprovados, também no runtime Docker, além de 36 suítes de navegador. Os resultados sustentam os comportamentos cobertos, sem demonstrar ganho financeiro, ausência de defeitos ou conformidade integral de acessibilidade. A publicação permanece condicionada ao ambiente de destino, à conferência de responsáveis antigos e à homologação real de provedores e operação. O código consultado e as fontes são identificados para permitir rastreabilidade e continuidade do trabalho.'
 en='This study presents SPOT (Sistemas de Projetos, Organização e Tarefas), a web application designed to support project organization, execution, communication and managerial monitoring in the context of Computécnica. The investigation combines a descriptive field survey, with 94 declared responses and convenience sampling, a documentary study of software solutions, and implementation analysis. Research figures and inconsistencies are preserved without reconstructing individual responses. The examined technical version uses Laravel 13, React 18, PostgreSQL, and analytical processing with Python and pandas. Features include identity, roles and permissions, a required project manager, tasks, work logs, expenses, calendar, notifications, email, and chat with a selected administrative approver. The interface supports Portuguese, English and Spanish, reading adjustments, and a permission-aware tutorial. Verification recorded 256 passing server tests and 3,487 assertions, including execution in the Docker runtime, as well as 36 browser suites. These results support the covered behaviors but do not demonstrate financial gains, absence of defects or full accessibility compliance. Production publication remains subject to target-environment configuration, review of legacy project ownership, and real-provider and operational acceptance tests. The reviewed source-code version and documentary sources are identified to support traceability and further development.'
 techpt='Esta documentação técnica do sistema Sistemas de Projetos, Organização e Tarefas (SPOT) registra a arquitetura, as regras de negócio, o modelo de dados, os contratos de API e as condições de operação do SPOT na versão consultada em 10 de novembro de 2026. O levantamento utiliza o código-fonte identificado por commit, migrations, rotas, componentes e evidências automatizadas, sem tratar propostas como recursos entregues. A aplicação emprega Laravel 13, React 18, PostgreSQL e consolidação analítica com Python e pandas. São descritos provisionamento administrativo e ativação por e-mail, controle de permissões, gerente obrigatório, execução de projetos, finanças, agenda, notificações e chat com aprovador escolhido. Também são registrados os modelos de e-mail em três idiomas, o tutorial, os recursos de leitura e a tradução automática opcional de mensagens. A revisão aprovou 256 testes de servidor com 3.487 asserções, inclusive em contêiner, e 36 suítes de navegador, além do build e das auditorias de dependências. A entrega corresponde a código verificado para homologação e não a uma declaração de produção liberada. Credenciais, domínio, integrações reais, responsáveis de projetos antigos e revisão humana de acessibilidade permanecem como condições de aceite. Referências atualizadas, inventários e o link da versão do repositório permitem consultar os artefatos e reproduzir a avaliação.'
 techen='This technical documentation records the architecture of SPOT (Sistemas de Projetos, Organização e Tarefas), business rules, data structures, API contracts and operational conditions for the source version examined on November 10, 2026. It draws on the identified commit, migrations, routes, components and automated evidence, without treating planned capabilities as delivered features. The application uses Laravel 13, React 18, PostgreSQL, and analytical processing with Python and pandas. It describes administrative provisioning, email-based activation, permissions, required project ownership, project execution, finances, calendar, notifications and chat with a selected approver. The documentation also covers three-language email templates, the tutorial, reading adjustments and optional automatic message translation. Verification passed 256 server tests with 3,487 assertions, including container execution, and 36 browser suites, together with the frontend build and dependency audits. The delivery represents code verified for acceptance testing, rather than an unconditional production release. Credentials, domain setup, real integrations, legacy ownership records and human accessibility review remain acceptance conditions. Updated references, inventories and a source-version link support consultation and reproducibility.'
 write_document(D/'Monografia_SPOT_documentacao_integrada_final','SPOT: sistemas de projetos, organização e tarefas','mono',assemble(mono_blocks(),True),pt,en)
 write_document(D/'documentacao-tecnica-spot-abnt','Documentação técnica do sistema SPOT: gestão integrada de projetos','tech',assemble(technical_blocks(),False),techpt,techen)
 info=manifest()
 lines=['# Referências da documentação final','',f'Código consultado: {COMMIT}. Data de acesso: {ACCESS}.','']
 for _,author,title,desc,url in REFERENCES:lines.append(f'{author} **{title}**. {desc}'+(f' Disponível em: [{url}]({url}). Acesso em: {ACCESS}.' if url else '')+'\n')
 (D/'referencias-documentacao-final.md').write_text('\n'.join(lines))
 print(json.dumps({'docx':2,'html':2,'commit':COMMIT,'rotas':len(ROUTES),'hash_codigo':info['code_sha256']},ensure_ascii=False))

if __name__=='__main__':main()
