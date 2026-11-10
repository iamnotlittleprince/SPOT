# Padrão dos e-mails do Spot

Referência visual: e-mail de redefinição de senha. Todos os envios encontrados em `app/` usam agora `resources/views/mail/layout.blade.php`: fundo claro, cartão de até 600 px, cabeçalho azul Spot, título, conteúdo legível, botão azul, link alternativo e rodapé automático. Ação e link alternativo são parciais compartilhadas. O layout tem tabelas de apresentação, estilos inline e ajuste de padding para celular.

## Envios cobertos

- Redefinição de senha: mantém textos pt/en/es, URL, expiração e alternativa em texto simples.
- Ativação administrativa: HTML e texto simples via `AccountActivationMail`, validade no fuso da conta e aviso de link pessoal. Continua passando pelas verificações de validade/revogação da fila. Nenhum gerador público de link foi reintroduzido.
- Convite para projeto: mesmo padrão, mantendo autor, projeto, validade e URL pessoal.
- Notificação imediata, resumo diário e semanal: cartões de atividades, botão para o Spot e instruções de preferências.

A estrutura é traduzida para pt/en/es usando a preferência do destinatário. Convites para e-mails sem conta usam português. Títulos, corpos de atividades e nomes fornecidos por pessoas são preservados e escapados no HTML; não são traduzidos automaticamente. Versões em texto simples mantêm URLs sem entidades HTML.

## Verificação

```bash
./php artisan test --filter='StandardizedMailTest|LocalizedResetMailTest|AccessEmailQueueTest|ProjectInvitationTest|NotificationDeliveryTest'
PATH="$PWD/.runtime/bin:$PATH" node scripts/test-system-mail-layout.mjs
PATH="$PWD/.runtime/bin:$PATH" npm run build
```

Prévias sintéticas sem envio real:

```bash
mkdir -p output/mail-standardization
./php scripts/render-system-mail-previews.php output/mail-standardization
```

A verificação visual automatizada cobre 18 prévias (seis variantes em três idiomas) em 320 e 700 px, conteúdo longo, ausência de expansão horizontal e ação com altura de pelo menos 44 px. Revisão visual pontual do convite em espanhol no navegador. Testes cobrem escape de HTML, links, multipart texto/HTML, idioma de ativação entregue pela fila, repetição em falha SMTP, expiração/revogação e preferências de notificações.

Não houve envio para destinatários reais nem validação em Gmail/Outlook/Apple Mail, leitor de tela ou simulação dos temas próprios de clientes de e-mail. Esses clientes podem alterar cores, fontes e cantos. A avaliação não certifica conformidade integral com WCAG.

## Reenvio ao alterar situação

Quando um administrador muda a situação de uma conta de outro estado para `pending_activation` (“Aguardando ativação”), a atualização gera um novo link e agenda `SendAccessEmail` na fila `emails`, depois da confirmação da transação. Links anteriores não usados são revogados. Cada nova entrada nesse estado repete o envio. Alterações de cargo, departamento, perfil ou permissões de uma conta que já está pendente não geram outro e-mail. O destinatário recebe o mesmo modelo padronizado e seu idioma preferido; a validade é a configurada na ação administrativa (atualmente 48 horas).

O envio depende do processamento da fila `emails` e do transporte de e-mail. Atualização da conta, auditoria e criação do link são transacionais; o registro da conta é bloqueado durante a alteração para evitar duplicação por atualizações simultâneas do mesmo estado. A resposta informa `activation_email_queued`, sem expor o token na resposta de atualização.

Validação: `./php artisan test --filter='ActivationStatusEmailTest|AdministrativeProvisioningTest|AccessEmailQueueTest'` — 18 testes e 124 asserções aprovados. Cobertura inclui reentrada no status, revogação, fila com link correspondente, idioma espanhol, alterações sem reenvio e autorização administrativa. Nenhum e-mail real foi enviado.
