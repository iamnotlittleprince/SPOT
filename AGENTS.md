# Orientações para alterações no Spot

## Inclusão e acessibilidade

- Use WCAG 2.2 AA como objetivo. Não declare conformidade integral com base apenas em build ou testes automatizados.
- Prefira HTML semântico, rótulos visíveis e controles nativos. Botões apenas com ícone precisam de nome acessível; ícones decorativos não devem repetir a leitura do texto.
- Preserve uso por teclado, foco visível e ordem lógica. Modais devem ter nome, foco inicial, contenção de Tab, fechamento por Escape e retorno do foco. Use `resources/js/useModalFocus.js` nos fluxos compatíveis.
- Respeite `prefers-reduced-motion`, zoom e modo de cores forçadas. Não comunique estado apenas por cor. Verifique contraste e alvos de interação ao alterar componentes.
- Escreva instruções e erros claros, indicando como corrigir. Associe erros aos campos quando houver validação por campo; anuncie resultados assíncronos sem mover foco desnecessariamente.
- Não bloqueie colagem nem gerenciadores de senha. Use autocomplete apropriado.
- Preserve as traduções pt/en/es e os formatos locais. Marque conteúdo inserido por usuários com `translate="no"`.
- Aceite nomes sem exigir sobrenome ou remover acentos/apóstrofos. Não acrescente gênero, pronomes ou outros dados pessoais sem necessidade funcional.
- Ao concluir mudanças de interface, registre verificações realizadas e limitações. Execute build e testes pertinentes; para teclado/modal use `node scripts/test-accessibility.mjs` após build com servidor na porta 8000.
- A revisão humana inclui teclado, leitor de tela, zoom, telas pequenas e, quando disponível, testes com pessoas com deficiência. Não substitua esses resultados por suposições.

Consulte `docs/acessibilidade-e-inclusao.md` para escopo, pendências e critérios de aceite.

## Responsividade

- Preserve a busca e o acesso a idiomas, tema, foto do perfil e ações essenciais no celular. Não esconda textos de ações como solução para falta de espaço.
- Use as regras compartilhadas em `resources/css/responsive.css`; tabelas e quadros podem rolar em sua própria área, sem ampliar a largura da página inteira.
- Ao alterar layouts compartilhados, execute `npm run test:responsive` com o servidor local ativo. Inclua telas estreitas, orientação horizontal, nomes longos e leitura em 120%; registre os limites da avaliação.
