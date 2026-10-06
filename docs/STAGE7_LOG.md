# Stage 7 — closed 17–18 September 2026

**Current system (1 October 2026):** development continued in `C:\xampp\htdocs\CP2_V1.6`. Suggest with AI was removed in A-067; submit now scores the ticket. Flask still starts from `CP2_V1.6\ai\start_classifier.bat`. See `docs/VERSION_PROGRESS.md`.

## Done
| ID | Item |
|----|------|
| A-052 | Python + Flask install |
| A-053 | Flask keyword classifier `:5000` |
| A-054 | Ticket Suggest with AI |
| A-055 | Verified priority save + title layout |
| B-049 | Wrong port 5001 → 5000 |
| B-050 | JSON `prioriy` → `priority` |
| B-051 | INSERT saves priority |
| B-052 | New Ticket title clipped → title inside form |

## How to run
1. XAMPP Apache + MySQL  
2. Current classifier: `C:\xampp\htdocs\CP2_V1.6\ai\start_classifier.bat`  
3. Current site: `http://localhost/CP2_V1.6/pages/login_signup.php`  
   (This stage was first run from `CP2_V1.5`.)

## Next
V1.6 continued this work (Tables 4–7, performance, My Profile). Stage 8 testing is still open for load and accuracy. See `docs/VERSION_PROGRESS.md`.
