@echo off
REM Stronger-model Kaggle benchmarks (CLI only — does NOT touch the live website).
REM WARNING: Each model run CALLS the OpenAI API and uses your platform tokens/billing.
REM Do not run this unless you explicitly approve spend.
REM
REM Usage:
REM   ai\run_strong_model_benchmarks.bat
REM   ai\run_strong_model_benchmarks.bat gpt-4.1
REM   ai\run_strong_model_benchmarks.bat gpt-4.1 gpt-4o

setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0\.."

if not exist "ai\.env" (
  echo Missing ai\.env with OPENAI_API_KEY. Aborting.
  exit /b 1
)

if "%~1"=="" (
  set "MODELS=gpt-4.1 gpt-4o gpt-5 o4-mini"
) else (
  set "MODELS=%*"
)

echo.
echo === This will spend OpenAI tokens for: %MODELS% ===
echo Press Ctrl+C to cancel, or
pause

for %%M in (%MODELS%) do (
  echo.
  echo --- Benchmarking %%M ---
  set "SAFE=%%M"
  set "SAFE=!SAFE:.=_!"
  set "SAFE=!SAFE:-=_!"
  php ai/benchmark_kaggle.php --mode=luna --n=200 --model=%%M --out=ai/data/benchmark_!SAFE!_report.json
  if errorlevel 1 (
    echo FAILED: %%M
  ) else (
    echo OK: %%M -^> ai/data/benchmark_!SAFE!_report.json
  )
)

php ai/rebuild_paper_metrics.php
echo.
echo Done. See ai\data\benchmark_table3_system_accuracy.md
endlocal
