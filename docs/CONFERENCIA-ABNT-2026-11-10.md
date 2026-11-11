# Conferência ABNT — SPOT — 10/11/2026

**Resultado: formatação, referências e navegação documental revisadas e corrigidas; permanecem pendências institucionais de estrutura para o depósito final.** Não se declara conformidade integral enquanto esses elementos estiverem ausentes.

A conferência abrange monografia integrada, documentação técnica e guia de estudo, nas versões DOCX/ODT/PDF. A base técnica continua sendo o commit `146bd986867ecb25bc75db53c6d1944c6d89ae66`. O código da aplicação e as respostas da pesquisa não foram alterados. Versões anteriores a esta conferência foram preservadas em `revisoes/2026-11-10-antes-conferencia-abnt/`.

## Fontes consultadas

Foram conferidas as edições nas bibliotecas [UFES](https://biblioteca.ufes.br/normalizacao) e [UFABC](https://portal.biblioteca.ufabc.edu.br/servicos/normalizacao), com consulta aos requisitos pertinentes nas normas disponibilizadas por instituições:

| Norma | Aplicação na revisão | Fonte |
| --- | --- | --- |
| NBR 14724:2024, versão corrigida de 01/04/2025 | Estrutura e apresentação | [Documento disponibilizado pela Prefeitura do Rio de Janeiro](https://vigilanciasanitaria.prefeitura.rio/wp-content/uploads/sites/84/2025/10/ABNT-NBR-14724-2025-informacao-e-documentacao-trabalho-academico.pdf) |
| NBR 6023:2025 | Referências | [Acervo institucional da UFES](https://engenhariaedesenvolvimentosustentavel.ufes.br/sites/engenhariaedesenvolvimentosustentavel.ufes.br/files/field/anexo/abnt_nbr_6023_-_referencias.pdf) e [FHO](https://www.fho.edu.br/assets/documentos/FHO_Biblioteca_Referencia_ABNT_6023_2025.pdf) |
| NBR 10520:2023 | Chamadas de citação | [Repositório da UFAM](https://edoc.ufam.edu.br/handle/123456789/7242?locale=pt_BR) |
| NBR 6027:2012 | Sumário | [Documento disponibilizado pela UFF](https://ppgdc.uff.br/wp-content/uploads/sites/681/2023/05/NBR6027-Arquivo-para-impressa%CC%83o.pdf) |
| NBR 6028:2021 | Resumo e palavras-chave | [Documento disponibilizado pela UESB](https://www2.uesb.br/ppg/ppgeo/wp-content/uploads/2016/09/ABNT6028Resumo-atualizada-2021.pdf) |
| NBR 6024:2012 | Seções e numeração progressiva | Edição confirmada na UFABC e orientações da [Biblioteca da UFRA](https://bdta.ufra.edu.br/jspui/TREINAMENTO%20DE%20NORMALIZA%C3%87%C3%83O%20ATUALIZADO%20GRADUA%C3%87%C3%83O%202021.pdf) |

Os textos integrais das normas não foram copiados para o pacote da documentação. As orientações consultadas não constituem o manual específico do Colégio Dr. Clóvis Bevilacqua.

## Correções aplicadas

| Item | Ajuste e resultado observado |
| --- | --- |
| Títulos pós-textuais | Referências e apêndices centralizados; seções numeradas mantidas à esquerda. |
| Hierarquia | Sumário passou a reproduzir negrito e itálico dos níveis do texto; indicadores alinhados à esquerda. |
| Espaçamento | Corpo em 1,5; natureza, legendas, fontes e referências simples; espaços dos títulos e parágrafos padronizados. |
| Paginação | Cabeçalho textual a 2 cm; número menor; capa excluída da contagem e folha de rosto contada. Sequência conferida até o último apêndice. |
| Listas | Figuras e quadros completos; lista de tabelas acrescentada aos documentos com tabelas numéricas. Todas as páginas comparadas com os elementos reais. |
| Chamadas | Inseridas remissões no corpo para cada figura, quadro e tabela. |
| Seções | Acrescentado texto de abertura onde um título era seguido diretamente de outro título. |
| Referências e citações | Correspondência autor-data conferida nos dois sentidos; diferenciadas as obras ABNT de 2012 e as páginas Atlassian pelo sufixo a/b. |
| Datas | Substituídas indicações “s. d.” por copirraite verificado ou intervalo permitido. Páginas sem ano verificável usam `[20--]`, identificando somente o século, sem inventar ano. Evidência em `fontes-documentais/datas-referencias-verificadas.json`. |
| Destaques | Título separado do subtítulo nas referências; expressão *et al.* em itálico no corpo. |
| Resumos | Parágrafo único, conteúdo informativo, extensão recomendada e cinco palavras-chave separadas por ponto e vírgula. Português e inglês conferidos. |
| Siglas | Ampliada a lista e ajustadas definições no corpo; códigos, campos de banco e URLs preservados. |
| Tabelas | Removida a grade interna das tabelas estatísticas; preservados cabeçalho, fonte e distinção entre tabelas e quadros. |
| Exportação | Eliminadas páginas vazias; estabilizada a paginação após reabertura dos índices; links do GitHub ativos nos PDFs. |

Arial, recuo de primeira linha de 1,25 cm e escolhas de destaque compõem a padronização adotada; não são apresentados como a única solução admitida pela ABNT.

## Evidências e limites

O verificador `conferir_abnt_documentos.py` examina dimensões, estilos, resumos, localização dos títulos, numeração, correspondência das referências, páginas do sumário e das listas, limites de impressão e ausência de páginas vazias. Resultados detalhados: [conferencia-abnt-2026-11-10.json](fontes-documentais/conferencia-abnt-2026-11-10.json). A [validação dos artefatos](fontes-documentais/validacao-documentos-finais.json) também confere imagens e links.

A conferência visual utiliza páginas renderizadas de capa, folha de rosto, resumos, listas, sumário, texto, gráficos, referências e apêndices. A inspeção visual é por amostragem; os verificadores percorrem todas as páginas. Isso não equivale a revisão humana integral por bibliotecário, validação com leitor de tela ou aprovação da banca.

## Pendências institucionais

1. **Folha de aprovação.** Mantida fora do arquivo conforme a solicitação expressa do autor. A NBR 14724 a inclui na estrutura obrigatória. O documento atual não deve ser descrito como integralmente conforme para depósito enquanto essa ausência não for resolvida conforme a exigência da instituição. Não foram fabricados nomes de avaliadores, data ou assinaturas.
2. **Ficha catalográfica.** Não foi localizada uma ficha emitida para esta versão e nenhuma foi inventada. A NBR 14724 prevê os dados de catalogação junto à folha de rosto. A emissão e a aplicação ao TCC técnico precisam ser tratadas com a biblioteca ou coordenação do colégio.
3. **Modelo do colégio.** Não foi fornecido ou localizado no workspace um manual institucional específico. A instituição deve confirmar natureza do trabalho, nomenclatura do curso/grau, identificação do orientador, modelo de entrega e eventuais exigências adicionais. Os metadados foram preservados do DOCX original.

Os arquivos revisados podem ser usados na revisão com o orientador. A liberação institucional para depósito permanece vinculada às pendências acima.

## Repetição da conferência

Após regeneração e exportação, com as dependências documentais em ambiente separado:

```sh
python3 docs/finalizar_documentos.py
python3 docs/conferir_abnt_documentos.py
```

A revisão não exige repetir os testes da aplicação, pois este trabalho alterou apenas a documentação e suas ferramentas.
