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
                                      
PRIORITIES = ("critical", "moderate", "low")

KEYWORD_RULES: list[tuple[str, str]] = [
    (r"\b(password|login|otp|2fa|account|username|lock(ed)?|reset|sign\s*in)\b", "account"),
    (r"\b(wifi|wi-?fi|internet|network|lan|vpn|router|dns|offline|disconnect|website)\b", "network"),
    (r"\b(blue\s*screen|bsod|overheat|fan|battery|charger|keyboard|mouse|monitor|printer|hardware|ram|ssd|hdd|turn on)\b", "hardware"),
    (r"\b(install|update|crash|freeze|slow|software|app|excel|word|chrome|outlook|license|activation|program)\b", "software"),
]


def _score_priority(urgency: int, impact: int) -> str:
    score = max(1, min(3, urgency)) * max(1, min(3, impact))
    if score >= 9:
        return "critical"
    if score >= 3:
        return "moderate"
    return "low"


def _estimate_axes(title: str, description: str) -> tuple[int, int]:
    blob = f"{title} {description}".lower()
    urgency = 1
    if re.search(r"\b(outage|down|stopped|stoppage|cannot work|can't work|blocker|emergency|total|won't turn on|will not turn on)\b", blob):
        urgency = 3
    elif re.search(r"\b(slow|crash|error|degraded|not working|freeze|frozen|intermittent|keeps)\b", blob):
        urgency = 2
    impact = 1
    if re.search(r"\b(server|department|everyone|all users|whole|campus|organization|entire|building)\b", blob):
        impact = 3
    elif re.search(r"\b(group|several|multiple|class|faculty|laboratory|lab |we |our |room )\b", blob):
        impact = 2
    return urgency, impact

def _normalize_category(value: Any) -> str:
    text = str(value or "").strip().lower()
    return text if text in CATEGORIES else "other"

def _normalize_priority(value: Any) -> str:
                                                      
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
    matched: list[str] = []

    for pattern, cat in KEYWORD_RULES:
        if re.search(pattern, blob, flags=re.IGNORECASE):
            category = cat
            matched.append(pattern)
            break

    urgency, impact = _estimate_axes(title, description)
    confidence = 0.72 if matched else 0.45
    return {
        "category": category,
        "priority": _score_priority(urgency, impact),
        "urgency": urgency,
        "impact": impact,
        "confidence": confidence,
        "method": "keyword",
        "model": None,
        "matched_rules": matched[:3],
    }

_LAST_OPENAI_ERROR: str | None = None

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
    global _LAST_OPENAI_ERROR
    _LAST_OPENAI_ERROR = None
    if not _openai_available():
        _LAST_OPENAI_ERROR = "no_key"
        return None

    system = (
        "You classify campus IT helpdesk tickets for ZPGC. "
        "Do not assign priority. The system computes priority as Urgency×Impact "
        "(1-2 Low, 3-6 Moderate, 9 Critical) and adds +40 if 30+ identical open reports exist. "
        "Reply with ONLY valid JSON: "
        '{"category":"hardware|software|network|account|other",'
        '"urgency":1,"impact":1,"confidence":0.0,"rationale":"short reason"} '
        "category from the ticket text only. "
        "urgency 1=can wait, 2=needs attention soon, 3=must be fixed immediately. "
        "impact 1=one person, 2=group/class/lab, 3=department or whole campus. "
        "urgency and impact must be integers 1, 2, or 3."
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
        urgency = int(data.get("urgency") or 0)
        impact = int(data.get("impact") or 0)
        if urgency not in (1, 2, 3) or impact not in (1, 2, 3):
            return None
        return {
            "category": _normalize_category(data.get("category")),
            "priority": _score_priority(urgency, impact),
            "urgency": urgency,
            "impact": impact,
            "confidence": float(data.get("confidence") or 0.8),
            "method": "openai",
            "model": OPENAI_MODEL,
            "rationale": str(data.get("rationale") or "")[:240],
        }
    except Exception as exc:                
        text = str(exc)
        if "insufficient_quota" in text or "credit_balance_exhausted" in text:
            _LAST_OPENAI_ERROR = "quota"
        else:
            _LAST_OPENAI_ERROR = "openai_error"
        print(f"[openai classify] fallback ({_LAST_OPENAI_ERROR}): {text[:300]}")
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
    except Exception as exc:                
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
        import matplotlib              

        return True
    except Exception:                
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
    except Exception as exc:                
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
        reason = _LAST_OPENAI_ERROR
        if reason:
            result["fallback_reason"] = reason
            if reason == "quota":
                result["method"] = "kw-quota"

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
