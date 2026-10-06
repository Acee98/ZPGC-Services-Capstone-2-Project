# OpenAI GPT-5.6 Luna — ZPGC setup

**Current folder (1 October 2026):** `C:\xampp\htdocs\CP2_V1.6`. Commands below that say `CP2_V1.5` were the Stage 7 path; use `CP2_V1.6` for the running system. Submit no longer has **Suggest with AI**. With no API credits the classifier records `kw-quota`. See `docs/VERSION_PROGRESS.md`.

Paper model ID to use in code: **`gpt-5.6-luna`**  
(Do **not** use the short alias `gpt-5.6` — that maps to a different model family.)

## Free ChatGPT vs API credits

| | ChatGPT free / Plus | OpenAI **API** |
|---|---|---|
| Where | chat.openai.com | platform.openai.com |
| Key needed? | No | Yes (`sk-…`) |
| Works in our Flask app? | No | Yes |

**Free ChatGPT chat credits are not the same as API credits.**  
You cannot paste a ChatGPT login into this project. The classifier needs an **API key** from [platform.openai.com](https://platform.openai.com/).

### Before you buy $5

1. Create / sign in to an OpenAI **API** account.
2. Open **Billing** / **API credits**. New accounts sometimes get a small **trial / free API credit** — if you see a balance > $0, you can test Luna **before** buying $5.
3. If the balance is **$0** and requests return `insufficient_quota` / `429`, buy the **$5** prepaid credit, then retry.
4. Create an API key under **API keys** → copy it once → put it only in `ai/.env` (never GitHub, never screenshots in the paper).

Until a key is present, the app still works: Flask uses the **keyword fallback** (same as Stage 7 demo).

## One-time install

```bat
cd C:\xampp\htdocs\CP2_V1.6\ai
python -m pip install -r requirements.txt
copy .env.example .env
notepad .env
```

In `.env`:

```env
OPENAI_API_KEY=sk-xxxxxxxx
OPENAI_MODEL=gpt-5.6-luna
OPENAI_REASONING_EFFORT=none
```

`reasoning_effort=none` keeps Luna cheap/fast for classification.

## Database columns (phpMyAdmin)

Run SQL from `database/v1.5_openai_features.sql` on `users_db`:

- `ai_guidance` — troubleshooting text for low-priority tickets  
- `ai_method` — `openai` / `keyword` / `auto_assign`  
- `created_at` — for live Dashboard weekly chart  

## Start classifier

Double-click `ai/start_classifier.bat` **or**:

```bat
cd C:\xampp\htdocs\CP2_V1.6\ai
python classifier_app.py
```

Check: [http://127.0.0.1:5000/health](http://127.0.0.1:5000/health)  
Expect `"openai_configured": true` when the key is valid.

## What the system does

1. **Submit** calls `/classify`, then scores urgency × impact (Tables 4–6).  
2. **Moderate and Critical** assign the least-busy active technician (`ongoing`).  
3. **Low** saves troubleshooting tips; no technician until **Request Technician**.  
4. **Admin Dashboard** charts read live MySQL counts.  
5. **Performance** counts resolved tickets by category and shows response and resolution times (A-073). It does not yet show an average response time.

## Security

- `ai/.env` is gitignored.  
- Never commit real keys.  
- Rotate the key on platform.openai.com if it leaks.
