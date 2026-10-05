# Ticket classifier service

Optional local helper for ZPGC Services ticket category and priority suggestions.

## Usage

**Azure / hosted PHP**  
Classification normally runs in PHP (`logic/ai_classify.php`) with `OPENAI_API_KEY` and related App Settings. This folder is not required on the web server.

**Local XAMPP**  
1. Copy `.env.example` to `.env` and set the API key.  
2. Install dependencies: `pip install -r requirements.txt`  
3. Start with `start_classifier.bat` (or run `classifier_app.py`).  

If no API key is available, the app falls back to keyword classification.

## Main files

| File | Purpose |
|------|---------|
| `classifier_app.py` | Local HTTP classifier |
| `charts.py` | Optional local chart helper |
| `.env.example` | Environment template (do not commit real keys) |
| `requirements.txt` | Python dependencies |

Campus-facing wording for the feature is in `pages/terms.php`.
