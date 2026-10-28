# Documentos vinculados a projetos e tarefas

Implementado em 28/10/2026.

## Uso

Na tela **Meus documentos**, escolha **Projeto** e, se necessário, **Tarefa** antes de **Enviar arquivo**. Também é possível escolher uma tarefa primeiro: o projeto dela é preenchido automaticamente. A seleção de projeto limita as tarefas oferecidas; mudar o projeto limpa a tarefa anterior.

Em um arquivo já enviado, use o botão com ícone de vínculo, **Atribuir documento**, para alterar a associação sem reenviar o arquivo. Escolha **Sem tarefa** para manter somente o projeto, ou **Sem projeto** para remover toda a associação. Confirme em **Salvar vínculo**.

A coluna **Projeto / tarefa** mostra a associação. A busca também encontra o título da tarefa; o filtro de projetos inclui documentos vinculados às tarefas desse projeto.

## Acesso e persistência

- O documento continua privado ao proprietário. Atribuir não compartilha o arquivo com a equipe.
- Listagem, alteração, download e exclusão respeitam o proprietário e a empresa atual.
- Os destinos pertencem à empresa atual e seguem a visibilidade de projetos e tarefas do usuário. Sem acesso a todas as tarefas, são oferecidas as tarefas sob sua responsabilidade ou com sua coparticipação.
- Projetos finalizados continuam disponíveis conforme as regras existentes de consulta histórica. Associar um documento privado não altera o projeto nem a tarefa.
- A API rejeita tarefa sem acesso e tarefa de projeto diferente. Remover um vínculo continua possível depois de perder acesso ao destino.
- A alteração modifica apenas `project_id` e `task_id`; mantém conteúdo, nome, tamanho, proprietário e localização do arquivo. A auditoria registra os valores anteriores e novos com `document.updated`, sem registrar operações sem mudança.
- A nova coluna opcional `project_documents.task_id` possui chave estrangeira com `ON DELETE SET NULL`. Excluir uma tarefa preserva o arquivo e seu vínculo com o projeto.
- Arquivos anteriores permanecem válidos com `task_id = null`.

## API

`GET /api/v1/documents` retorna `documents`, `projects` e `tasks`. As opções contêm somente os dados necessários à seleção; os documentos incluem as relações `project` e `task`.

`POST /api/v1/documents` recebe o arquivo e os identificadores opcionais `project_id` e `task_id`. Quando só a tarefa é informada, o servidor determina o projeto.

`PATCH /api/v1/documents/{id}` recebe ambos os campos, aceitando `null` para limpar o vínculo:

```json
{"project_id": 12, "task_id": 34}
```

Erros de seleção retornam 422. Acesso a documento de outro proprietário ou empresa retorna 404. Rotas continuam autenticadas por sessão e protegidas por CSRF.

Migração aplicada no ambiente local: `database/migrations/2026_10_28_170000_add_task_to_project_documents.php`. Em outros ambientes, executar as migrações na implantação.

## Verificação

- `tests/Feature/DocumentAssignmentTest.php`: envio sem vínculo/com projeto/com tarefa, inferência do projeto, alteração e remoção, preservação do arquivo, auditoria e rollback, isolamento por usuário/empresa, destinos incompatíveis, participação e remoção da tarefa.
- `scripts/test-document-assignment.mjs`: fluxo de envio e alteração no navegador, recuperação de erro 422, remoção, Escape, contenção e retorno do foco, desktop e celular de 320 px com leitura em 120%.
- Build e verificações compartilhadas de responsividade, acessibilidade e idiomas.

As verificações de navegador usam respostas de API simuladas; os testes PHP exercitam persistência e autorização reais no banco de testes. Não foi feita avaliação com leitor de tela nem testes com usuários com deficiência nesta alteração.
