# ZPGC Services — V1.4 (Stage 6) working notes

**Current system (1 October 2026):** `C:\xampp\htdocs\CP2_V1.6`. Stage 6 confirmation is closed. Paths below are the folder where this stage was written. See `docs/VERSION_PROGRESS.md`.

Folder: `C:\xampp\htdocs\CP2_V1.5`  
Copied from V1.3 on 19 September 2026. Cookie path: `/CP2_V1.5/`.

## Official focus (Q-012)

User confirmation after the technician finishes:

- Technician marks work ready → status `awaiting_confirmation` (Confirming card)
- Reporter chooses **Solved** → `resolved`
- Reporter chooses **Not Solved Yet** → back to `ongoing` (+ optional note in mailbox)

## Before coding

1. Use URL `http://localhost/CP2_V1.5/...` (not V1.3).
2. Keep Apache + MySQL running in XAMPP.
3. phpMyAdmin: extend `tickets.status` ENUM with `awaiting_confirmation` (see `database/v1.5_stage6.sql`).
4. Do **not** install Python / Flask yet — that is Stage 7 (AI). Stage 6 stays PHP + MySQL.

## Already installed on this PC (keep using)

- XAMPP (Apache, MySQL, phpMyAdmin)
- PHP 8.x (bundled with XAMPP)
- Browser (Chrome/Edge) for testing
- Any code editor (VS Code or similar)
- Git + Git Bash (push when V1.4 is done; leave V1.3 tag alone)

## Optional later (not for first Stage 6 lessons)

- Local Chart.js already in `js/chart.umd.js` (no CDN needed)
- Composer is present but not required for confirmation UI
- Node is present but not required for this stage
