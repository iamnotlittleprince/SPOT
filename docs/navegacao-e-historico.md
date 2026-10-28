# Navegação e histórico do navegador

A navegação entre as páginas principais do Spot usa a History API do navegador, centralizada em `resources/js/useBrowserPage.js`.

- Cada troca de página registra uma entrada com `pushState`; clicar novamente na página atual não duplica a entrada.
- A página é identificada pelo parâmetro `page`, por exemplo `/?page=calendar` e `/?page=tasks`. Home usa a URL sem esse parâmetro; parâmetros existentes como `?home` são preservados.
- Voltar/avançar restaura a página pela URL usando `popstate`, sem criar uma nova entrada. Escolher outro destino depois de voltar segue o comportamento normal do histórico, substituindo a sequência adiante.
- Recarregar ou abrir a URL mantém a página selecionada quando a sessão estiver autenticada. O servidor continua aplicando autenticação e permissões; o parâmetro não concede acesso.
- Identificadores desconhecidos e páginas administrativas ausentes do menu do usuário exibem Home.
- Ao percorrer o histórico, menus e popovers abertos são fechados. O título da aba e os indicadores de navegação acompanham a página.

O escopo é a navegação entre páginas do dashboard, incluindo os atalhos do menu lateral, menu superior, perfil e busca. Filtros, abas internas, formulários, seleção de projetos/equipes e modais não são serializados no histórico; ao sair de uma tela, seu estado local pode ser reiniciado. O recurso não restaura dados de formulários não salvos.

## Verificação

Com servidor na porta 8000:

```bash
export PATH="$PWD/.runtime/bin:$PATH"
npm run build
node scripts/test-browser-navigation.mjs
node scripts/test-accessibility.mjs
node scripts/test-tutorial.mjs
```

O teste de navegação usa dados simulados e cobre voltar/avançar, URL, título, recarga, cliques repetidos, mudança de destino após voltar, fechamento de popovers e identificadores desconhecidos ou administrativos indisponíveis. As fixtures de navegador atendem também URLs com parâmetros para testar recargas da aplicação.

Resultado da execução: build aprovado; teste de navegação e de acessibilidade aprovados; tutorial com 645 verificações e nenhum erro. O teste do tutorial aguarda agora o dashboard após recargas, sem presumir retorno automático à Home.
