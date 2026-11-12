"""Gera uma simulação documental, sem acessar o Spot, banco ou serviços externos."""

import csv
import json
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent
MEMBERS = [
    {"id": "alexander", "nome": "Alexander Molany Longo", "perfil_spot": "gestor-administrador", "papel_projeto": "manager", "frente_simulada": "Coordenação, implementação e evidências técnicas"},
    {"id": "lucas", "nome": "Lucas Rodrigues Soares", "perfil_spot": "analista", "papel_projeto": "analyst", "frente_simulada": "Pesquisa, requisitos e rastreabilidade"},
    {"id": "pedro", "nome": "Pedro Galvão de Matos", "perfil_spot": "analista", "papel_projeto": "analyst", "frente_simulada": "Verificação, operação e roteiro de demonstração"},
    {"id": "yasmin", "nome": "Yasmin Regina de Souza", "perfil_spot": "analista", "papel_projeto": "analyst", "frente_simulada": "Revisão documental, apresentação e entrega"},
]

# IDs e dependências são metadados deste roteiro; não são IDs do banco ou
# funcionalidades de dependência automática do produto.
# título, responsável, revisor documental, estimativa em minutos, prazo D+n,
# dependências do roteiro, evidência/critério de aceite
ROWS = [
    ("Inventariar e congelar fontes", "alexander", "lucas", 120, -13, [], "Inventário com origem, versão e distinção entre fontes atuais e históricas"),
    ("Configurar equipe e escopo", "alexander", "pedro", 90, -13, [1], "Quatro vínculos ativos; gerente elegível; projeto marcado SIMULAÇÃO"),
    ("Reconciliar regras com código atual", "alexander", "pedro", 240, -10, [2], "Gerente obrigatório, chat e tarefas do projeto descritos sem regras antigas"),
    ("Consolidar evidências técnicas", "alexander", "pedro", 180, -8, [3], "Separação entre relatórios de 10, 11 e 12/11; nenhuma soma entre execuções"),
    ("Revisar limites de acesso", "alexander", "lucas", 150, -6, [3], "Documentos privados; analista vê tarefas autorizadas; acesso financeiro separado"),
    ("Conferir versão de demonstração", "alexander", "pedro", 180, -4, [4, 5, 17], "Código, roteiro e arquivos identificados; serviços externos condicionais"),
    ("Extrair dados da pesquisa", "lucas", "yasmin", 180, -12, [1], "Base declarada 94 e período preservados; nenhuma resposta individual reconstruída"),
    ("Conferir totais e percentuais", "lucas", "yasmin", 210, -10, [7], "Totais variáveis e 57 comentários versus 49 frequências explicitados"),
    ("Relacionar pesquisa e requisitos", "lucas", "alexander", 180, -8, [8], "Dor, recurso e limite associados em matriz rastreável"),
    ("Revisar objetivos e metodologia", "lucas", "yasmin", 180, -6, [9], "Objetivo implementado separado de benefício não medido e simulação"),
    ("Conferir referências e chamadas", "lucas", "yasmin", 210, -4, [10], "Referências consistentes com fontes locais; pendências marcadas para conferência"),
    ("Preparar respostas sobre pesquisa", "lucas", "pedro", 120, -2, [11], "Respostas sobre conveniência, discrepâncias e ausência de avaliação pós-uso"),
    ("Mapear fluxos demonstráveis", "pedro", "alexander", 180, -12, [2], "Projeto, tarefa, quadro, agenda e consulta documental com permissões"),
    ("Organizar matriz de verificações", "pedro", "alexander", 180, -10, [13], "Resultado histórico, teste a repetir e revisão humana separados"),
    ("Preparar percurso de teclado e celular", "pedro", "yasmin", 180, -8, [14], "Roteiro de foco, Escape, retorno, 320 px e leitura 120%; sem certificar WCAG"),
    ("Preparar checklist de implantação", "pedro", "alexander", 150, -6, [14], "HTTPS, backup, worker, scheduler, SMTP/OAuth como condições de operação"),
    ("Preparar demonstração sem provedores", "pedro", "lucas", 180, -5, [15, 16], "Dados sintéticos, agenda interna e alternativa para falha de integração"),
    ("Ensaiar demonstração e recuperação", "pedro", "alexander", 150, -2, [6, 17], "Percurso hipotético com falha e retomada; tempo identificado como simulado"),
    ("Comparar monografia e guias", "yasmin", "lucas", 180, -12, [1], "Edição final priorizada; versões duplicadas e antigas identificadas"),
    ("Revisar estrutura e normalização", "yasmin", "lucas", 240, -9, [19, 8], "Sumário, listas, fontes, referências e pendências institucionais anotados"),
    ("Planejar atualização dos 12 slides", "yasmin", "alexander", 240, -6, [20, 4], "Plano de atualização baseado em 12 slides, não no roteiro antigo de 15"),
    ("Planejar falas dos quatro autores", "yasmin", "pedro", 150, -4, [21, 10], "Divisão de falas com reserva para troca e falhas; duração alvo, não medida"),
    ("Conferir pacote documental simulado", "yasmin", "lucas", 180, -2, [11, 22], "Checklist de nomes, versões e leitura; não equivale a aprovação institucional"),
    ("Registrar retrospectiva simulada", "yasmin", "alexander", 90, -1, [12, 18, 23], "Achados do cenário separados de depoimentos ou ganhos reais"),
    ("Confirmar folha de aprovação", "yasmin", "alexander", 60, -1, [20], "Decisão institucional registrada; sem nomes, datas ou assinaturas inventados"),
    ("Confirmar catalogação e modelo do colégio", "lucas", "yasmin", 60, -1, [20], "Orientação institucional real disponível antes do depósito"),
    ("Revisar prontidão da entrega", "alexander", "yasmin", 90, -1, [12, 18, 23, 24], "Conferência do conjunto e bloqueios explícitos das tarefas 25 e 26"),
    ("Autorizar entrega e finalizar projeto", "alexander", "pedro", 30, 0, [25, 26, 27], "Pendências resolvidas e equipe conferida antes da finalização"),
]


def generate():
    tasks, events = [], []
    for n, (title, owner, reviewer, estimate, due, deps, acceptance) in enumerate(ROWS, 1):
        task_id = f"T{n:02}"
        dependency_ids = [f"T{x:02}" for x in deps]
        # A sequência de criação precede os eventos do dia; ordenação por fase.
        events.append({"dia_relativo": -14, "ordem": 0, "tarefa": task_id, "autor": "alexander", "acao": "criada", "de": None, "para": "todo"})
        status = "done" if n <= 24 else ("review" if n == 27 else "todo")
        actual = estimate + ((n % 3) - 1) * 15 if n <= 24 else (45 if n == 27 else 0)
        # Dependências concluídas até o dia de início. São conferidas abaixo.
        earliest = max([ROWS[x - 1][4] for x in deps] or [-14])
        start = max(-13, due - 2, earliest) if n <= 24 else -1
        task = {
            "id": task_id, "titulo": title, "responsavel": owner,
            "revisor_documental": reviewer,
            "participantes": [reviewer],
            "prioridade": "high" if n in {1, 2, 3, 4, 8, 20, 21, 25, 26, 27, 28} else "medium",
            "prazo_relativo": due, "estimativa_minutos": estimate,
            "duracao_simulada_minutos": actual, "status_final": status,
            "dia_inicio_simulado": start if status != "todo" else None,
            "dia_conclusao_simulada": due if status == "done" else None,
            "dependencias_do_roteiro": dependency_ids, "criterio_de_aceite": acceptance,
            "natureza": "SIMULAÇÃO — não é registro real de uso",
        }
        tasks.append(task)
        if status != "todo":
            for order, day, before, after in [(1, start, "todo", "in_progress"), (2, due, "in_progress", "review")]:
                events.append({"dia_relativo": day, "ordem": order, "tarefa": task_id, "autor": owner, "acao": "status", "de": before, "para": after})
            if status == "done":
                events.append({"dia_relativo": due, "ordem": 3, "tarefa": task_id, "autor": owner, "acao": "status", "de": "review", "para": "done"})
    task_by_id = {t["id"]: t for t in tasks}

    def dependency_rank(task_id, visiting=None):
        visiting = set() if visiting is None else visiting
        if task_id in visiting:
            raise ValueError("Dependência circular no roteiro")
        deps = task_by_id[task_id]["dependencias_do_roteiro"]
        return 1 + max((dependency_rank(dep, visiting | {task_id}) for dep in deps), default=-1)

    events.sort(key=lambda e: (e["dia_relativo"], dependency_rank(e["tarefa"]), e["ordem"], e["tarefa"]))
    known = {m["id"] for m in MEMBERS}
    states = {}
    valid = {"todo", "in_progress", "review", "done"}
    for event in events:
        if event["autor"] not in known or event["para"] not in valid:
            raise ValueError("Autor ou status inválido")
        if states.get(event["tarefa"]) != event["de"]:
            raise ValueError(f"Transição inconsistente: {event}")
        if event["para"] == "in_progress":
            if any(states.get(dep) != "done" for dep in task_by_id[event["tarefa"]]["dependencias_do_roteiro"]):
                raise ValueError(f"Início antes da conclusão das dependências: {event}")
        states[event["tarefa"]] = event["para"]
    for task in tasks:
        if states[task["id"]] != task["status_final"]:
            raise ValueError("Resultado final divergente")
        if task["status_final"] == "done":
            for dep in task["dependencias_do_roteiro"]:
                earlier = next(t for t in tasks if t["id"] == dep)
                if earlier["status_final"] != "done" or earlier["dia_conclusao_simulada"] > task["dia_inicio_simulado"]:
                    raise ValueError(f"Dependência inconsistente: {task['id']} / {dep}")
    summary = {
        "natureza": "SIMULAÇÃO DOCUMENTAL OFFLINE; não exercita API, UI ou banco do Spot",
        "data_elaboracao": "2026-11-12", "calendario": "D = entrega hipotética, sem data real atribuída",
        "membros": len(MEMBERS), "tarefas": len(tasks), "eventos_sinteticos": len(events),
        "por_status": dict(Counter(t["status_final"] for t in tasks)),
        "estimativa_total_horas": sum(t["estimativa_minutos"] for t in tasks) / 60,
        "duracao_simulada_total_horas": sum(t["duracao_simulada_minutos"] for t in tasks) / 60,
        "por_membro": [{"id": m["id"], "tarefas": sum(t["responsavel"] == m["id"] for t in tasks),
                        "estimativa_horas": sum(t["estimativa_minutos"] for t in tasks if t["responsavel"] == m["id"]) / 60,
                        "duracao_simulada_horas": sum(t["duracao_simulada_minutos"] for t in tasks if t["responsavel"] == m["id"]) / 60} for m in MEMBERS],
        "bloqueios": ["T25: decisão sobre folha de aprovação", "T26: catalogação e modelo institucional", "T28: aguarda T25, T26 e aceite de T27"],
        "projeto_finalizado": False,
        "checagens": ["IDs e autores válidos", "status nativos", "reprodução das transições", "estado final consistente", "dependências das tarefas concluídas verificadas"],
    }
    dataset = {"natureza": summary["natureza"], "perfis_e_responsabilidades": "proposta para o cenário; não representa histórico pessoal",
               "projeto": {"nome": "SIMULAÇÃO — Reta final do TCC SPOT", "empresa": "Ambiente acadêmico de demonstração", "gerente": "alexander", "finalizado": False},
               "membros": MEMBERS, "tarefas": tasks, "eventos": events}
    for filename, data in [("cenario.json", dataset), ("resultado-simulacao.json", summary)]:
        (ROOT / filename).write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n")
    fields = ["id", "titulo", "responsavel", "revisor_documental", "prioridade", "prazo_relativo", "estimativa_minutos", "duracao_simulada_minutos", "status_final", "dependencias_do_roteiro", "criterio_de_aceite", "natureza"]
    with (ROOT / "tarefas-simuladas.csv").open("w", newline="", encoding="utf-8-sig") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields, extrasaction="ignore", delimiter=";")
        writer.writeheader()
        for task in tasks:
            writer.writerow({**task, "dependencias_do_roteiro": ", ".join(task["dependencias_do_roteiro"])})
    lines = ["# Quadro de tarefas simulado", "", "Dados sintéticos. D = entrega hipotética. Dependências, estimativas e revisor são metadados do roteiro; não afirmam automação nativa. O CSV é uma planilha de apoio, sem importador automático implementado.", "", "| ID | Tarefa | Responsável | Revisor | Prazo | Estimativa | Estado final |", "| --- | --- | --- | --- | --- | --- | --- |"]
    labels = {"done": "Concluído", "review": "Em revisão", "todo": "A fazer"}
    for task in tasks:
        lines.append(f"| {task['id']} | {task['titulo']} | {task['responsavel'].title()} | {task['revisor_documental'].title()} | D{task['prazo_relativo']:+} | {task['estimativa_minutos']} min | {labels[task['status_final']]} |")
    lines += ["", f"Resultado calculado: {len(tasks)} tarefas; 24 concluídas, uma em revisão e três a fazer. Estimativa total: {summary['estimativa_total_horas']:.2f} h; duração sintética: {summary['duracao_simulada_total_horas']:.2f} h. Esses valores foram criados para o exercício e não medem produtividade.", "", "T25 e T26 permanecem pendentes de confirmação institucional; T27 fica em revisão e T28 aguarda liberação. O projeto continua aberto."]
    (ROOT / "QUADRO-SIMULADO.md").write_text("\n".join(lines) + "\n")
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    generate()
