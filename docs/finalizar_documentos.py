#!/usr/bin/env python3
"""Restaura links externos nos PDFs e confere os artefatos finais."""
from pathlib import Path
import json
import shutil
from bs4 import BeautifulSoup
import pymupdf

ROOT=Path(__file__).resolve().parent
BASES=[ROOT/'Monografia_SPOT_documentacao_integrada_final', ROOT/'documentacao-tecnica-spot-abnt', ROOT/'tcc/GUIA-COMPLETO-TCC-SPOT']


def main():
    report={}
    for base in BASES:
        soup=BeautifulSoup(base.with_suffix('.html').read_text(), 'html.parser')
        external={(a.get_text(' ',strip=True),a['href']) for a in soup.select('a[href]') if a['href'].startswith('https://')}
        pdf=base.with_suffix('.pdf')
        document=pymupdf.open(pdf)
        added=0
        for page in document:
            old=[(l.get('uri'),l['from']) for l in page.get_links() if l.get('uri')]
            for label,url in external:
                for rect in page.search_for(label):
                    if not any(u==url and r.intersects(rect) for u,r in old):
                        page.insert_link({'kind':pymupdf.LINK_URI,'from':rect,'uri':url})
                        added+=1
        if added:
            temp=pdf.with_suffix('.tmp.pdf')
            document.save(temp,garbage=4,deflate=True)
            document.close()
            temp.replace(pdf)
            document=pymupdf.open(pdf)
        outside=[]
        for i,page in enumerate(document):
            for b in page.get_text('blocks'):
                if b[0]<80 or b[2]>545 or b[3]>790:
                    outside.append({'page':i+1,'bbox':list(b[:4])})
        text='\n'.join(p.get_text() for p in document)
        github=[l['uri'] for p in document for l in p.get_links() if 'github.com/iamnotlittleprince/SPOT' in l.get('uri','')]
        missing_images=[img['src'] for img in soup.select('img[src]') if not (base.parent/img['src']).exists()]
        data={'paginas':len(document),'marcadores':len(document.get_toc()),'paginas_vazias':[i+1 for i,p in enumerate(document) if not p.get_text().strip()], 'blocos_fora_margens':outside, 'links_github':sorted(set(github)), 'imagens_html_ausentes':missing_images, 'numeros_testes_presentes':'256' in text and '3.487' in text, 'aprovador_direcionado_presente':'assigned_reviewer_id' in text}
        assert not outside and not data['paginas_vazias'] and not missing_images, data
        assert github and data['numeros_testes_presentes'] and data['aprovador_direcionado_presente'], data
        report[str(base.relative_to(ROOT))]=data
        if base.parent==ROOT:
            base.with_suffix('.txt').write_text(text)
        document.close()
    for ext in ['.docx','.odt','.pdf','.html','.md','.txt']:
        shutil.copy2(ROOT/('tcc/GUIA-COMPLETO-TCC-SPOT'+ext),ROOT/('tcc/GUIA-DEFESA-TCC-SPOT'+ext))
    output=ROOT/'fontes-documentais/validacao-documentos-finais.json'
    output.write_text(json.dumps(report,ensure_ascii=False,indent=2))
    print(json.dumps(report,ensure_ascii=False,indent=2))


if __name__=='__main__':
    main()
