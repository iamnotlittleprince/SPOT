# Equipe inicial no cadastro do projeto

Na seção **02 — Responsáveis e período**, o bloco **Equipe do projeto** permite selecionar as pessoas que participarão do projeto antes de criá-lo.

## Uso

1. Busque usuários por nome ou e-mail. A busca também encontra nomes com acentos quando o termo é digitado sem acento.
2. Marque uma ou mais pessoas. O contador e os nomes selecionados continuam visíveis ao filtrar a lista.
3. Para retirar uma pessoa, desmarque sua opção ou use o botão de remover junto ao nome.
4. Clique em **Criar projeto**. A equipe é gravada com o projeto e fica disponível na área **Equipes** para ajustes posteriores.

A seleção é opcional. Sem pessoas marcadas, o projeto é criado sem equipe inicial. Não são criadas contas novas nem adicionados automaticamente o criador ou os gerentes: o bloco grava as pessoas escolhidas explicitamente.

## Regras e persistência

- O endpoint de parâmetros fornece `project_workers`: contas ativadas, ativas e da empresa atual. A lista de gerentes permanece separada e mantém a restrição aos três perfis já definidos.
- `POST /api/v1/projects` aceita `member_ids`, uma lista opcional com até 500 IDs distintos. A API valida tipo, existência, empresa e estado da conta; o navegador não é a única camada de validação.
- Projeto, impostos, membros e auditoria são gravados na mesma transação. Uma falha na inclusão de membros desfaz o cadastro inteiro. As contas selecionadas são conferidas novamente e bloqueadas para atualização durante essa etapa.
- O vínculo padrão é de analista, com as permissões de membro utilizadas pelo sistema. Contas com perfil de convidado na empresa atual recebem vínculo de convidado e acesso limitado a visualizar tarefas e arquivos; não são promovidas a analistas ou gerentes.
- A atribuição registra autor, data e evento `project.member_assigned`, reutilizando a auditoria e o fluxo de notificações existentes. As preferências e condições de envio continuam valendo.
- `member_ids` não é uma coluna de `projects`. A equipe fica em `project_members`. A edição posterior da equipe usa o fluxo existente em **Equipes**, sem substituir membros silenciosamente em um `PUT /projects/{id}`.
- Erros de validação preservam as escolhas e os campos preenchidos no navegador.

## Implementação e verificação

O seletor está em `resources/js/ProjectTeamPicker.jsx`, com estilos próprios em `resources/css/project-team.css`. A criação usa `SaveProjectRequest` e `ProjectController`; as mensagens têm traduções pt/en/es. Campos nativos, rótulos visíveis, estado anunciado, alvos de clique e layout em coluna mantêm o uso por teclado e celular.

Com o servidor local na porta 8000:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
node scripts/test-project-team.mjs
node scripts/test-project-managers.mjs
node scripts/test-management.mjs
node scripts/test-languages.mjs
npm run test:responsive
./php vendor/bin/phpunit tests/Feature/InitialProjectTeamTest.php tests/Feature/ProjectManagerEligibilityTest.php tests/Feature/ProjectV1CrudTest.php tests/Feature/TeamManagementTest.php tests/Feature/ProjectConfigurationTest.php tests/Feature/NotificationDeliveryTest.php
```

O teste específico do navegador cobre busca, seleção múltipla, remoção, preservação após erro e envio dos IDs, em desktop e em 320 px com leitura em 120%. A suíte PHP passou em 25 testes e 189 asserções, incluindo o rollback após uma falha simulada no segundo membro, vínculos limitados de convidados e o acesso do analista ao projeto atribuído.

Verificação complementar concluída: build aprovado; testes de seleção da equipe, gerentes e gestão aprovados; 239 verificações de responsividade e 144 de idiomas sem falhas. O relatório de idiomas mantém os avisos anteriores de revisão de alguns textos. `git diff --check` sem erros.
