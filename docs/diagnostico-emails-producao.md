# Diagnosticar e corrigir e-mails do Spot — VM Azure com Docker

Roteiro para o responsável pela produção. Os comandos são executados no terminal da VM, na pasta que contém `docker-compose.yml` e `.env.production`. Os nomes `web`, `worker` e `scheduler` referem-se ao Compose deste repositório; se a instalação usa outro arquivo, adaptar os nomes antes de executar.

O login Google/Microsoft e o envio de e-mails são configurações distintas. No Spot, convites de projeto e ativações são jobs `SendAccessEmail` na fila `emails`; o envio usa o transporte configurado em `MAIL_MAILER`. Corrigir OAuth de login não configura SMTP.

## 1. Registrar uma ocorrência

Escolher um único destinatário de teste autorizado. Registrar horário, ação utilizada e tipo de mensagem: convite de projeto, ativação de conta ou recuperação de senha. Conferir o endereço e as pastas spam/quarentena. Não disparar vários convites durante o diagnóstico.

## 2. Confirmar a instalação e o worker

```bash
cd /CAMINHO/REAL/DO/SPOT
docker compose --env-file .env.production ps
docker compose --env-file .env.production logs --tail=100 worker
```

O `worker` deve aparecer em execução, sem reinícios contínuos. No Compose do Spot, o comando esperado é:

```text
php artisan queue:work --queue=emails,default --sleep=3 --tries=3 --timeout=60
```

`Up` sozinho não comprova que o envio funciona. Se o worker está ausente, verificar o erro e a configuração antes de iniciá-lo: pendências válidas podem ser enviadas assim que ele voltar a consumir a fila. O scheduler é usado por atividades periódicas; sua ausência não é, por si, a causa de um convite imediato não enviado.

## 3. Conferir a configuração de correio

Editar a `.env.production` existente na VM, preservando suas outras configurações. Preencher com os dados reais fornecidos pelo serviço de e-mail:

```dotenv
QUEUE_CONNECTION=database
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=HOST_FORNECIDO_PELO_PROVEDOR
MAIL_PORT=587
MAIL_USERNAME=USUARIO_SMTP
MAIL_PASSWORD="CREDENCIAL_SMTP"
MAIL_FROM_ADDRESS=REMETENTE_AUTORIZADO_PELO_PROVEDOR
MAIL_FROM_NAME="Spot"
APP_URL=https://spot-tcc-alex.brazilsouth.cloudapp.azure.com
```

O exemplo usa a combinação usual de porta 587 com STARTTLS. Se o provedor exigir TLS implícito na porta 465, usar `MAIL_SCHEME=smtps` e `MAIL_PORT=465`. Seguir a combinação de porta e segurança do provedor; não desativar a validação de certificado para contornar erros.

Pontos importantes:

- `MAIL_MAILER=log` apenas registra a mensagem, e `array` a mantém em memória: nenhum dos dois entrega na caixa do destinatário.
- `MAIL_FROM_ADDRESS` precisa ser um remetente/domínio autorizado no serviço contratado.
- `MAIL_PASSWORD` é a credencial de correio exigida pelo provedor; não é `GOOGLE_CLIENT_SECRET` nem `MICROSOFT_CLIENT_SECRET`.
- Se houver `MAIL_URL`, verificar se ela está substituindo os campos separados. Evitar duas configurações conflitantes.
- `SPOT_ACTIVITY_EMAIL_ENABLED=false` desativa e-mails de atividade; não bloqueia os convites e ativações implementados em `SendAccessEmail`.
- Não imprimir a `.env.production`, executar comandos que despejem todas as variáveis nem compartilhar senhas/payloads de jobs.

Se disponível na versão publicada, executar a verificação somente leitura:

```bash
docker compose --env-file .env.production exec web php scripts/check-production.php
```

Ela identifica configurações básicas ausentes sem imprimir credenciais ou enviar mensagens. Resultado positivo não testa autenticação SMTP, conexão externa ou recebimento.

## 4. Verificar erros da aplicação e da fila

```bash
docker compose --env-file .env.production exec web php artisan queue:failed
docker compose --env-file .env.production exec worker sh -c 'tail -n 120 storage/logs/laravel.log'
```

O segundo comando corresponde ao arquivo padrão do Laravel. Se ele não existir, conferir `LOG_CHANNEL`: a instalação pode registrar em arquivos diários ou no console. Nesse caso, consultar o arquivo do dia ou `docker compose --env-file .env.production logs --tail=100 web worker`. A tabela de jobs com falha não inclui jobs ainda aguardando novas tentativas.

| Sinal observado | Interpretação e correção |
| --- | --- |
| `535`, `Authentication failed` | Conferir usuário, credencial e política SMTP do provedor. Uma senha de login pode não ser aceita para SMTP. |
| `Connection timed out` ou `Connection refused` | Conferir host, porta, DNS e saída de rede da VM e do contêiner. |
| Erro de TLS/certificado/STARTTLS | Conferir porta e `MAIL_SCHEME`, certificado do servidor e relógio da VM. |
| `550`, `553`, remetente não autorizado | Conferir remetente, verificação do domínio e autorização de envio. O texto completo do código distingue destinatário inválido, relay negado e outras recusas. |
| `429`, `454`, `451`, quota/rate limit | Conferir limite e situação da conta no provedor; aguardar ou corrigir conforme a resposta recebida. |
| Erro de PostgreSQL/tabela `jobs` ausente | Verificar conexão e migrations da versão instalada; planejar aplicação de migrations após backup. |
| `DecryptException`/erro de descriptografia | Confirmar que web e worker preservam a mesma `APP_KEY` usada ao criar os jobs. Não gerar uma chave nova para resolver. |
| Job conclui, mas nenhuma mensagem sai | Conferir transporte real, validade do convite e logs do provedor. Jobs de convite inválido, revogado, expirado ou já utilizado podem terminar sem envio. |
| Provedor aceitou, mas destinatário não recebeu | Consultar entrega, rejeição posterior, supressão, spam/quarentena e autenticação do domínio no provedor. Aceitação SMTP não garante caixa de entrada. |

A documentação de [filas Laravel](https://laravel.com/docs/13.x/queues) descreve consulta e repetição de jobs; a de [e-mail](https://laravel.com/docs/13.x/mail) descreve transportes e envio.

## 5. Investigar rede se houver timeout

Pedir ao responsável para testar a conexão ao host e à porta SMTP a partir do contêiner `worker`, além da resolução DNS. Conferir regras de saída do NSG, firewall da VM, firewall/proxy da rede e restrições do provedor.

No Azure, a porta 25 pode sofrer restrições conforme a assinatura. A orientação oficial diferencia essa situação de SMTP autenticado na porta 587, que não recebe a mesma restrição de plataforma. Não liberar uma porta de entrada na VM para resolver um envio de saída. [Diagnóstico SMTP no Azure](https://learn.microsoft.com/en-us/troubleshoot/azure/virtual-network/troubleshoot-outbound-smtp-connectivity).

## 6. Aplicar a correção ao ambiente em execução

Depois de corrigir a configuração e conferir as pendências que serão processadas:

```bash
docker compose --env-file .env.production up -d --force-recreate web worker scheduler
```

Esse comando recria os serviços com as variáveis da `.env.production`. Um simples `restart` não recarrega variáveis alteradas no arquivo `env_file`.

Se também foi recebido código novo, reconstruir a imagem:

```bash
docker compose --env-file .env.production up -d --build web worker scheduler
```

Se a instalação usa cache de configuração criado manualmente, limpar o cache de cada serviço de aplicação que o utiliza:

```bash
docker compose --env-file .env.production exec web php artisan config:clear
docker compose --env-file .env.production exec worker php artisan config:clear
docker compose --env-file .env.production exec scheduler php artisan config:clear
docker compose --env-file .env.production restart worker scheduler
```

O runtime padrão não cria esse cache durante o build. Esses comandos não substituem a recriação para carregar variáveis de ambiente novas. Preservar banco, uploads, `APP_KEY`, `JWT_SECRET` e volumes. Não executar `docker compose down -v`, `migrate:fresh` ou seed como parte deste diagnóstico.

## 7. Validar um único fluxo

Com uma conta de teste autorizada, gerar uma nova mensagem pelo próprio Spot e acompanhar os logs do worker e do provedor. Para ativação administrativa, nova entrada em “aguardando ativação” gera outro link; editar uma conta que já está nesse estado não dispara novo envio. Não alterar o status de uma conta real apenas para testar.

Se a mensagem ficou em `failed_jobs`, corrigir primeiro a causa e identificar um único UUID de `SendAccessEmail` correspondente ao teste. Se o convite continuar válido, o responsável pode repetir apenas esse job:

```bash
docker compose --env-file .env.production exec web php artisan queue:retry UUID_DO_JOB_DE_TESTE
```

Não usar `queue:retry all` para diagnóstico. Convites expirados, revogados ou substituídos precisam de um novo convite pelo fluxo autorizado. Repetir o job antigo não recupera sua validade. O worker pode já ter realizado uma entrega antes de uma falha posterior; conferir os registros antes de repetir.

## 8. Critério de conclusão e retorno ao suporte

Confirmar: worker ativo; ação de teste aceita; job processado; provedor registrou a entrega; destinatário recebeu a mensagem; link abre o domínio de produção e o fluxo esperado. Se o provedor indicar somente aceitação, continuar verificando a entrega final.

Se persistir, encaminhar apenas: horário/fuso, tipo de mensagem, estado do worker, identificador do job e trecho de erro com dados sensíveis ocultados. Não encaminhar `.env`, senhas, link de ativação, payloads completos ou logs integrais.

Este roteiro foi conferido com o código local e documentação oficial; não executou comandos na VM nem enviou e-mails reais.
