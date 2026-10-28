# Elegibilidade dos gerentes de projetos

Os campos **Gerente de contas** e **Gerente do projeto**, tanto na criação quanto na edição, aceitam apenas usuários dos perfis:

- Administrador global: perfil existente `administrador`.
- Gestor-administrador: `gestor-administrador`.
- Gestor: `gestor`.

O usuário deve estar ativo, com conta ativada, na mesma empresa atual. O perfil elegível também precisa estar vinculado a essa empresa: ter perfil de gestor em outra empresa não habilita a indicação. A existência de permissões individuais ou um cargo com nome de gerente não substitui o perfil exigido.

A regra está centralizada em `app/Domain/Projects/ProjectManagers.php`. O endpoint `/api/v1/parameters` fornece uma lista `managers` para os dois seletores, preservando `users` para as demais funções do sistema, como a seleção de analistas. Os formulários não usam a lista geral como alternativa quando não há gerentes elegíveis.

`SaveProjectRequest` aplica a mesma regra por meio de `EligibleProjectManager`, na criação e atualização de projetos, inclusive quando a requisição é enviada diretamente à API. Valores indevidos retornam 422 com erro associado ao respectivo campo e orientação para escolher um perfil permitido. Os campos continuam opcionais e aceitam `null`.

Não houve alteração automática de gerentes já registrados nem dos perfis das contas. Ao reenviar um responsável antigo que não atenda à regra, é necessário escolher alguém elegível ou remover a indicação antes de salvar. A configuração de papéis de membros da equipe é distinta desses dois campos de responsabilidade e não foi alterada.

## Verificação

- Testes PHP: dez testes e 111 asserções aprovados, cobrindo elegibilidade, parâmetros, criação, edição, empresa, contas inativas/pendentes, perfis permitidos e bloqueio de analistas/convidados.
- Teste de navegador específico: seletores de criação e edição exibem somente a lista de gerentes; os IDs escolhidos são enviados corretamente.
- Teste existente de gestão de projetos: aprovado, incluindo cadastro, edição, finalização/reabertura, custos e cadastros.
- Build de produção e `git diff --check`: aprovados.

```bash
./php vendor/bin/phpunit tests/Feature/ProjectManagerEligibilityTest.php tests/Feature/ProjectV1CrudTest.php tests/Feature/ProjectApiTest.php tests/Feature/ProjectConfigurationTest.php tests/Feature/ProjectGovernanceTest.php
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
node scripts/test-project-managers.mjs
node scripts/test-management.mjs
```
