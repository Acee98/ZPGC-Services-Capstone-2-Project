"""
ZPGC AI ticket classifier — OpenAI gpt-5.6-luna + keyword fallback.

Priorities match MySQL ENUM: critical | moderate | low
(OpenAI may say medium/high — we map those here.)

Run:
  cd C:\\xampp\\htdocs\\CP2_V1.5\\ai
  python -m pip install -r requirements.txt
  copy .env.example .env
  python classifier_app.py
"""

from __future__ import annotations

import json
import os
import re
from typing import Any

from dotenv import load_dotenv
from flask import Flask, jsonify, request
from flask_cors import CORS

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

app = Flask(__name__)
CORS(app)

OPENAI_API_KEY = (os.getenv("OPENAI_API_KEY") or "").strip()
OPENAI_MODEL = (os.getenv("OPENAI_MODEL") or "gpt-5.6-luna").strip()
OPENAI_REASONING_EFFORT = (os.getenv("OPENAI_REASONING_EFFORT") or "none").strip()

CATEGORIES = ("hardware", "software", "network", "account", "other")
# Match tickets.priority ENUM in MySQL
PRIORITIES = ("critical", "moderate", "low")

KEYWORD_RULES: list[tuple[str, str, str]] = [
    (r"\b(password|login|otp|2fa|account|username|lock(ed)?|reset)\b", "account", "critical"),
    (r"\b(wifi|wi-?fi|internet|network|lan|vpn|router|dns|offline|disconnect)\b", "network", "critical"),
    (r"\b(blue\s*screen|bsod|overheat|fan|battery|charger|keyboard|mouse|monitor|printer|hardware|ram|ssd|hdd)\b", "hardware", "moderate"),
    (r"\b(install|update|crash|freeze|slow|software|app|excel|word|chrome|outlook|license|activation)\b", "software", "moderate"),
    (r"\b(urgent|critical|down|outage|cannot\s+work|emergency)\b", "other", "critical"),
]


def _normalize_category(value: Any) -> str:
    text = str(value or "").strip().lower()
    return text if text in CATEGORIES else "other"


def _normalize_priority(value: Any) -> str:
    """Map model/keyword words onto DB ENUM values."""
    text = str(value or "").strip().lower()
    if text in ("high", "urgent", "severe"):
        return "critical"
    if text in ("medium", "med", "normal"):
        return "moderate"
    if text in PRIORITIES:
        return text
    return "moderate"


def _text_fields(payload: dict[str, Any]) -> tuple[str, str]:
    title = str(payload.get("title") or payload.get("subject") or "").strip()
    description = str(payload.get("description") or "").strip()
    return title, description


def _keyword_classify(title: str, description: str) -> dict[str, Any]:
    blob = f"{title} {description}".lower()
    category = "other"
    priority = "moderate"
    matched: list[str] = []

    for pattern, cat, pri in KEYWORD_RULES:
        if re.search(pattern, blob, flags=re.IGNORECASE):
            category = cat
            priority = pri
            matched.append(pattern)
            break

    if re.search(r"\b(urgent|critical|emergency|cannot\s+work|outage)\b", blob, flags=re.IGNORECASE):
        priority = "critical"
    elif re.search(r"\b(asap|important|blocking)\b", blob, flags=re.IGNORECASE) and priority == "moderate":
        priority = "critical"
    elif re.search(r"\b(minor|small|question|how\s+to|curious)\b", blob, flags=re.IGNORECASE):
        priority = "low"

    confidence = 0.72 if matched else 0.45
    return {
        "category": category,
        "priority": priority,
        "confidence": confidence,
        "method": "keyword",
        "model": None,
        "matched_rules": matched[:3],
    }


def _openai_available() -> bool:
    key = OPENAI_API_KEY
    return bool(key) and not key.startswith("sk-your-key") and len(key) > 20


def _openai_client():
    from openai import OpenAI

    return OpenAI(api_key=OPENAI_API_KEY)


def _strip_json_fence(content: str) -> str:
    text = content.strip()
    if text.startswith("```"):
        text = re.sub(r"^```(?:json)?\s*", "", text)
        text = re.sub(r"\s*```$", "", text)
    return text.strip()


def _openai_classify(title: str, description: str) -> dict[str, Any] | None:
    if not _openai_available():
        return None

    system = (
        "You classify IT helpdesk tickets for a school campus (ZPGC). "
        "Reply with ONLY valid JSON: "
        '{"category":"hardware|software|network|account|other",'
        '"priority":"critical|moderate|low","confidence":0.0-1.0,'
        '"rationale":"short reason"}'
    )
    user = f"Title: {title}\nDescription: {description}"

    try:
        client = _openai_client()
        kwargs: dict[str, Any] = {
            "model": OPENAI_MODEL,
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user},
            ],
            "temperature": 0.2,
            "max_completion_tokens": 200,
        }
        if OPENAI_REASONING_EFFORT and OPENAI_REASONING_EFFORT.lower() not in ("", "default"):
            kwargs["reasoning_effort"] = OPENAI_REASONING_EFFORT

        resp = client.chat.completions.create(**kwargs)
        content = _strip_json_fence(resp.choices[0].message.content or "")
        data = json.loads(content)
        return {
            "category": _normalize_category(data.get("category")),
            "priority": _normalize_priority(data.get("priority")),
            "confidence": float(data.get("confidence") or 0.8),
            "method": "openai",
            "model": OPENAI_MODEL,
            "rationale": str(data.get("rationale") or "")[:240],
        }
    except Exception as exc:  # noqa: BLE001
        print(f"[openai classify] fallback: {exc}")
        return None


def _openai_suggest(title: str, description: str, category: str, priority: str) -> dict[str, Any] | None:
    if not _openai_available():
        return None

    system = (
        "You are a campus IT helpdesk assistant. Give short, safe, step-by-step "
        "troubleshooting tips for LOW-priority tickets. No passwords, no "
        "destructive commands. Reply with ONLY valid JSON: "
        '{"summary":"one sentence","steps":["step1","step2","step3"],'
        '"ask_technician_if":"when to escalate"}'
    )
    user = (
        f"Category: {category}\nPriority: {priority}\n"
        f"Title: {title}\nDescription: {description}"
    )

    try:
        client = _openai_client()
        kwargs: dict[str, Any] = {
            "model": OPENAI_MODEL,
            "messages": [
                {"role": "system", "content": system},
                {"role": "user", "content": user},
            ],
            "temperature": 0.4,
            "max_completion_tokens": 400,
        }
        if OPENAI_REASONING_EFFORT and OPENAI_REASONING_EFFORT.lower() not in ("", "default"):
            kwargs["reasoning_effort"] = OPENAI_REASONING_EFFORT

        resp = client.chat.completions.create(**kwargs)
        content = _strip_json_fence(resp.choices[0].message.content or "")
        data = json.loads(content)
        steps = data.get("steps") or []
        if not isinstance(steps, list):
            steps = [str(steps)]
        steps = [str(s).strip() for s in steps if str(s).strip()][:6]
        return {
            "summary": str(data.get("summary") or "Try these steps first.")[:300],
            "steps": steps or ["Restart the device and try again."],
            "ask_technician_if": str(
                data.get("ask_technician_if") or "If the problem continues after these steps."
            )[:240],
            "method": "openai",
            "model": OPENAI_MODEL,
        }
    except Exception as exc:  # noqa: BLE001
        print(f"[openai suggest] fallback: {exc}")
        return None


def _keyword_suggest(title: str, description: str, category: str) -> dict[str, Any]:
    cat = _normalize_category(category)
    templates = {
        "account": [
            "Confirm you are using the correct campus username.",
            "Try resetting your password via the official portal (if available).",
            "Wait a few minutes after a reset, then sign in again.",
        ],
        "network": [
            "Toggle Wi-Fi off and on, then reconnect to the campus network.",
            "Forget the Wi-Fi network and join again with the correct password.",
            "Try another device or a wired connection if available.",
        ],
        "hardware": [
            "Fully power off the device, wait 30 seconds, then power on.",
            "Check cables, power brick, and ports for a loose connection.",
            "Test with another known-good cable or peripheral if possible.",
        ],
        "software": [
            "Save your work, then restart the application.",
            "Restart the computer and open the app again.",
            "Check for pending updates, then retry the same action.",
        ],
        "other": [
            "Note the exact error message and when it started.",
            "Restart the device and retry once.",
            "If it still fails, request a technician with the error details.",
        ],
    }
    return {
        "summary": "Quick self-help steps before a technician is assigned.",
        "steps": templates.get(cat, templates["other"]),
        "ask_technician_if": "If these steps do not fix the issue, request a technician.",
        "method": "keyword",
        "model": None,
    }


def _matplotlib_available() -> bool:
    try:
        import matplotlib  # noqa: F401

        return True
    except Exception:  # noqa: BLE001
        return False


@app.get("/health")
def health():
    return jsonify(
        {
            "ok": True,
            "service": "zpgc-classifier",
            "openai_configured": _openai_available(),
            "model": OPENAI_MODEL if _openai_available() else None,
            "fallback": "keyword",
            "matplotlib": _matplotlib_available(),
        }
    )


@app.post("/charts/png")
def charts_png():
    """Render a live dashboard chart PNG with matplotlib."""
    if not _matplotlib_available():
        return jsonify({"ok": False, "error": "matplotlib not installed"}), 503

    from charts import render_chart

    payload = request.get_json(silent=True) or {}
    chart_type = str(payload.get("type") or "").strip().lower()
    chart_payload = payload.get("payload") or {}
    if not isinstance(chart_payload, dict):
        chart_payload = {}

    try:
        png = render_chart(chart_type, chart_payload)
    except Exception as exc:  # noqa: BLE001
        print(f"[charts/png] error: {exc}")
        return jsonify({"ok": False, "error": str(exc)}), 500

    if png is None:
        return jsonify({"ok": False, "error": "unknown chart type"}), 400

    return app.response_class(png, mimetype="image/png")


@app.post("/classify")
def classify():
    payload = request.get_json(silent=True) or {}
    title, description = _text_fields(payload)

    if not title and not description:
        return jsonify({"ok": False, "error": "title or description required"}), 400

    result = _openai_classify(title, description)
    if result is None:
        result = _keyword_classify(title, description)

    return jsonify({"ok": True, **result})


@app.post("/suggest")
def suggest():
    payload = request.get_json(silent=True) or {}
    title, description = _text_fields(payload)
    category = _normalize_category(payload.get("category"))
    priority = _normalize_priority(payload.get("priority") or "low")

    if not title and not description:
        return jsonify({"ok": False, "error": "title or description required"}), 400

    result = _openai_suggest(title, description, category, priority)
    if result is None:
        result = _keyword_suggest(title, description, category)

    result["category"] = category
    result["priority"] = priority
    return jsonify({"ok": True, **result})


if __name__ == "__main__":
    print(f"Classifier starting — OpenAI: {_openai_available()} model={OPENAI_MODEL}")
    app.run(host="127.0.0.1", port=5000, debug=False)
