"""
Paper §4.4.4: recompute Accuracy / Precision / Recall / F1 with Scikit-learn
from a ZPGC benchmark JSON (uses category_confusion / priority_confusion).

Usage:
  pip install scikit-learn
  python ai/eval_paper_sklearn.py ai/data/benchmark_luna_report.json
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

try:
    from sklearn.metrics import (
        accuracy_score,
        confusion_matrix,
        precision_recall_fscore_support,
    )
except ImportError as e:
    raise SystemExit("Install scikit-learn: pip install scikit-learn") from e

CATS = ["hardware", "software", "network", "account", "other"]
PRIS = ["critical", "moderate", "low"]


def expand_confusion(conf: dict, labels: list[str]) -> tuple[list[str], list[str]]:
    y_true: list[str] = []
    y_pred: list[str] = []
    for g in labels:
        row = conf.get(g, {})
        for p in labels:
            n = int(row.get(p, 0))
            y_true.extend([g] * n)
            y_pred.extend([p] * n)
    return y_true, y_pred


def evaluate(conf: dict, labels: list[str]) -> dict:
    y_true, y_pred = expand_confusion(conf, labels)
    if not y_true:
        return {"error": "empty confusion"}
    acc = accuracy_score(y_true, y_pred)
    p_w, r_w, f_w, _ = precision_recall_fscore_support(
        y_true, y_pred, labels=labels, average="weighted", zero_division=0
    )
    p_m, r_m, f_m, _ = precision_recall_fscore_support(
        y_true, y_pred, labels=labels, average="macro", zero_division=0
    )
    cm = confusion_matrix(y_true, y_pred, labels=labels)
    return {
        "n": len(y_true),
        "accuracy": round(float(acc), 4),
        "weighted": {
            "precision": round(float(p_w), 4),
            "recall": round(float(r_w), 4),
            "f1": round(float(f_w), 4),
        },
        "macro": {
            "precision": round(float(p_m), 4),
            "recall": round(float(r_m), 4),
            "f1": round(float(f_m), 4),
        },
        "table3": {
            "accuracy_pct": f"{acc * 100:.1f}%",
            "precision_pct": f"{p_w * 100:.1f}%",
            "recall_pct": f"{r_w * 100:.1f}%",
            "f1_pct": f"{f_w * 100:.1f}%",
        },
        "sklearn_confusion_matrix": cm.tolist(),
        "labels": labels,
    }


def main() -> None:
    path = Path(sys.argv[1] if len(sys.argv) > 1 else "ai/data/benchmark_luna_report.json")
    data = json.loads(path.read_text(encoding="utf-8"))
    out = {
        "source": str(path).replace("\\", "/"),
        "model": data.get("model"),
        "category": evaluate(data.get("category_confusion") or {}, CATS),
        "priority": evaluate(data.get("priority_confusion") or {}, PRIS),
    }
    print(json.dumps(out, indent=2))


if __name__ == "__main__":
    main()
