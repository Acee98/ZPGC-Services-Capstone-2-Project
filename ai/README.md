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

## Capstone paper metrics (Table 3 / §4.4.4)

Evaluation follows Accuracy, Precision, Recall, and F1 from a confusion matrix
(True Positive / False Positive / False Negative / True Negative per class),
matching Scikit-learn:

```text
CA  = correctly_classified / N
PR_c = TP_c / (TP_c + FP_c)
RE_c = TP_c / (TP_c + FN_c)
F1_c = 2 * PR_c * RE_c / (PR_c + RE_c)
```

Table 3 scalar Precision / Recall / F1 use **weighted** averages (class imbalance).

| Command | Purpose |
|---------|---------|
| `php ai/benchmark_kaggle.php --mode=luna --n=200` | Run model benchmark (writes confusion + paper metrics) |
| `php ai/rebuild_paper_metrics.php` | Rebuild Table 3 from existing JSON reports |
| `python ai/eval_paper_sklearn.py ai/data/benchmark_luna_report.json` | Verify with Scikit-learn |

Outputs: `ai/data/benchmark_table3_system_accuracy.json` and `.md`.

## Main files

| File | Purpose |
|------|---------|
| `classifier_app.py` | Local HTTP classifier |
| `metrics_paper.php` | Paper formulas (Acc / P / R / F1) |
| `benchmark_kaggle.php` | Kaggle remap benchmark |
| `rebuild_paper_metrics.php` | Table 3 rebuild |
| `eval_paper_sklearn.py` | Scikit-learn verification (§4.4.4) |
| `charts.py` | Optional local chart helper |
| `.env.example` | Environment template (do not commit real keys) |
| `requirements.txt` | Python dependencies |

Campus-facing wording for the feature is in `pages/terms.php`.
