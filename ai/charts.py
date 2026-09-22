"""
Matplotlib chart PNG renderer for ZPGC admin Dashboard.
Called via POST /charts/png  JSON { "type": "report|categories|severity|satisfaction", "payload": {...} }
"""

from __future__ import annotations

import io
from typing import Any

# Non-interactive backend before pyplot import
import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402


MAROON = "#610107"
TEAL = "#5BC8E8"
SEV_COLORS = ["#FF3B30", "#FF8D28", "#34C759"]
SAT_COLORS = ["#7ED9A8", "#2E8B8B", "#5BC8E8", "#F5A623", "#D9435E"]


def _fig_to_png_bytes(fig) -> bytes:
    buf = io.BytesIO()
    fig.savefig(buf, format="png", dpi=120, bbox_inches="tight", facecolor="white")
    plt.close(fig)
    buf.seek(0)
    return buf.read()


def render_report(payload: dict[str, Any]) -> bytes:
    labels = payload.get("labels") or ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
    submitted = payload.get("submitted") or [0] * len(labels)
    resolved = payload.get("resolved") or [0] * len(labels)

    fig, ax = plt.subplots(figsize=(5.2, 3.0))
    ax.plot(labels, submitted, color=MAROON, marker="o", linewidth=2, label="Submitted")
    ax.plot(labels, resolved, color=TEAL, marker="o", linewidth=2, label="Resolved")
    ax.set_ylabel("Tickets")
    ax.set_ylim(bottom=0)
    ax.legend(loc="upper left", fontsize=8)
    ax.grid(True, axis="y", alpha=0.25)
    return _fig_to_png_bytes(fig)


def render_categories(payload: dict[str, Any]) -> bytes:
    labels = payload.get("labels") or []
    data = payload.get("data") or []
    fig, ax = plt.subplots(figsize=(5.2, 3.0))
    ax.bar(labels, data, color=MAROON, width=0.65)
    ax.set_ylabel("Tickets")
    ax.set_ylim(bottom=0)
    ymax = max(data) if data else 0
    ax.set_ylim(top=max(1, ymax) * 1.25)
    for i, v in enumerate(data):
        ax.text(i, v + 0.05, str(int(v)), ha="center", va="bottom", fontsize=8)
    return _fig_to_png_bytes(fig)


def render_severity(payload: dict[str, Any]) -> bytes:
    labels = payload.get("labels") or ["Critical", "Moderate", "Low"]
    data = payload.get("data") or [0, 0, 0]
    total = sum(data)
    fig, ax = plt.subplots(figsize=(4.2, 3.0))
    if total <= 0:
        ax.text(0.5, 0.5, "No prioritized tickets", ha="center", va="center")
        ax.axis("off")
    else:
        wedges, texts, autotexts = ax.pie(
            data,
            labels=labels,
            colors=SEV_COLORS,
            autopct=lambda p: f"{p:.0f}%" if p > 0 else "",
            startangle=90,
            wedgeprops=dict(width=0.45),
        )
        for t in texts:
            t.set_fontsize(8)
        for t in autotexts:
            t.set_fontsize(8)
    return _fig_to_png_bytes(fig)


def render_satisfaction(payload: dict[str, Any]) -> bytes:
    labels = payload.get("labels") or []
    data = payload.get("data") or []
    short = ["Very sat.", "Satisfied", "Not sure", "Not sat.", "Hate it"]
    if len(labels) == len(short):
        labels = short
    fig, ax = plt.subplots(figsize=(5.2, 3.0))
    ax.bar(labels, data, color=SAT_COLORS[: len(data)], width=0.65)
    ax.set_ylabel("Score")
    ax.set_ylim(bottom=0)
    ymax = max(data) if data else 0
    ax.set_ylim(top=max(1, ymax) * 1.3)
    ax.tick_params(axis="x", labelsize=7)
    return _fig_to_png_bytes(fig)


RENDERERS = {
    "report": render_report,
    "categories": render_categories,
    "severity": render_severity,
    "satisfaction": render_satisfaction,
}


def render_chart(chart_type: str, payload: dict[str, Any]) -> bytes | None:
    fn = RENDERERS.get(chart_type)
    if not fn:
        return None
    return fn(payload or {})
