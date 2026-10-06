# ZPGC Services — Stage 7 install & run

**Current system (1 October 2026):** run Flask and the site from `C:\xampp\htdocs\CP2_V1.6`, not V1.5. See `docs/VERSION_PROGRESS.md`.

## Folders

| Folder | Role |
|--------|------|
| `CP2_V1.4` | Frozen Stage 6 (confirmation) |
| `CP2_V1.5` | Stage 7 (AI category/priority) |

Current site: `http://localhost/CP2_V1.6/pages/login_signup.php`

## Already done on this PC

- Python 3.13 + pip
- `flask`, `flask-cors`, `requests`

## Every time you develop Stage 7

1. Start **XAMPP** Apache + MySQL  
2. Start Flask classifier:

```powershell
cd C:\xampp\htdocs\CP2_V1.6\ai
python classifier_app.py
```

Or double-click `start_classifier.bat`. Leave that window open.

3. Check: http://127.0.0.1:5000/health  
4. Use the PHP site on `/CP2_V1.6/`

## Optional packages (later)

```powershell
python -m pip install scikit-learn joblib
```

Only if a later lesson trains a model. Current classifier is **keyword rules** (honest local demo).
