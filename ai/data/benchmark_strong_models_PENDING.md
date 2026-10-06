# Stronger OpenAI models — pending benchmarks (no API run)

**Status:** NOT measured. These models were **not** called, so no Acc / Precision / Recall / F1 exists for them on the ZPGC protocol.

**Why:** Measuring them requires OpenAI Chat Completions API usage (platform tokens / billing). Per request, no live site and **no OpenAI tokens** were used.

Paper Table 3 OpenAI literature target: **92% Acc · 90% P · 90.5% R · 91% F1**.  
Already measured (cheap models, remapped Kaggle n=200): best **~60.5%** Acc (`gpt-4.1-nano`). Hitting ~90% on *this* gold set is unlikely with zero-shot alone; fine-tuning on campus labels is the realistic path.

## Models queued (suggested earlier)

| Model | Role | Est. cost / 200 tickets* | Command (when you allow spend) |
|-------|------|--------------------------|--------------------------------|
| `gpt-4.1` | Best first full model | ~$0.15–0.40 | see below |
| `gpt-4o` | Strong generalist | ~$0.20–0.50 | |
| `gpt-5` | Flagship ceiling | higher / varies | |
| `o4-mini` | Cheap reasoning | mid | |
| `o3` | Heavy reasoning (slow) | highest | |

\*Rough: ~400–800 input + ~80–150 output tokens per ticket × list price. Exact cost depends on prompt size and model pricing that day.

## Offline-only options available now (already safe)

```bat
php ai/benchmark_kaggle.php --mode=keyword --n=200 --out=ai/data/benchmark_keyword_n200_seed42.json
php ai/rebuild_paper_metrics.php
python ai/eval_paper_sklearn.py ai/data/benchmark_luna_report.json
```

Keyword + rebuild use **no** OpenAI tokens.

## When you approve API spend (local CLI only — not the website)

From `C:\xampp\htdocs\CP2_V1.6` with `OPENAI_API_KEY` in `ai/.env`:

```bat
php ai/benchmark_kaggle.php --mode=luna --n=200 --model=gpt-4.1 --out=ai/data/benchmark_gpt41_full_report.json
php ai/benchmark_kaggle.php --mode=luna --n=200 --model=gpt-4o --out=ai/data/benchmark_gpt4o_full_report.json
php ai/benchmark_kaggle.php --mode=luna --n=200 --model=gpt-5 --out=ai/data/benchmark_gpt5_full_report.json
php ai/benchmark_kaggle.php --mode=luna --n=200 --model=o4-mini --out=ai/data/benchmark_o4_mini_report.json
php ai/rebuild_paper_metrics.php
```

Or run the helper (still spends tokens when executed):

```bat
ai\run_strong_model_benchmarks.bat
```

Then check `ai/data/benchmark_table3_system_accuracy.md` for Table 3 columns.
