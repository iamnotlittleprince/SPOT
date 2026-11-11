#!/usr/bin/env python3
"""Atualiza sumários e exporta DOCX para ODT/PDF usando LibreOffice isolado."""
import argparse
from pathlib import Path
import socket
import shutil
import subprocess
import tempfile
import time
import uno
from com.sun.star.beans import PropertyValue


def props(**values):
    result = []
    for name, value in values.items():
        p = PropertyValue()
        p.Name, p.Value = name, value
        result.append(p)
    return tuple(result)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('files', nargs='+', type=Path)
    args = parser.parse_args()
    with tempfile.TemporaryDirectory(prefix='spot-libreoffice-') as profile:
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        process = subprocess.Popen([
            'libreoffice', '-env:UserInstallation=' + Path(profile).as_uri(),
            '--headless', '--norestore', '--nodefault', '--nofirststartwizard',
            f'--accept=socket,host=localhost,port={port};urp;StarOffice.ServiceManager',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
        try:
            context = uno.getComponentContext()
            resolver = context.ServiceManager.createInstanceWithContext('com.sun.star.bridge.UnoUrlResolver', context)
            deadline = time.monotonic() + 30
            while True:
                try:
                    remote = resolver.resolve(f'uno:socket,host=localhost,port={port};urp;StarOffice.ComponentContext')
                    break
                except Exception:
                    if time.monotonic() >= deadline:
                        raise RuntimeError('Não foi possível conectar ao LibreOffice isolado.')
                    time.sleep(0.2)
            desktop = remote.ServiceManager.createInstanceWithContext('com.sun.star.frame.Desktop', remote)
            for file in args.files:
                path = file.resolve()
                working = Path(profile)/'documents'
                working.mkdir(exist_ok=True)
                temporary = working/path.name
                shutil.copy2(path, temporary)
                document = desktop.loadComponentFromURL(temporary.as_uri(), '_blank', 0, props(Hidden=True, MacroExecutionMode=4, UpdateDocMode=3))
                if document is None:
                    raise RuntimeError(f'Falha ao abrir {path}')
                try:
                    if document.getDocumentIndexes().getCount() == 0:
                        search = document.createSearchDescriptor()
                        search.SearchString = 'SUMÁRIO'
                        found = document.findFirst(search)
                        if found is not None:
                            cursor = document.Text.createTextCursorByRange(found.Start)
                            found.String = ''
                            cursor.CharHeight = 1.0
                            cursor.ParaTopMargin = 0
                            cursor.ParaBottomMargin = 0
                            spacing = uno.createUnoStruct('com.sun.star.style.LineSpacing')
                            spacing.Mode = 3
                            spacing.Height = 35
                            cursor.ParaLineSpacing = spacing
                            cursor.ParaKeepTogether = False
                            index = document.createInstance('com.sun.star.text.ContentIndex')
                            index.CreateFromOutline = True
                            index.Level = 3
                            index.Title = 'SUMÁRIO'
                            document.Text.insertTextContent(cursor, index, False)
                            styles = document.StyleFamilies.getByName('ParagraphStyles')
                            if styles.hasByName('Contents Heading'):
                                heading = styles.getByName('Contents Heading')
                                heading.BreakType = uno.Enum('com.sun.star.style.BreakType', 'PAGE_BEFORE')
                                heading.ParaAdjust = 3
                                heading.CharHeight = 12.0
                    for title, style in [('LISTA DE FIGURAS', 'LegendaFigura'), ('LISTA DE QUADROS', 'LegendaQuadro'), ('LISTA DE TABELAS', 'LegendaTabela')]:
                        indexes = document.getDocumentIndexes()
                        if any(indexes.getByIndex(i).Title == title for i in range(indexes.getCount())):
                            continue
                        search = document.createSearchDescriptor()
                        search.SearchString = title
                        found = document.findFirst(search)
                        if found is not None:
                            cursor = document.Text.createTextCursorByRange(found.Start)
                            found.String = ''
                            cursor.CharHeight = 1.0
                            cursor.ParaTopMargin = 0
                            cursor.ParaBottomMargin = 0
                            spacing = uno.createUnoStruct('com.sun.star.style.LineSpacing')
                            spacing.Mode = 3
                            spacing.Height = 35
                            cursor.ParaLineSpacing = spacing
                            cursor.ParaKeepTogether = False
                            index = document.createInstance('com.sun.star.text.ContentIndex')
                            index.CreateFromOutline = False
                            index.CreateFromMarks = False
                            index.CreateFromLevelParagraphStyles = True
                            index.Level = 1
                            uno.invoke(index.LevelParagraphStyles, 'replaceByIndex', (0, uno.Any('[]string', (style,))))
                            index.Title = title
                            document.Text.insertTextContent(cursor, index, False)
                    styles = document.StyleFamilies.getByName('ParagraphStyles')
                    for level in range(1,4):
                        name = 'Contents '+str(level)
                        if styles.hasByName(name):
                            style = styles.getByName(name)
                            style.CharFontName = 'Arial'
                            style.CharHeight = 12.0
                            style.CharWeight = 150.0 if level<3 else 100.0
                            style.CharPosture = uno.Enum('com.sun.star.awt.FontSlant', 'ITALIC' if level==3 else 'NONE')
                            style.ParaLeftMargin = 0
                            style.ParaFirstLineIndent = 0
                    for _ in range(4):
                        document.refresh()
                        document.getTextFields().refresh()
                        indexes = document.getDocumentIndexes()
                        for index in range(indexes.getCount()):
                            indexes.getByIndex(index).update()
                        document.getCurrentController().getPageCount() if hasattr(document.getCurrentController(), 'getPageCount') else None
                    document.store()
                    # Uma nova leitura estabiliza a quebra após índices longos no DOCX.
                    document.close(True)
                    document = desktop.loadComponentFromURL(temporary.as_uri(), '_blank', 0, props(Hidden=True, MacroExecutionMode=4, UpdateDocMode=3))
                    if document is None:
                        raise RuntimeError(f'Falha ao reabrir {temporary.name}')
                    for _ in range(3):
                        document.refresh()
                        document.getTextFields().refresh()
                        indexes = document.getDocumentIndexes()
                        for i in range(indexes.getCount()):
                            indexes.getByIndex(i).update()
                    document.store()
                    document.storeToURL(path.with_suffix('.odt').as_uri(), props(FilterName='writer8', Overwrite=True))
                    document.storeToURL(path.with_suffix('.pdf').as_uri(), props(FilterName='writer_pdf_Export', Overwrite=True, FilterData=props(ExportBookmarks=True, ExportDocumentStructure=True, UseTaggedPDF=True)))
                    print(path.name + ': DOCX atualizado; ODT e PDF exportados.', flush=True)
                finally:
                    document.close(True)
                shutil.copy2(temporary, path)
            desktop.terminate()
        finally:
            process.terminate()
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()


if __name__ == '__main__':
    main()
