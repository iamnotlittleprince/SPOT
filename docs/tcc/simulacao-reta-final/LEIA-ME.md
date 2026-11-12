# Spot na reta final do TCC: levantamento e simulação

Elaborado em **12/11/2026**, a partir dos arquivos locais. Este pacote reúne o conhecimento documental sobre o Spot e um cenário de como os quatro autores poderiam organizar a conclusão do próprio TCC com o sistema.

**Os nomes dos autores são documentados; as funções, tarefas, horas, conversas e acontecimentos deste cenário são propostos e simulados.** Não foram criadas contas, projetos ou mensagens no banco, nem executada uma implantação. O exercício não constitui relato de uso real, pesquisa com participantes ou comprovação de ganho de produtividade.

## Por onde começar

| Material | Conteúdo |
| --- | --- |
| [Levantamento consolidado](LEVANTAMENTO-SPOT.md) | Origem, pesquisa, funcionalidades, arquitetura, versões, evidências e pendências |
| [Perfis dos quatro membros](PERFIS-DA-EQUIPE.md) | Responsabilidades propostas, permissões, entregas e revisão cruzada |
| [Simulação da reta final](SIMULACAO-RETA-FINAL.md) | Preparação, cronograma de duas semanas, decisões, conversas fictícias e roteiro de demonstração |
| [Quadro de tarefas](QUADRO-SIMULADO.md) | 28 tarefas, responsáveis, revisores, prazos relativos e estado final |
| [Planilha CSV](tarefas-simuladas.csv) | Mesmas tarefas, critérios de aceite, duração sintética e dependências do roteiro |
| [Inventário das fontes](INVENTARIO-FONTES.md) | 192 arquivos anteriores a esta entrega, com classificação e tratamento |
| [Manifesto das fontes](fontes.json) | Caminhos, hashes SHA-256, tamanhos e extração textual |
| [Cenário estruturado](cenario.json) | Quatro perfis, projeto conceitual, tarefas e 102 eventos sintéticos |
| [Resultado calculado](resultado-simulacao.json) | Totais do cenário e checagens de consistência |

## Resultado do exercício

Foram distribuídas 28 tarefas: **24 concluídas no cenário, uma em revisão e três a fazer**. O planejamento soma 73 horas; a duração criada para o exercício soma 69,75 horas, incluindo uma revisão em andamento. Esses números não são medições da equipe e não servem para calcular economia ou eficácia.

O projeto permanece aberto: folha de aprovação, catalogação/modelo institucional e autorização de entrega continuam condicionados às confirmações necessárias. Marcar uma tarefa como concluída no cenário não altera os documentos originais nem resolve uma pendência real.

## Reproduzir a simulação documental

Na raiz do repositório:

```sh
python3 docs/tcc/simulacao-reta-final/gerar_simulacao.py
```

O script usa somente a biblioteca padrão do Python e regrava os quatro arquivos gerados: `cenario.json`, `resultado-simulacao.json`, `tarefas-simuladas.csv` e `QUADRO-SIMULADO.md`. Ele verifica transições de estado e dependências do roteiro em memória. Não chama a aplicação, não lê `.env`, não envia e-mails e não grava no banco. Isso verifica a coerência dos dados sintéticos, não o funcionamento integral do Spot.

O CSV e o JSON são materiais de apoio. **Não existe importador automático deste pacote implementado no Spot.** Para percorrer o cenário na aplicação, usar uma base de demonstração separada, contas próprias de teste e os passos descritos no roteiro.

## Escopo e verificações desta entrega

Foram inventariados README, AGENTS, todos os arquivos de `docs/` anteriores a este pacote e os documentos de assunto Spot/TCC/monografia/pesquisa/memorial e materiais auxiliares pertinentes encontrados no primeiro nível de Downloads. A busca por nomes pertinentes em Documentos, fora deste repositório, não encontrou outros documentos. Não houve varredura de toda a máquina, discos externos, nuvem ou conversas anteriores.

Houve extração de texto ou listagem de pacote em 143 dos 192 arquivos, sem falhas de extração. Dois CSVs auxiliares tiveram sua estrutura consultada, sem uso dos valores; os demais são recursos visuais inventariados; sua aparência não foi novamente revisada. A extração inclui DOCX, ODT, PPTX e PDF, mas não substitui inspeção de imagens, gráficos ou paginação. Arquivos históricos e exportações do mesmo trabalho não foram contados como pesquisas independentes.

Foi executado o gerador e conferida a coerência dos dados, dos links relativos deste pacote e do diff. Nenhuma interface ou regra da aplicação foi alterada; build e testes de navegador não foram repetidos nesta entrega documental. Resultados históricos do produto são citados com a fonte e data correspondentes. Revisão humana dos materiais, atribuições reais da equipe e data efetiva da defesa permanecem por confirmar.
