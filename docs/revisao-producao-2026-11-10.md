# Revisão para produção — 10/11/2026

**Resultado: código validado para homologação; publicação condicionada à configuração do servidor, conferência dos responsáveis antigos e testes dos provedores reais.** Não houve publicação, envio de e-mail real nem alteração de responsáveis em projetos existentes.

A avaliação abrange o estado atual do workspace, incluindo arquivos novos ainda não versionados. Uma implantação a partir do Git deve incluir esses arquivos, as migrations e o lockfile atualizado; não apenas os arquivos já presentes no commit anterior.

## Correções feitas nesta revisão

- **Dependências:** corrigidos sete avisos de segurança em quatro pacotes Symfony. `http-foundation`, `http-kernel` e `routing` passaram para 7.4.23; `mime`, para 7.4.22. Auditorias Composer e npm não apresentam avisos no momento da revisão.
- **Isolamento dos testes:** os testes de fila e de mudança para aguardando ativação agora usam o ciclo de migrations apropriado, sem deixar dados que contaminavam outras classes. Chaves de aplicação/JWT sintéticas foram definidas para os testes não dependerem dos segredos do `.env` local.
- **Tutorial:** incluídas três etapas sobre chat, escolha do aprovador, acompanhamento, edição e tradução, em pt/en/es. A elegibilidade acompanha `can_chat`. Corrigido o destaque da etapa de portfólio para funcionar também com área restrita. Atualizadas as orientações de gerente obrigatório e reenvio de ativação. Trocar idioma dentro do passeio não grava na conta nem envia ações de negócio.
- **Chat:** corrigido o uso da instância atual do editor no atalho de nova linha e a restauração de foco da barra de formatação, que podia competir com a movimentação do cursor. O caso de listas foi repetido três vezes após o ajuste, com 100 verificações em cada execução.
- **Relatórios:** uma indisponibilidade do detalhamento financeiro é tratada no próprio portfólio, preservando os outros indicadores; a tela global de erro não cobre mais um relatório que já tem tratamento local para 503.
- **Idiomas:** corrigidos os textos restantes identificados na auditoria de pt/en/es, incluindo permissões, percentual concluído e a legenda de informação ausente nos gráficos. A rodada final não deixou apontamentos de tradução.
- **Inclusão:** o cadastro administrativo deixa de exigir sobrenome por expressão regular. Nome com um único elemento continua sujeito aos limites de texto, sem retirada de acentos ou apóstrofos.
- **Scheduler:** notificações e limpezas executam dentro do processo PHP, sem depender de um `php` externo. O retorno de erro do comando continua sendo registrado como falha. Testes cobrem execução e propagação do erro.
- **Imagem Docker:** excluídos backups, evidências, dados de uploads locais e cache de testes do contexto. Diretórios vazios de armazenamento são preparados com as permissões necessárias. A instalação Python foi separada em uma camada reaproveitável.
- **Configuração:** modelo de produção atualizado e `JWT_SECRET` incluída. Sem essa chave, a autenticação da API de estoque/token falhava na imagem limpa. Adicionado verificador somente leitura, sem impressão de credenciais.

## Evidências automatizadas

| Verificação | Resultado |
| --- | --- |
| Suíte PHP local | 256 testes / 3.487 asserções aprovados |
| Suíte PHP na imagem de produção, PostgreSQL descartável | 256 testes / 3.487 asserções aprovados |
| Suítes de navegador | 36 de 36 aprovadas na rodada completa |
| Responsividade | 323 verificações: 320–1440 px, vertical/horizontal, leitura 100%/120% e dados longos |
| Tutorial | 756 verificações, sem erros; conteúdo completo pt/en/es e seleção por permissões |
| Idiomas | 144 verificações, sem falhas nem apontamentos pendentes |
| Chat após ajuste do editor | 170 verificações aprovadas; formatação repetida 3 × 100 verificações |
| Acessibilidade automatizada | Navegação por teclado, foco/modal, retorno de foco, leitura e ajustes públicos aprovados |
| E-mails | 36 prévias: seis variantes × três idiomas × duas larguras, sem envio real |
| Build Vite | Aprovado |
| Composer validate / requisitos de plataforma | Aprovados |
| Auditorias npm e Composer | Nenhum aviso encontrado na execução final |
| Sintaxe PHP | Sem erros nos 162 arquivos de aplicação/configuração/rotas/migrations verificados |
| Docker build | Imagem `spot:production-review` construída |
| Docker Compose | Configuração validada com valores sintéticos; nenhum serviço de produção iniciado |
| Smoke test da imagem | PHP 8.3.36; `/up`, `/`, `/forgot-password` e `/first-access/preview-token` retornaram 200 |
| Armazenamento/Python na imagem | Diretórios graváveis, PDO PostgreSQL disponível e pandas 2.3.3 carregado |

As últimas correções de strings e foco também foram verificadas pelos testes específicos de idiomas, portfólio, chat, formatação e teclado. O smoke test executa as rotas públicas pelo kernel HTTP dentro da imagem, com sessão/cache efêmeros e sem rede externa; ele não simula a emissão real de certificados pelo Caddy.

As evidências locais estão em `output/production-review/`: `phpunit-final.log`, `container-phpunit.xml`, `browser-release/results.json`, `languages-final.log`, `tutorial-final.log`, `docker-smoke.json`, `composer-audit-final.json` e `environment-check.json`. Essa pasta fica fora da imagem de produção.

## Pendências para liberação

1. **Ambiente de destino.** O ambiente revisado é local, com HTTP, debug e Vite. No servidor final, preencher `.env.production` a partir do modelo, configurar domínio/HTTPS, `APP_KEY`, `JWT_SECRET`, cookies seguros, PostgreSQL, remetente/SMTP e demais segredos. Preservar as chaves existentes em atualizações. O artefato não deve incluir `public/hot`.
2. **Responsáveis antigos.** A auditoria somente leitura encontrou cinco projetos; três estão sem `project_manager_id`, embora todos tenham gerente ativo na equipe. Conferir e indicar o gerente correto desses três projetos antes de transportar os dados. Nenhuma atribuição foi escolhida automaticamente.
3. **Integrações reais.** Validar recebimento de ativação, convite e recuperação em pt/en/es; worker das filas `emails,default`; scheduler por minuto; callbacks e permissões Google/Microsoft; agendas externas; serviço de tradução quando oferecido. Os testes usam serviços simulados ou transporte de e-mail em memória, sem destinatários reais.
4. **Operação.** Validar backups e restauração do PostgreSQL e `storage`, persistência dos volumes, reinício de worker/scheduler e acesso HTTPS no domínio final. A fila local estava sem jobs pendentes e sem falhas registradas; isso não comprova o funcionamento do worker no servidor final.
5. **Revisão humana.** Conferir teclado, leitor de tela, zoom do navegador, Firefox/Safari e dispositivos físicos. Os testes de interface são automatizados com Chromium e não certificam conformidade integral WCAG 2.2 AA nem substituem teste de carga com dados reais.

## Como repetir

Com dependências instaladas, schema de testes isolado e servidor local na porta 8000:

```sh
export PATH="$PWD/.runtime/bin:$PATH"
./php artisan test
npm run build
node scripts/verify-production-browser.mjs
./composer audit --no-dev
npm audit
```

Validação PHP no runtime Docker com PostgreSQL descartável, sem expor portas ou usar o banco local:

```sh
docker build -t spot:production-review .
python3 scripts/verify-container-tests.py
```

O runner necessita das dependências de desenvolvimento em `vendor` para executar o PHPUnit; a imagem final permanece instalada com `--no-dev`.

Depois de preencher os segredos e aplicar migrations **no servidor de destino**, executar:

```sh
docker compose --env-file .env.production exec web php scripts/check-production.php
```

Saída zero desse verificador confirma somente os controles implementados; as integrações reais e a revisão humana continuam necessárias. O roteiro de implantação e os cuidados com a primeira conta estão em `docs/implantacao-docker.md`.
