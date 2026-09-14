#!/usr/bin/env python3
"""Consolida o levantamento do portfólio do Spot com Pandas.

Recebe uma lista JSON pela entrada padrão e devolve os indicadores usados pela
tela de gráficos. O script não acessa o banco: autenticação e escopo permanecem
sob responsabilidade da API Laravel.
"""

import json
import sys

import pandas as pd


STATUS_LABELS = {
    "planning": "Planejamento",
    "in_progress": "Em andamento",
    "review": "Em revisão",
    "completed": "Concluídos",
    "done": "Concluídos",
    "stopped": "Parados",
    "frozen": "Congelados",
}
MONTH_LABELS = ["jan", "fev", "mar", "abr", "mai", "jun", "jul", "ago", "set", "out", "nov", "dez"]


def records(series: pd.Series, label_name: str) -> list[dict]:
    return [{label_name: str(label), "value": int(value)} for label, value in series.items()]


def main() -> None:
    payload = json.load(sys.stdin)
    frame = pd.DataFrame(payload.get("projects", []))
    if frame.empty:
        print(json.dumps({"summary": {"total": 0, "in_progress": 0, "completed": 0, "on_time": 0, "overdue": 0, "frozen": 0}, "status": [], "clients": [], "managers": [], "monthly": [], "projects": []}))
        return

    for column in ["contract_value", "actual_revenue", "actual_cost", "profit", "worked_hours", "estimated_hours"]:
        frame[column] = pd.to_numeric(frame[column], errors="coerce").fillna(0.0)
    for column in ["due_date", "start_date", "end_date"]:
        frame[column] = pd.to_datetime(frame[column], errors="coerce")

    today = pd.Timestamp(payload.get("today")).normalize()
    completed = frame["status"].isin(["completed", "done"]) | frame["finalized"]
    frame.loc[frame["finalized"], "status"] = "completed"
    frozen = frame["status"].isin(["stopped", "frozen"])
    overdue = (~completed) & frame["due_date"].notna() & (frame["due_date"] < today)
    on_time = (~overdue) & (~frozen)

    statuses = frame["status"].map(STATUS_LABELS).fillna(frame["status"].str.replace("_", " ").str.title())
    by_status = statuses.value_counts(sort=False).sort_values(ascending=False)
    by_client = frame["client"].fillna("Sem cliente").replace("", "Sem cliente").value_counts().head(10)
    by_manager = frame["manager"].fillna("Não atribuído").replace("", "Não atribuído").value_counts().head(10)

    starts = frame["start_date"].fillna(pd.to_datetime(frame["created_at"], errors="coerce"))
    monthly = starts.dropna().dt.to_period("M").value_counts().sort_index()

    project_rows = []
    for row in frame.sort_values(["contract_value", "name"], ascending=[False, True]).to_dict("records"):
        project_rows.append({
            "id": int(row["id"]), "name": row["name"], "client": row.get("client") or "Sem cliente",
            "manager": row.get("manager") or "Não atribuído", "status": STATUS_LABELS.get(row["status"], row["status"]),
            "progress": int(row.get("progress") or 0), "contract_value": round(row["contract_value"], 2),
            "actual_revenue": round(row["actual_revenue"], 2), "actual_cost": round(row["actual_cost"], 2),
            "profit": round(row["profit"], 2), "worked_hours": round(row["worked_hours"], 1),
            "estimated_hours": round(row["estimated_hours"], 1),
        })

    result = {
        "summary": {
            "total": int(len(frame)), "in_progress": int(((frame["status"] == "in_progress") & ~completed).sum()),
            "completed": int(completed.sum()), "on_time": int(on_time.sum()),
            "overdue": int(overdue.sum()), "frozen": int(frozen.sum()),
            "clients": int(frame["client"].replace("", pd.NA).nunique()),
            "managers": int(frame["manager"].replace("", pd.NA).nunique()),
            "contract_value": round(float(frame["contract_value"].sum()), 2),
            "revenue": round(float(frame["actual_revenue"].sum()), 2),
            "cost": round(float(frame["actual_cost"].sum()), 2),
            "profit": round(float(frame["profit"].sum()), 2),
        },
        "status": records(by_status, "label"),
        "clients": records(by_client, "label"),
        "managers": records(by_manager, "label"),
        "monthly": [{"label": f"{MONTH_LABELS[period.month - 1]}/{str(period.year)[2:]}", "value": int(value)} for period, value in monthly.items()],
        "projects": project_rows,
    }
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
