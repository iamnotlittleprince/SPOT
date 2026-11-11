#!/usr/bin/env python3
"""Atualiza guias de estudo sem regravar os slides e roteiros históricos."""
from pathlib import Path
import shutil
from docx import Document
import gerar_documentacao_final as g


def main():
    folder = g.D/'tcc'
    archive = g.D/'revisoes/2026-11-10-anteriores/tcc'
    archive.mkdir(parents=True, exist_ok=True)
    for stem in ['GUIA-COMPLETO-TCC-SPOT', 'GUIA-DEFESA-TCC-SPOT']:
        for suffix in ['.md', '.txt', '.docx', '.pdf', '.html', '.odt']:
            src = folder/(stem+suffix)
            dst = archive/src.name
            if src.exists() and not dst.exists():
                shutil.copy2(src, dst)
    blocks = g.mono_blocks()
    position = next(i for i,b in enumerate(blocks) if b['type']=='h' and b['text'].startswith('APÊNDICE'))
    blocks[position:position] = [g.H(1,'11 PREPARAÇÃO PARA A DEFESA'),
        g.H(2,'11.1 Demonstração sugerida'),
        g.P('Preparar uma base sintética e demonstrar: acesso administrativo, cadastro de projeto com gerente, tarefa e apontamento, leitura de indicadores, pedido de chat com pessoa, motivo e aprovador escolhido, decisão do destinatário e conversa autorizada. Mostrar o tutorial e os três idiomas. Usar prévias de e-mail quando não houver SMTP homologado. Não expor nomes, mensagens, credenciais ou contratos reais.'),
        g.H(2,'11.2 Perguntas e limites'),
        g.T('Questões para ensaio',['Pergunta','Resposta sustentada pela versão final'],[
          ['Os testes comprovam ausência de defeitos?', 'Não. Os 256 testes e 3.487 asserções cobrem cenários definidos; as 36 suítes de navegador complementam a verificação.'],
          ['Quem aprova o chat?', 'O administrador ou gerente administrador ativo da mesma empresa escolhido pelo solicitante, sem autoaprovação; vínculos diretos de equipe dispensam pedido.'],
          ['Qual banco é usado?', 'PostgreSQL. SQLite não descreve o ambiente da versão final.'],
          ['Há inteligência artificial?', 'Há integração opcional de tradução automática. Não foi entregue agente generativo de planejamento ou decisão gerencial.'],
          ['A pesquisa demonstra ganho de receita?', 'Não. A amostra de conveniência descreve percepções; não houve estudo de impacto antes/depois.'],
          ['Já pode afirmar produção homologada?', 'A configuração real, os responsáveis legados, SMTP/OAuth e avaliação humana ainda precisam de aceite.'],
          ['Onde consultar a implementação?', g.REPO+'; commit '+g.COMMIT+'.']]),
        g.H(2,'11.3 Material histórico'),
        g.P('Os slides e roteiros produzidos anteriormente foram preservados. Eles não foram sincronizados automaticamente nesta revisão: números, capturas, tempos e falas precisam de conferência antes da apresentação. Este guia e a monografia final identificam a referência técnica vigente, sem inventar duração de ensaio ou aprovação da banca.')]
    blocks = g.assemble(blocks, True)
    doc = Document(g.D/'Monografia_SPOT_documentacao_integrada_final.docx')
    def summary(title):
        ps = doc.paragraphs
        at = next(i for i,p in enumerate(ps) if p.text==title)
        return ps[at+1].text
    base = folder/'GUIA-COMPLETO-TCC-SPOT'
    g.write_document(base, 'SPOT: guia atualizado de estudo e defesa', 'tech', blocks, summary('RESUMO'), summary('ABSTRACT'))
    md=['# SPOT — Guia atualizado de estudo e defesa', '', 'Versão técnica de 10/11/2026. Código: '+g.COMMIT+'.', '', 'Repositório: '+g.REPO, '']
    for b in blocks:
        typ=b['type']
        if typ=='h': md += ['#'*min(6,b['level']+1)+' '+b['text'],'']
        elif typ=='p': md += [b['text'],'']
        elif typ=='link': md += ['['+b['text']+']('+b['url']+')','']
        elif typ=='figure':
            rel=Path(__import__('os').path.relpath(b['path'],folder)).as_posix()
            md += ['!['+b['title']+']('+rel+')','', 'Fonte: '+b['source'],'']
        elif typ=='table':
            md += [b['title'],'','| '+' | '.join(b['headers'])+' |','| '+' | '.join(['---']*len(b['headers']))+' |']
            md += ['| '+' | '.join(str(c).replace('|','/').replace('\n',' ') for c in row)+' |' for row in b['rows']]
            md += ['', 'Fonte: '+b['source'],'']
        elif typ=='references':
            md += ['## REFERÊNCIAS','']
            for _,author,title,desc,url in b['items']:
                md += [author+' **'+title+'**. '+desc+((' Disponível em: '+url+'. Acesso em: '+g.ACCESS+'.') if url else ''),'']
    base.with_suffix('.md').write_text('\n'.join(md))
    base.with_suffix('.txt').write_text('\n'.join(md))
    for ext in ['.md','.txt','.docx','.html']:
        shutil.copy2(base.with_suffix(ext), folder/('GUIA-DEFESA-TCC-SPOT'+ext))
    print('Guias atualizados; versões anteriores preservadas.')


if __name__=='__main__':
    main()
