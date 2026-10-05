@echo off
cd /d "%~dp0"
echo Starting ZPGC AI classifier on http://127.0.0.1:5000
echo Make sure ai\.env has OPENAI_API_KEY if you want gpt-5.6-luna.
python classifier_app.py
pause
