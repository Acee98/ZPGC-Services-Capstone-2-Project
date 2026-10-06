# Session handoff — 1 October 2026

## Current version
**V1.6** is the working folder: `C:\xampp\htdocs\CP2_V1.6`.

Stages 1–6 are closed. V1.5 added classification, assignment, and charts. V1.6 scores tickets on submit (Tables 4–7), reports resolved tickets by category, and My Profile matches the paper cards.

## Read this first
`docs/VERSION_PROGRESS.md` — shipped features by version, plus what is partial or not in the system.

## Still open
- Luna calls fall back to keyword rules when API credits are exhausted (`kw-quota`).
- Email and SMS switches are saved and not sent.
- No admin audit log, no real satisfaction rating, no load test, no accuracy study.
- Stage 9 (deployment and defense) has not started.

## Run
1. XAMPP Apache + MySQL
2. `C:\xampp\htdocs\CP2_V1.6\ai\start_classifier.bat`
3. `http://localhost/CP2_V1.6/pages/login_signup.php`
