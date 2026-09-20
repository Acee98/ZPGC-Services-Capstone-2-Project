@echo off
cd /d "%~dp0"
echo Starting ZPGC classifier on http://127.0.0.1:5000 ...
python classifier_app.py
pause
