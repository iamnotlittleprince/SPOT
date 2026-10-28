# Notificações e atualização automática

Implementação de 26/10/2026. Complementa os itens parciais de integrações/e-mail, painéis e tarefas/agenda do material do TCC.

## Fluxo

`AuditRecorder` grava o evento de negócio e chama `BusinessNotifications`, que seleciona destinatários autorizados e persiste uma entrega em `notification_deliveries`. Não chama SMTP durante a requisição da operação. Nas transações existentes, a entrega é revertida junto com o evento em caso de rollback.

O comando `notifications:dispatch`, agendado a cada minuto, executa `DueReminders`, entrega os avisos internos e forma os lotes de e-mail. `NotificationCenter` e o job `SendNotificationBatch` revalidam acesso, empresa, preferências, estado da tarefa e validade do evento de agenda. Os lotes são enviados pela fila `emails`, com três tentativas e intervalos de 60/300 segundos.

O catálogo da tela mostra apenas eventos produzidos pelo sistema: atribuição/alteração de projetos e tarefas, finalização/reabertura, prazo de tarefas, compromissos próximos, arquivos próprios, aprovação de despesas, pendências de aprovação e estado de convites. Eventos de segurança usam as preferências específicas da conta. Comentários e revisão de arquivos não são oferecidos como opções de módulos inexistentes.

Os documentos continuam privados por proprietário. Uma notificação de envio de arquivo é destinada ao proprietário, não expõe o arquivo aos demais membros.

## Prazos, canais e resumo

- Tarefas: alerta na data ou no dia anterior ao vencimento e outro ao entrar em atraso, uma vez por fase/data/responsável ou participante. Lembretes consultam os últimos 30 dias, sem reabrir todo o histórico de atrasos.
- Agenda: alerta quando o compromisso registrado no Spot começa nos próximos 30 minutos. Eventos criados pelo Spot em Google/Outlook/Teams têm registro local; eventos exclusivamente externos que nunca foram cadastrados no Spot não recebem esses lembretes internos.
- Convites: aviso ao emissor quando aceitos/revogados ou a menos de 24 horas da expiração.
- Silêncio: posterga avisos internos e e-mail para o fim da faixa, inclusive quando atravessa a meia-noite, no fuso do usuário. Início/fim iguais são rejeitados ao ativar a faixa.
- `none`: e-mail imediato, sem resumo. A interface esclarece isso.
- Diário: um lote por dia, a partir do horário configurado.
- Semanal: um lote por semana, a partir de segunda-feira no horário configurado. Se o scheduler voltar depois do horário, envia o lote disponível da semana.
- Avisos posteriores ao resumo aguardam o período seguinte. Cada lote inclui até 500 eventos; eventuais excedentes permanecem pendentes para o próximo período.
- A caixa de entrada independe da frequência de e-mail. Eventos de agenda já iniciados e lembretes obsoletos não são enviados quando chega a hora da entrega.

## Ativação e operação

A migração `2026_10_26_160000_create_notification_delivery_tables.php` cria a outbox e os lotes. Aplicar com `./php artisan migrate --force`, sem executar seed no banco do usuário.

O envio externo de **atividades** exige SMTP configurado e `SPOT_ACTIVITY_EMAIL_ENABLED=true`. O padrão é `false`: permite usar notificações internas sem enviar novos e-mails aos contatos existentes durante implantação. Eventos criados com esse envio desativado não acumulam uma campanha de e-mails retroativos. Convites/ativação continuam seguindo a fila de acesso já existente, independente dessa chave.

Após habilitar ou alterar configuração, recarregar o cache de configuração conforme o ambiente e reiniciar o worker sob seu supervisor. Não é necessário executar manualmente o comando de entrega com o navegador aberto. Manter o scheduler existente (`iniciar.sh` ou serviço scheduler no Compose) e o worker da fila `emails` ativos.

Consulta sem envio: `./php artisan notifications:status`. Falhas da fila: `./php artisan queue:failed`; reprocessamento controlado: `./php artisan queue:retry UUID`. O job revalida os dados ao tentar novamente. `notifications:dispatch` é uma operação de entrega, não uma consulta.

Chaves únicas e locks evitam duplicação normal por repetição do scheduler ou de jobs. Como no restante do sistema, uma falha após o SMTP aceitar a mensagem, antes de confirmar a transação, pode causar reenvio: não há garantia de entrega exatamente uma vez.

## Painéis e integrações

`useAutoRefresh.js` atualiza Home, caixa de entrada e portfólio a cada 30 segundos quando visíveis; agenda, a cada 60 segundos. Atualizações também são solicitadas ao recuperar foco ou após mutações, inclusive entre abas via evento de armazenamento. O hook impede sobreposição das próprias consultas e remove seus timers/listeners ao desmontar. Mantém filtros aplicados; editar um período ainda não aplicado não muda a consulta automática do portfólio.

Isso é atualização automática por consulta periódica, não WebSocket ou push instantâneo. O intervalo de geração de alertas é de um minuto, somado ao intervalo de leitura da tela e ao processamento da fila.

A agenda lê páginas de eventos do Google e Microsoft Graph, no máximo 20 páginas por consulta, avisando para reduzir o intervalo se houver mais. Links de paginação Microsoft são limitados a HTTPS no host Graph, sem encaminhar tokens a outros hosts. Requisições têm limites de conexão e resposta. Datas informadas sem offset são interpretadas no fuso do usuário e normalizadas para UTC; a conexão PostgreSQL também fixa UTC. A configuração não reescreve instantes históricos gravados.

O gerenciador de integrações diferencia conta vinculada, calendário autorizado e configuração da aplicação, sem expor segredos. Serviços reais dependem das credenciais e consentimentos do provedor; os demais aplicativos do menu continuam sendo atalhos externos.

## Testes

`NotificationDeliveryTest` cobre isolamento, eventos de negócio, rollback, deduplicação, canais, silêncio, resumos, revogação, lembretes, ativação de e-mail, falha SMTP e repetição do lote. `CalendarIntegrationTest` cobre paginação, renovação de token, confidencialidade, criação Google/Teams, UTC e rejeição de nextLink externo. Testes usam SMTP/HTTP simulados e não enviam mensagens reais.

`node scripts/test-live-updates.mjs` verifica timer, atualizações entre abas e após eventos, preservação de filtros e limpeza ao sair. Os scripts de tarefas e portfólio continuam cobrindo CRUD, visões e gráficos.
