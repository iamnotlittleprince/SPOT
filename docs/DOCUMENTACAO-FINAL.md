# Documentação final do SPOT — 10/11/2026

Código de referência: commit `146bd986867ecb25bc75db53c6d1944c6d89ae66`.

Repositório para consulta: [github.com/iamnotlittleprince/SPOT](https://github.com/iamnotlittleprince/SPOT).
Versão analisada: [consulta pelo commit](https://github.com/iamnotlittleprince/SPOT/tree/146bd986867ecb25bc75db53c6d1944c6d89ae66). O acesso depende das permissões do repositório. Os documentos desta edição são alterações locais até seu versionamento e envio ao remoto.

## Arquivos para entrega

| Documento | Leitura | Edição | Consulta |
| --- | --- | --- | --- |
| Monografia integrada | [PDF](Monografia_SPOT_documentacao_integrada_final.pdf) | [DOCX](Monografia_SPOT_documentacao_integrada_final.docx), [ODT](Monografia_SPOT_documentacao_integrada_final.odt) | [HTML](Monografia_SPOT_documentacao_integrada_final.html) |
| Documentação técnica | [PDF](documentacao-tecnica-spot-abnt.pdf) | [DOCX](documentacao-tecnica-spot-abnt.docx), [ODT](documentacao-tecnica-spot-abnt.odt) | [HTML](documentacao-tecnica-spot-abnt.html) |
| Guia de estudo e defesa | [PDF](tcc/GUIA-COMPLETO-TCC-SPOT.pdf) | [DOCX](tcc/GUIA-COMPLETO-TCC-SPOT.docx), [ODT](tcc/GUIA-COMPLETO-TCC-SPOT.odt) | [Markdown](tcc/GUIA-COMPLETO-TCC-SPOT.md) |
| Referências | [Lista completa](referencias-documentacao-final.md) | Incluídas nos documentos acima | Fontes oficiais e documentos primários |

Pacote: [SPOT-documentacao-final-2026-11-10.zip](SPOT-documentacao-final-2026-11-10.zip). Inclui os arquivos finais, imagens necessárias ao HTML, dados transcritos da pesquisa, fontes de geração e manifestos. Não inclui código integral, segredos, cadastros reais ou arquivos históricos de apresentação.

## Atualizações realizadas

- Arquitetura real: Laravel 13, React 18, Vite, PostgreSQL e relatórios Python/pandas.
- Regras finais: provisionamento administrativo; conferência apenas do e-mail na ativação; reenvio ao voltar para aguardando ativação; gerente obrigatório; chat com escolha de administrador ou gerente administrador como aprovador.
- E-mails em pt/en/es, tutorial, responsividade, recursos de leitura, privacidade, operação e integrações.
- Evidências da revisão: 256 testes PHP e 3.487 asserções, também em Docker, e 36 suítes de navegador. As evidências são as registradas em [revisão de produção](revisao-producao-2026-11-10.md); esta atualização documental não repetiu a suíte da aplicação.
- Pesquisa original preservada, com discrepâncias de totais e limitações explícitas; propostas de IA separadas do que foi implementado.
- Referências verificadas e link do GitHub, incluindo o commit consultado.

## Normalização e conferência

Conferência posterior: [relatório de revisão ABNT](CONFERENCIA-ABNT-2026-11-10.md), com correções realizadas e pendências de aprovação, catalogação e modelo institucional.

Foram aplicados A4; margens superior/esquerda de 3 cm e inferior/direita de 2 cm; Arial 12 no corpo; espaçamento 1,5; referências, fontes e legendas em espaçamento simples; paginação a partir do texto, contando os elementos desde a folha de rosto; sumário automático; numeração de seções, figuras, tabelas e quadros; citações autor-data; referências com acesso em 10 nov. 2026.

Referências normativas: NBR 14724:2024, NBR 6023:2025, NBR 10520:2023, NBR 6024:2012, NBR 6027:2012 e NBR 6028:2021. Edições conferidas nos serviços de [normalização da UFES](https://biblioteca.ufes.br/manuais-de-normalizacao) e [UFABC](https://portal.biblioteca.ufabc.edu.br/servicos/normalizacao). Os textos integrais das normas não foram reproduzidos. A aplicação destas orientações não equivale à certificação integral por bibliotecário ou à aprovação pela instituição. Requisitos específicos do modelo do colégio devem ser conferidos antes do depósito.

Os PDFs foram exportados pelo LibreOffice, com conferência de sumário, páginas vazias, limites de impressão, imagens e links externos. Ver [resultado das verificações](fontes-documentais/validacao-documentos-finais.json). Arial pode ser substituída por fonte metricamente compatível no ambiente de exportação; o arquivo editável especifica Arial. HTML e TXT são versões de consulta; o PDF registra a paginação conferida. A acessibilidade de leitura dos próprios documentos não foi homologada com leitor de tela.

A folha de aprovação foi retirada a pedido da equipe. Foram incluídas listas automáticas de figuras e quadros, com indicação de página. Autores, curso, instituição, orientador e local foram obtidos do documento original. Não foram inventados aprovação, assinaturas, depoimentos, medições ou benefícios financeiros.

## Preservação e reprodução

Os DOCX originais em Downloads não foram alterados. Os arquivos substituídos foram preservados em [revisoes/2026-11-10-anteriores](revisoes/2026-11-10-anteriores/). Os slides e roteiros anteriores continuam históricos e precisam ser sincronizados antes de uma apresentação; o guia foi atualizado sem sobrescrevê-los.

O [manifesto](fontes-documentais/manifesto-versao-final.json) identifica commit, hashes do código e documentos de origem. O [inventário de rotas](fontes-documentais/rotas-versao-final.json) registra 170 rotas totais do Laravel; o apêndice filtra as rotas da aplicação e informa seu critério.

Para reproduzir na versão identificada, instalar `python-docx`, `Pillow`, `beautifulsoup4` e `PyMuPDF` em ambiente de ferramentas separado; instalar LibreOffice Writer e Python UNO. Não adicionar essas dependências ao runtime da aplicação. Executar na raiz:

```sh
python3 docs/gerar_documentacao_final.py
python3 docs/atualizar_guias_finais.py
python3 docs/converter_documentos_uno.py docs/Monografia_SPOT_documentacao_integrada_final.docx docs/documentacao-tecnica-spot-abnt.docx docs/tcc/GUIA-COMPLETO-TCC-SPOT.docx
python3 docs/finalizar_documentos.py
python3 docs/conferir_abnt_documentos.py
```

Quando as bibliotecas estiverem em um ambiente virtual, o conversor UNO deve usar o Python do sistema que oferece o módulo `uno`. A conexão do LibreOffice usa perfil temporário próprio e não modifica a sessão gráfica do usuário. Revisar novamente os resultados após qualquer regeneração. Uma nova versão do código exige nova análise do conteúdo e atualização consciente da data de corte e evidências.
