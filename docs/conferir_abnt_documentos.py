#!/usr/bin/env python3
"""Confere geometria, estilos, referências cruzadas e paginação dos documentos SPOT.
Não certifica aprovação institucional nem substitui a revisão da ficha catalográfica.
"""
from pathlib import Path
import json
import re
import unicodedata
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
import pymupdf
import gerar_documentacao_final as generator

ROOT=Path(__file__).resolve().parent
BASES=[ROOT/'Monografia_SPOT_documentacao_integrada_final',ROOT/'documentacao-tecnica-spot-abnt',ROOT/'tcc/GUIA-COMPLETO-TCC-SPOT']


def compact(text):
    return re.sub(r'[.\s\u00ad]+','',unicodedata.normalize('NFKC',text))


def check(base):
    doc=Document(base.with_suffix('.docx'))
    pdf=pymupdf.open(base.with_suffix('.pdf'))
    tests=[]
    def verify(name,condition,detail=None):
        tests.append({'item':name,'aprovado':bool(condition),'detalhe':detail})
    verify('A4 em todas as seções',all(abs(s.page_width.cm-21)<.02 and abs(s.page_height.cm-29.7)<.02 for s in doc.sections))
    verify('Margens 3/3/2/2 cm',all(abs(s.top_margin.cm-3)<.02 and abs(s.left_margin.cm-3)<.02 and abs(s.bottom_margin.cm-2)<.02 and abs(s.right_margin.cm-2)<.02 for s in doc.sections))
    normal=doc.styles['Normal']
    verify('Corpo Arial 12',normal.font.name=='Arial' and normal.font.size.pt==12)
    verify('Corpo em entrelinha 1,5',normal.paragraph_format.line_spacing==1.5)
    verify('Cabeçalho a 2 cm e paginação menor',abs(doc.sections[-1].header_distance.cm-2)<.02 and next(s for s in doc.styles if s.style_id=='Header').font.size.pt==10)
    verify('Natureza em espaço simples e metade direita',doc.styles['Natureza'].paragraph_format.line_spacing==1 and abs(doc.styles['Natureza'].paragraph_format.left_indent.cm-8)<.02)
    verify('Referências à esquerda em espaço simples',doc.styles['Referencia'].paragraph_format.alignment==WD_ALIGN_PARAGRAPH.LEFT and doc.styles['Referencia'].paragraph_format.line_spacing==1)
    verify('Fontes e legendas menores e uniformes',all(doc.styles[name].font.size.pt==10 for name in ['Fonte','LegendaFigura','LegendaQuadro','LegendaTabela']))
    headings=[p for p in doc.paragraphs if p.style.name.startswith('Heading')]
    verify('Títulos pós-textuais centralizados',all(p.alignment==WD_ALIGN_PARAGRAPH.CENTER for p in headings if p.text.startswith(('REFERÊNCIAS','APÊNDICE'))))
    for title in ['RESUMO','ABSTRACT']:
        at=next(i for i,p in enumerate(doc.paragraphs) if p.text==title)
        body=doc.paragraphs[at+1].text
        verify(title+': parágrafo único e 150–500 palavras',150<=len(body.split())<=500 and '\n' not in body,len(body.split()))
        keywords=doc.paragraphs[at+2].text
        verify(title+': palavras-chave separadas por ponto e vírgula',keywords.endswith('.') and len(keywords.split(';'))==5)
    outline=pdf.get_toc()
    first=next(page-1 for level,title,page in outline if title.startswith('1 '))
    verify('Sem numeração impressa nos pré-textuais',all(not any(b[4].strip().isdigit() and b[1]<78 and b[0]>500 for b in pdf[i].get_text('blocks')) for i in range(first)))
    page_errors=[]
    for i in range(first,len(pdf)):
        numbers=[b[4].strip() for b in pdf[i].get_text('blocks') if b[4].strip().isdigit() and b[1]<78 and b[0]>500]
        if numbers!=[str(i)]:page_errors.append(i+1)
    verify('Paginação contínua: folha de rosto contada; capa excluída',not page_errors,page_errors)
    # Acrescentar qualquer continuação do sumário entre seu início e a introdução.
    toc_start=next(i for i in range(first) if 'SUMÁRIO' in pdf[i].get_text())
    toc_text=compact('\n'.join(pdf[i].get_text() for i in range(toc_start,first)))
    missing=[title for level,title,page in outline if compact(title+str(page-1)) not in toc_text]
    verify('Todos os itens do sumário com título e página corretos',not missing,missing)
    for label,style_name in [('Figura','LegendaFigura'),('Quadro','LegendaQuadro'),('Tabela','LegendaTabela')]:
        captions=[p.text for p in doc.paragraphs if p.style.name==style_name]
        if not captions:continue
        title='LISTA DE '+{'Figura':'FIGURAS','Quadro':'QUADROS','Tabela':'TABELAS'}[label]
        page=next((p for p in list(pdf)[:first] if title in p.get_text()),None)
        errors=[]
        for caption in captions:
            target=next((i for i in range(first,len(pdf)) if pdf[i].search_for(caption)),None)
            if target is None or page is None or compact(caption+str(target)) not in compact(page.get_text()):errors.append(caption)
        verify(title+': todos os itens e páginas',not errors,{'quantidade':len(captions),'erros':errors})
        text='\n'.join(p.text for p in doc.paragraphs if p.style.name=='Normal')
        absent=[caption for caption in captions if not re.search(r'\b'+re.escape(caption.split(' — ')[0])+r'\b',text)]
        verify(label+': chamadas no texto',not absent,absent)
    all_text='\n'.join(p.text for p in doc.paragraphs)
    verify('Sem marcador de referências nem datas s. d.', '__REFERENCIAS' not in all_text and 's. d.' not in all_text)
    references=[p for p in doc.paragraphs if p.style.name=='Referencia']
    verify('Referências com título em destaque e ano identificável',all(any(r.bold for r in p.runs) and re.search(r'\b(?:19|20)\d{2}[a-z]?|\[20--\]',p.text) for p in references),len(references))
    body='\n'.join(p.text for p in doc.paragraphs if p.style.name!='Referencia')+'\n'+'\n'.join(c.text for t in doc.tables for row in t.rows for c in row.cells)
    corpus=re.findall(r'\(([^()]*)\)',body)
    calls=[]
    for group in corpus:
        for call in group.split('; '):
            author,sep,dates=call.partition(', ')
            if sep:calls.extend((author,d) for d in re.findall(r'\[20--\]|\b\d{4}[a-z]?',dates))
    forms={'ABNT14724':('Associação Brasileira de Normas Técnicas','2024'),'ABNT6023':('Associação Brasileira de Normas Técnicas','2025'),'ABNT6024':('Associação Brasileira de Normas Técnicas','2012a'),'ABNT6027':('Associação Brasileira de Normas Técnicas','2012b'),'ABNT6028':('Associação Brasileira de Normas Técnicas','2021'),'ABNT10520':('Associação Brasileira de Normas Técnicas','2023'),'AWS':('Amazon Web Services','2026'),'ASANA':('Asana','2026'),'JIRA':('Atlassian','2026a'),'TRELLO':('Atlassian','2026b'),'CLICKUP':('ClickUp','2026'),'DOCKER':('Docker','2026'),'LARAVEL':('Laravel','2026'),'LIBRE':('LibreTranslate','[20--]'),'PESQUISA':('Longo et al.','2026a'),'ORIGINAL':('Longo et al.','2026b'),'CODE':('Longo et al.','2026c'),'REACT':('Meta Platforms','[20--]'),'MONDAY':('monday.com','[20--]'),'NOTION':('Notion','2026'),'PANDAS':('Pandas Development Team','2026'),'POSTGRES':('PostgreSQL Global Development Group','2026'),'UFABC':('Universidade Federal do ABC','[20--]'),'UFES':('Universidade Federal do Espírito Santo','2026'),'VITE':('Vite','[20--]'),'WCAG':('World Wide Web Consortium','2024')}
    expected=[(key,forms[key]) for key,author,title,desc,url in generator.REFERENCES if any(author in p.text and title.split(':')[0] in p.text for p in references)]
    absent=[key for key,(author,date) in expected if (author,date) not in calls and author+' ('+date+')' not in body]
    verify('Todas as referências possuem chamada autor-data correspondente',not absent,absent)
    known=set(forms.values())
    unknown=sorted(set(c for c in calls if c not in known))
    verify('Todas as chamadas autor-data possuem referência correspondente',not unknown,unknown)
    contents_styles=[name for name in doc.styles if name.type==1 and name.name.startswith(('Contents','TOC ')) and name.name[-1:].isdigit()]
    for style in contents_styles:
        level=int(style.name[-1]);verify('Hierarquia tipográfica '+style.name,style.font.bold if level<3 else style.font.italic)
    for name in ['TOC 1','TOC 2','TOC 3']:
        if name in doc.styles:
            style=doc.styles[name]
            verify('Hierarquia tipográfica '+name,style.font.bold if name!='TOC 3' else style.font.italic)
    verify('Folha de aprovação ausente conforme solicitação','BANCA EXAMINADORA' not in all_text)
    verify('PDF sem páginas vazias',all(p.get_text().strip() for p in pdf))
    bounds=[]
    for i,p in enumerate(pdf):
        for b in p.get_text('blocks'):
            if b[0]<80 or b[2]>545 or b[3]>790:bounds.append(i+1)
    verify('Texto dentro da área de impressão',not bounds,bounds)
    count=len(pdf)
    pdf.close()
    return {'paginas':count, 'verificacoes':tests,'falhas':sum(not t['aprovado'] for t in tests)}


def main():
    results={str(b.relative_to(ROOT)):check(b) for b in BASES}
    output=ROOT/'fontes-documentais/conferencia-abnt-2026-11-10.json'
    output.write_text(json.dumps(results,ensure_ascii=False,indent=2))
    print(json.dumps({name:{'paginas':data['paginas'],'verificacoes':len(data['verificacoes']),'falhas':[t for t in data['verificacoes'] if not t['aprovado']]} for name,data in results.items()},ensure_ascii=False,indent=2))
    if any(d['falhas'] for d in results.values()):raise SystemExit(1)


if __name__=='__main__':
    main()
