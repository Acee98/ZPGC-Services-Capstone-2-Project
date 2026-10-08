# Ticket classifier service

Optional local helper for ZPGC Services ticket category and priority suggestions.

## Azure / hosted PHP

Classification runs in PHP (`logic/ai_classify.php`) with `OPENAI_API_KEY`. This folder is not required on the web server.

## Local XAMPP

1. Copy `.env.example` to `.env` and set the API key.
2. Install dependencies: `pip install -r requirements.txt`
3. Start with `start_classifier.bat` (or run `classifier_app.py`).

If no API key is available, the app falls back to keyword classification.
