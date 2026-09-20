"""
ZPGC Services — Stage 7 ticket classifier (Flask).
Rule/keyword based — local demo, no paid API required.

Run (PowerShell):
  cd C:\\xampp\\htdocs\\CP2_V1.5\\ai
  python classifier_app.py

Then open: http://127.0.0.1:5000/health
"""

from flask import Flask, jsonify, request
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

CATEGORIES = ("hardware", "software", "account", "network", "other")
PRIORITIES = ("critical", "moderate", "low")

CATEGORY_KEYWORDS = {
    "hardware": (
        "monitor", "keyboard", "mouse", "printer", "laptop", "cpu", "ram",
        "hard drive", "ssd", "motherboard", "cable", "charger", "screen",
        "hardware", "pc won't turn", "power button",
    ),
    "software": (
        "install", "update", "windows", "office", "excel", "word", "chrome",
        "application", "app crash", "error code", "software", "license",
        "program", "driver install",
    ),
    "account": (
        "password", "login", "username", "account", "locked out", "reset",
        "email access", "permission", "cannot sign in", "credentials",
    ),
    "network": (
        "wifi", "wi-fi", "internet", "network", "ethernet", "lan", "vpn",
        "no connection", "offline", "router", "ip address", "dns",
    ),
}

PRIORITY_KEYWORDS = {
    "critical": (
        "urgent", "critical", "down", "cannot work", "all users", "entire lab",
        "exam", "deadline", "no one can", "outage", "emergency",
    ),
    "moderate": (
        "several", "multiple", "slow", "intermittent", "sometimes",
        "affecting", "class", "department",
    ),
    "low": (
        "one unit", "only 1", "only one", "minor", "cosmetic", "when free",
        "low priority", "single computer",
    ),
}


def _score_bucket(text, buckets):
    best = None
    best_score = 0
    for label, words in buckets.items():
        score = sum(1 for w in words if w in text)
        if score > best_score:
            best_score = score
            best = label
    return best, best_score


def classify_text(subject, description):
    text = f"{subject} {description}".lower()
    category, cat_score = _score_bucket(text, CATEGORY_KEYWORDS)
    if not category:
        category = "other"
        cat_score = 0

    priority, pri_score = _score_bucket(text, PRIORITY_KEYWORDS)
    if not priority:
        priority = "moderate"
        pri_score = 0

    confidence = min(0.95, 0.35 + 0.12 * (cat_score + pri_score))
    return {
        "category": category,
        "priority": priority,
        "confidence": round(confidence, 2),
        "method": "keyword",
    }


@app.get("/health")
def health():
    return jsonify({"ok": True, "service": "zpgc-classifier", "version": "1.5"})


@app.post("/classify")
def classify():
    data = request.get_json(silent=True) or {}
    subject = (data.get("subject") or "").strip()
    description = (data.get("description") or "").strip()
    if not subject and not description:
        return jsonify({"error": "subject or description required"}), 400

    result = classify_text(subject, description)

    # Priority JSON key corrected (was planted typo "prioriy" — B-050).
    payload = {
        "category": result["category"],
        "priority": result["priority"],
        "confidence": result["confidence"],
        "method": result["method"],
    }
    return jsonify(payload)


if __name__ == "__main__":
    # Bind localhost only — PHP on the same PC calls this.
    app.run(host="127.0.0.1", port=5000, debug=False)
