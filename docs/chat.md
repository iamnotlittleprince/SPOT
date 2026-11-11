# Chat do Spot

Regra vigente revisada em 11/11/2026: administradores e gerentes administradores iniciam conversas diretamente; solicitações são exigidas para analistas e gerentes fora de projetos em comum. As seções datadas de revisões anteriores registram o histórico da implementação.

Conversas individuais persistentes, limitadas à empresa atual, acessíveis pelo ícone e texto **Chat** na barra lateral e pelo menu de páginas. A sessão inicial, login e atualização de perfil informam `can_chat`; as mesmas regras são verificadas no servidor em todas as operações.

## Quem pode conversar

- Contas ativas, com situação ativa e perfil `analista`, `gestor`, `gestor-administrador` ou `administrador` na empresa atual podem acessar a funcionalidade. A presença do perfil `convidado`, mesmo acumulado com outro perfil, bloqueia o chat.
- Administradores e gerentes administradores podem buscar e iniciar uma conversa com qualquer pessoa elegível da mesma empresa, sem pedido ou aprovador. Analistas e gerentes têm acesso direto quando ambas as pessoas participam ativamente, sem revogação, como `analyst` ou `manager` em um projeto em comum não excluído. Finalizar um projeto não remove os vínculos da equipe.
- Fora desses times, o analista ou gerente seleciona uma pessoa da mesma empresa e informa o motivo. Os perfis **administrador** (`administrador`) e **gerente administrador** (`gestor-administrador`) podem aprovar ou recusar, mesmo sem participação no projeto. A ausência desses perfis na equipe não impede solicitar a outro aprovador ativo da mesma empresa. A lista informa apenas nomes, identificação e disponibilidade de chat, sem e-mails ou mensagens alheias.
- A autorização vale para o par de pessoas, nos dois sentidos, até revogação. Não libera outros usuários nem projetos. Um pedido pendente por par impede duplicatas, inclusive no sentido inverso. Pedidos recusados/revogados permitem nova solicitação.
- Ninguém aprova a própria solicitação, inclusive em pedidos legados. Administradores e gerentes administradores não geram novos pedidos para si: a API informa que a conversa já está disponível. Sem revisor elegível, o formulário dos analistas/gerentes explica a indisponibilidade.
- Quando um administrador ou gerente administrador envia a primeira mensagem a uma pessoa fora do time, ela pode ler e responder nessa mesma conversa sem um pedido separado. Apenas abrir o contato não libera a iniciativa do analista/gerente. O contato administrativo precisa continuar elegível e com perfil administrativo; perda dessa condição exige outro vínculo de acesso válido. O perfil administrativo não permite ler mensagens de conversas entre terceiros.
- Inativação, mudança de empresa ou perfil de convidado tornam o participante indisponível. Fora dos times, a aprovação também deixa de permitir acesso se seu revisor perder a condição de administrador ou gerente administrador ativo na empresa. O usuário pode pedir nova aprovação a outro revisor elegível.
- A retirada de participação, a revogação ou a mudança para papel de convidado no projeto bloqueiam o acesso pelo time; outra participação compartilhada ou autorização específica ainda válida pode mantê-lo. A revogação de uma autorização externa não remove o acesso de um time compartilhado.

## Mensagens e versões

Apenas o autor edita, até 24 horas contadas do envio original, inclusive o instante limite. Editar não reinicia o prazo. O servidor confere autoria, prazo e versão; alterações concorrentes retornam conflito em vez de sobrescrever silenciosamente. O texto original e cada edição ficam em `chat_message_revisions` e podem ser consultados pelos participantes autorizados. Mensagens sem texto e sem anexos são recusadas; o texto tem limite de 5.000 caracteres.

Não há ação nem rota de exclusão de mensagens ou conversas. O modelo bloqueia a exclusão e as referências de banco usam restrição de exclusão, sem cascatas que apaguem o histórico. Arquivar/excluir um aviso na caixa de entrada não apaga a mensagem do chat. Revogar acesso mantém os registros; a leitura, edição, tradução e consulta de versões passam a exigir novamente um vínculo permitido.

A conversa carrega as últimas 50 mensagens e permite carregar as anteriores. A tela consulta atualizações a cada oito segundos em abas visíveis e ao recuperar foco, além de atualizar após operações. Não usa WebSocket nem informa presença ou confirmação de leitura. O histórico rola em sua própria área e preserva a posição ao carregar mensagens anteriores; novas mensagens não levam a rolagem ao final quando a pessoa está lendo o histórico. Avisos na caixa de entrada indicam novas mensagens e pedidos/respostas, com botão para abrir o chat. Os avisos de novas mensagens não copiam o texto da conversa.

## Tradução

A tradução dos textos escritos pelos usuários é separada da tradução da interface. A integração usa uma instância LibreTranslate, com detecção do idioma de origem e destino pt/en/es. A tradução ocupa o texto do próprio balão, é identificada como automática e oferece Ver original/Ver tradução para alternar localmente a exibição. O original continua preservado no servidor; traduzir nunca edita a mensagem salva. A edição sempre abre o texto original e uma nova versão da mensagem deixa de exibir a tradução antiga. Mensagens antigas também podem ser traduzidas. O cache é separado por mensagem, versão e idioma; editar invalida o resultado anterior pela versão. A chave fica apenas no servidor.

Configuração no servidor:

```dotenv
# URL base, sem o sufixo /translate
CHAT_TRANSLATION_URL=https://tradutor.sua-empresa.example
CHAT_TRANSLATION_KEY=
```

A chave depende da configuração da instância. Pode-se hospedar o serviço dentro da organização. Quando a tradução for solicitada, o texto será enviado ao serviço configurado; a tradução da interface não envia esse conteúdo. Sem URL, a interface informa a indisponibilidade e desabilita a ação. Não há provedor público padrão nem tradução simulada em produção. O responsável pelo ambiente deve disponibilizar o serviço, definir os idiomas necessários e configurar as variáveis; com cache de configuração, regenerá-lo após a alteração.

Referência do protocolo: [API de tradução do LibreTranslate](https://docs.libretranslate.com/api/operations/translate/).

## Instalação e verificações

Aplicar `./php artisan migrate --force` e executar `npm run build`. A migration de criação foi aplicada no ambiente local desta implementação. Nenhuma conversa real, mensagem real ou aprovação para contas reais foi criada nos testes.

- `./php artisan test --filter='ChatTest|TeamManagementTest|ProfileTest|SessionApiAuthTest'`
- `npm run test:chat` (build e navegador com respostas simuladas)
- Com servidor na porta 8000: `npm run test:responsive`, `node scripts/test-languages.mjs` e `node scripts/test-accessibility.mjs` após o build.

A validação do chat inclui isolamento de empresa, convidados, participantes de times, solicitações, autoria, prazo original, conflitos de versão, revogação, retenção, tradução/cache e indisponibilidade do provedor; no navegador, envio, edição, Escape e retorno de foco, histórico, tradução sem alterar o original, mudança de permissões, idiomas e telas de 320/1440 px, com leitura em 120% no celular. Avaliação humana com leitor de tela, dispositivos físicos e pessoas com deficiência permanece pendente. Os testes automatizados não comprovam conformidade integral com WCAG 2.2 AA.

## Apresentação das conversas

O visual segue uma referência do Microsoft Teams: painel integrado com lista de pessoas à esquerda, seleção destacada, avatar e nome no cabeçalho, mensagens enviadas à direita em roxo e recebidas à esquerda, e composição no rodapé da conversa. O histórico rola dentro do painel. No celular, lista e conversa se empilham, mantendo busca, tradução e ações com seus textos completos. Não há indicadores de presença ou confirmação de leitura.


## Autoria e atalhos

A sessão inicial e a resposta de atualização do perfil incluem o identificador do usuário. As mensagens retornam `is_mine` e `sender_name`, calculados no servidor; a interface também normaliza identificadores numéricos/textuais no caminho de compatibilidade. Isso mantém mensagens próprias à direita e recebidas à esquerda após recarregar ou atualizar o perfil. Nome, horário e botões compactos de editar/traduzir aparecem acima do balão. Esses botões têm alvos de pelo menos 32 px e nomes acessíveis completos.

No campo de composição, **Enter** envia; **Ctrl + Shift + Enter** insere uma nova linha na posição do cursor ou substitui a seleção. A inserção preserva a posição do cursor de forma síncrona. Repetição de tecla e envio em andamento não criam envios adicionais; a confirmação de composição de texto por IME não dispara envio. A instrução é visível e associada ao campo. Edição inline continua sendo salva pelo botão Salvar edição.

## Anexos privados

O botão Anexar arquivos abre a seleção nativa. São aceitos até **3 arquivos de 2 MB por mensagem**, em PDF, documentos Office, TXT, CSV, JPG/JPEG, PNG, WebP ou ZIP. Tipo real, extensão, quantidade e tamanho são validados no servidor; a interface antecipa erros de quantidade/tamanho. Os limites cabem na configuração atual de PHP, com upload de 2 MB por arquivo e requisição de 8 MB. É possível enviar apenas arquivos ou incluir uma legenda.

Arquivos ficam no disco `local`, dentro de `storage/app/private/chat`, com nomes internos gerados pelo armazenamento. O caminho interno não é exposto na API. A tabela `chat_attachments` referencia a mensagem, com restrição de exclusão. Não há rota de exclusão, remoção ou substituição de arquivos enviados; alterar ou limpar a legenda preserva os anexos. Antes de enviar, o usuário pode retirar um arquivo da seleção.

O download usa uma rota autenticada que verifica empresa, participação na conversa e acesso atual, incluindo revogação/inativação/perfil de convidado. A alçada de revisão do gerente administrador não dá acesso a arquivos de conversas alheias. O arquivo é servido como download, com `nosniff` e cache privado sem armazenamento. A interface verifica o resultado antes de iniciar o download para explicar erros de acesso sem salvar a resposta JSON como arquivo.

Envio e metadados são transacionais: uma falha de persistência reverte mensagem, versões e avisos, e remove os arquivos ainda não enviados da operação. Os registros/arquivos já enviados permanecem após a revogação de acesso. A tradução trata apenas o texto/legenda; arquivos e mensagens que contêm apenas anexos não são enviados ao tradutor. A migration de anexos foi aplicada no ambiente local.


## Apresentação da tradução e instruções

A conversa exibe um único texto por mensagem: tradução ou original, sem bloco duplicado ou separador adicional. O trecho traduzido recebe o idioma de destino para tecnologias assistivas. Ver original e Ver tradução não fazem nova requisição nem alteram o histórico. Os controles de editar e traduzir permanecem acima do balão, com texto visível e estilo discreto, sem bordas permanentes.

A composição mantém o atalho de teclado visível. Os limites de anexos e as regras de edição ficam em Limites e edição, um elemento nativo que abre os detalhes sem modal; as descrições continuam associadas ao campo e ao botão de anexar. Erros permanecem visíveis fora da área recolhida.

## Editor de texto

O Chat inclui formatação de texto na composição e na edição: negrito, itálico, sublinhado, tachado, listas, destaque, cor/tamanho, citação, links, código e opções adicionais. A barra abre por Formatar texto e pode ser recolhida. Guia dos controles, atalhos, persistência e critérios de validação em [Formatação de mensagens](chat-formatacao.md).

## Envio e visualização de imagens

O botão **Enviar imagem** abre a seleção nativa de fotos JPG/JPEG, PNG ou WebP. A seleção apresenta uma prévia local e permite informar uma descrição opcional de até 500 caracteres, usada como texto alternativo. O total de anexos, incluindo imagens e outros arquivos, continua limitado a três de até 2 MB cada. A imagem pode ser enviada sozinha ou com texto/legenda e formatação. Arquivos ainda não enviados podem ser retirados da seleção.

Depois de enviar, a imagem aparece no balão com link para baixar o original. Imagens válidas já anexadas pelo botão geral também passam a ter prévia. A rota da prévia exige autenticação e o mesmo acesso atual da conversa, sem endereço público; tipo real, estrutura e dimensões são verificados no servidor. A prévia aceita apenas as três famílias de imagens raster acima, com até 8192 × 8192 pixels, e usa cache privado sem armazenamento, `nosniff` e uma política restritiva de conteúdo. Documentos e imagens inválidas não são servidos nessa rota. A validação do upload rejeita imagens corrompidas e dimensões excedidas, além dos limites de tamanho/extensão já existentes.

A prévia usa o arquivo original, sem recortar ou modificar os bytes; o navegador a ajusta à largura do balão. Se não for possível carregá-la, a interface informa a indisponibilidade e conserva o acesso ao download autorizado. URLs de prévias locais são liberadas ao retirar a imagem ou encerrar a seleção. Não há reconhecimento nem tradução do conteúdo visual; legendas continuam sendo tratadas pelo fluxo de texto. A descrição fica preservada com o anexo enviado. Não foi adicionada exclusão de imagens enviadas. A migration de descrições foi aplicada no ambiente local.

## Área de trabalho expandida

No desktop, em janelas acima de 900 px de largura e 640 px de altura, o Chat ocupa toda a área útil abaixo do cabeçalho e da faixa de navegação. Não há margem externa do painel nem limite de altura de 1.000 px. O dimensionamento usa a altura dinâmica da tela e a escala de leitura, com distribuição flexível do espaço, em vez de subtrair uma altura fixa dos cabeçalhos. Lista, histórico e solicitações rolam em suas próprias áreas; a conversa também permite rolagem quando seus controles precisam de mais espaço. Em telas estreitas/baixas permanece o reflow com rolagem da página e acesso aos controles. A apresentação expandida se aplica somente ao Chat e é retirada ao navegar para outra página.

## Idioma de tradução com bandeiras — 10/11/2026

O seletor de texto “Traduzir para” foi substituído pelo componente compartilhado de bandeiras pt/en/es. A escolha altera somente o destino da tradução das mensagens, sem alterar o idioma global. O grupo usa o rótulo visível, cada botão tem nome acessível e estado `aria-pressed`, com alvo de 44 × 44 px, foco visível e indicação de seleção adaptada a cores forçadas.

O teste do chat foi atualizado para selecionar espanhol por teclado e verificar a tradução inline e a preservação do idioma global, incluindo tela de 320 px com leitura em 120% e nome longo. Não houve mudança na API ou no mecanismo de tradução.

### Posição no cabeçalho — 10/11/2026

As bandeiras de tradução ficam no cabeçalho da conversa, no lado oposto ao avatar/nome. A identidade ocupa o espaço disponível e o controle fica alinhado à direita. Em telas estreitas, o controle quebra para a linha seguinte, ainda à direita, sem comprimir ou ocultar o nome. A faixa inferior fica reservada ao aviso de tradução indisponível, quando necessário.

## Solicitar acesso pela aba de solicitações — 10/11/2026

A aba possui o menu expansível nativo “Solicitar acesso ao chat”, com pessoa e motivo obrigatório (até 1.000 caracteres). Usa o mesmo endpoint e regras do pedido pela conversa. A lista contém somente contatos elegíveis, sem conversa já liberada ou pedido pendente; não depende do filtro da lista de conversas. Pedidos recusados/revogados podem ser feitos novamente quando o servidor indicar ausência de acesso e de pendência. Sem revisor elegível, o formulário é substituído por orientação para procurar a administração. O menu também fica disponível a revisores, preservando a proibição de aprovar o próprio pedido no servidor.

O envio mantém a aba aberta, limpa o formulário após sucesso, atualiza a lista e anuncia o resultado. Erros de motivo são associados ao campo; o texto da pessoa e do motivo não é traduzido. Traduções en/es da interface incluídas. A seleção e o envio não concedem acesso: a conversa continua dependendo de aprovação válida ou vínculo de time.

### Enviar e acompanhar na mesma tela — 10/11/2026

O formulário de solicitação fica diretamente visível, sem menu recolhido. A área “Acompanhar solicitações” apresenta os registros já retornados pela API e permite filtrar entre todas as situações, pendente, aprovada, recusada e revogada. Ausência de registros e ausência de resultados para um filtro têm mensagens diferentes. A mudança é apenas de apresentação; elegibilidade, revisão, bloqueio de duplicatas e isolamento da empresa continuam no servidor.


## Aprovação administrativa e gerente do projeto — 10/11/2026

A política de revisão inclui `administrador` e `gestor-administrador`, sempre ativos e elegíveis na empresa atual. Analistas e gerentes podem pedir autorização a esses aprovadores fora do projeto; não é necessário ter um administrador na equipe. A pessoa escolhe um aprovador elegível da empresa, diferente de si mesma; o aviso e a análise do novo pedido são direcionados a essa pessoa. Isolamento entre empresas, bloqueio de convidados, pendência única, autoaprovação proibida, revogação e sigilo de mensagens são preservados.

Na criação pela API v1, `project_manager_id` passa a ser obrigatório, validado pelos perfis elegíveis já existentes. Esse responsável entra automaticamente na equipe com papel `manager`, sem duplicar o registro quando também selecionado em `member_ids`. O campo é obrigatório no formulário. A alteração do responsável também garante sua participação como gerente na equipe.

As rotas de edição de equipe, inclusão/alteração de membro e remoção de membro bloqueiam mudanças que deixariam o projeto sem gerente ativo elegível. Operações são transacionais e serializadas pelo registro do projeto; o gerente de referência é sincronizado com os remanescentes. Não houve atribuição automática de responsáveis aos projetos antigos: registros sem gerente precisam de indicação administrativa explícita. Nenhuma migration nem alteração em projetos reais foi feita nesta revisão.

Validação: 46 testes/560 asserções de chat, gerente obrigatório, provisionamento, equipe inicial, elegibilidade e equipes; mais dois testes de configuração e CRUD (18 asserções) em processos separados, todos aprovados. Navegador: 168 verificações do chat e fluxo de gestão de projetos aprovados. Build e teste de teclado/modal aprovados. A execução da suíte completa teve 29 erros de colisão de fixtures de perfis/permissões/categorias entre classes e uma fixture antiga sem gerente; a fixture pertinente foi corrigida e os testes pertinentes passaram separadamente. A suíte completa não é declarada aprovada.


## Escolha do aprovador — 10/11/2026

Os formulários da aba e da conversa incluem “Enviar aprovação para”. O catálogo retorna nomes e identificadores dos administradores/gerentes administradores ativos e elegíveis da mesma empresa, excluindo o solicitante. O envio exige `reviewer_id`, validado novamente no servidor; destinatários de outra empresa, inativos, sem alçada ou a própria conta são recusados.

`assigned_reviewer_id` registra o destinatário escolhido, separado de `reviewer_id`, que registra quem efetivamente decidiu. Somente o destinatário escolhido recebe o aviso e pode aprovar/recusar o novo pedido. A listagem inclui pedidos dos participantes e, para revisores, os atribuídos a eles; “Enviado para” identifica o destinatário no acompanhamento. Pedidos antigos sem destinatário continuam disponíveis aos aprovadores elegíveis para preservar o fluxo anterior. Regras de revogação permanecem.

Migration aditiva `2026_11_10_130000_add_assigned_reviewer_to_chat_requests.php` aplicada no ambiente local, sem alterar os pedidos existentes. Build aprovado; 25 testes do chat/364 asserções e 170 verificações no navegador aprovados, incluindo destino exato, aviso somente ao escolhido, bloqueio de outro aprovador, validação de destinatário e tela de 320 px com leitura 120%. Nenhuma solicitação real criada.

## Consolidação da revisão final — 10/11/2026

A pendência de isolamento entre classes descrita na etapa intermediária acima foi corrigida na revisão final. A suíte completa passou com 256 testes e 3.487 asserções, também na imagem Docker; o chat teve 170 verificações de navegador e o editor foi repetido três vezes com 100 verificações por execução. Os resultados históricos foram mantidos para rastreabilidade. Ver [relatório final](revisao-producao-2026-11-10.md) e [documentação consolidada](DOCUMENTACAO-FINAL.md).

## Contato administrativo direto — 11/11/2026

A API informa `can_start_without_approval` para a conta atual e `administrative_access` nos contatos, mantendo `can_chat` como resultado final da política. O catálogo e os endpoints de leitura, envio, anexos, edição, versões e tradução seguem a mesma regra. Uma consulta em lote identifica conversas nas quais o interlocutor já enviou mensagens; o servidor confere novamente a autorização em cada operação.

A interface administrativa mostra Conversa direta, orienta pesquisar um nome e substitui o formulário de pedido por uma explicação na aba Aprovações de chat. O botão Ir para conversas retorna o foco à busca. Analistas/gerentes mantêm o formulário de pessoa, motivo e aprovador escolhido. O tutorial foi atualizado em pt/en/es. Convidados, contas inativas/arquivadas e participantes de outra empresa continuam indisponíveis; histórico alheio continua privado.

Não foi necessária nova migration nesta regra de chat, e nenhum pedido, permissão ou mensagem real foi criado para validá-la. A aplicação na VM depende de publicar a nova imagem. Avaliação humana com leitor de tela e dispositivos físicos permanece pendente.

Validação desta regra: suíte completa PHP com 261 testes e 3.580 asserções aprovadas; 26 testes de chat/412 asserções; build; 174 verificações de chat em 320/1440 px, incluindo envio administrativo direto e foco ao retornar à busca; 323 verificações de responsividade; 144 de idiomas; 756 de tutorial; teste de teclado/modal e ajustes de leitura. As mensagens e solicitações usadas nos testes são sintéticas, sem alteração de conversas reais.
