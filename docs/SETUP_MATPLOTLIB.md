# Matplotlib for ZPGC Dashboard

**Current folder (1 October 2026):** `C:\xampp\htdocs\CP2_V1.6`. Install matplotlib in that folder’s `ai` directory. See `docs/VERSION_PROGRESS.md`.

## Modes

| Mode | What you see |
|------|----------------|
| **Day-to-day Admin UI** | **Chart.js** canvases + live MySQL (`DASHBOARD_CHART_DATA`) |
| **Paper / PNG export** | **matplotlib** via Flask `POST /charts/png` + `logic/dashboard_chart_png.php` |

Both use `logic/dashboard_stats.php`.

## Install

```bat
cd C:\xampp\htdocs\CP2_V1.6\ai
python -m pip install matplotlib
```

Restart classifier: `ai\start_classifier.bat`  
Health: `http://127.0.0.1:5000/health` → `"matplotlib": true`

## Docs shots (A-066)

Matplotlib Dashboard screenshots were taken with clean titles (no “(live)” / fallback banners), then the UI was reverted to Chart.js.

## Switch Dashboard to matplotlib PNGs again (if needed)

Replace `pages/partials/dashboard_charts.php` with the `<img src="../logic/dashboard_chart_png.php?type=…">` grid (see git/history / A-065). Keep Flask running.

## Switch back to Chart.js (current default)

1. Canvas markup in `pages/partials/dashboard_charts.php` (Tickets Report / Categories / Satisfaction / Severity).
2. `admin.php`: `$dashboard_charts = dashboard_chart_data($conn);`
3. Scripts: `chart.umd.js` → set `window.DASHBOARD_CHART_DATA` → `dashboard_static_charts.js`
