# System Accuracy Table (Paper Table 3 columns)

Measured on ZPGC Kaggle remap. Formulas: Accuracy, Precision, Recall, F1 (weighted).

## Literature baselines (from paper Table 3)

| Model | Accuracy | Precision | Recall | F1-Score | Speed | Training |
|-------|----------|-----------|--------|----------|-------|----------|
| OpenAI (literature) | 92.0% | 90.0% | 90.5% | 91.0% | 80-150 | Low |
| BERT (literature) | 91.0% | 90.8% | 89.5% | 90.1% | 1,000-1,500 | High |
| RoBERTa (literature) | 93.5% | 91.5% | 92.2% | 92.7% | 1,200 | High |
| DistilRoBERTa (literature) | 88.0% | 88.0% | 88.0% | 87.9% | 2,000-4,000 | Medium |

## ZPGC measured system results (category)

| Model | N | Accuracy | Precision | Recall | F1-Score | Training |
|-------|---|----------|-----------|--------|----------|----------|
| keyword_n200 | 200 | 51.5% | 73.3% | 51.5% | 51.2% | None |
| keyword_n3000 | 3000 | 46.5% | 70.3% | 46.5% | 50% | None |
| gpt-5.6-luna | 200 | 57% | 68% | 57% | 55.6% | Low (prompt / zero-shot) |
| gpt-4o-mini | 200 | 54.5% | 68.5% | 54.5% | 52.5% | Low (prompt / zero-shot) |
| gpt-4.1-nano | 200 | 60.5% | 68.4% | 60.5% | 58.9% | Low (prompt / zero-shot) |
| gpt-4.1-mini | 200 | 57.5% | 69.8% | 57.5% | 55.3% | Low (prompt / zero-shot) |
| gpt-5-mini | 200 | 56.5% | 66.4% | 56.5% | 54.3% | Low (prompt / zero-shot) |
