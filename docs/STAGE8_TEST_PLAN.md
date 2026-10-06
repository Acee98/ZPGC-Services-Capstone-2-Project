# Stage 8 — Testing checklist (Q-009)

**Current folder (1 October 2026):** `C:\xampp\htdocs\CP2_V1.6`  
**URL:** `http://localhost/CP2_V1.6/pages/login_signup.php`  
**Progress report:** `docs/VERSION_PROGRESS.md`

The checklist below was written for V1.5. In V1.6 these steps changed:

- There is no **Suggest with AI** button and no manual Priority dropdown (A-067). Submit scores the ticket.
- A Low ticket shows troubleshooting steps before a technician is assigned.
- B2, B7, C2, and G2 below describe the V1.5 screen. Use submit-and-score instead of Suggest, and do not expect a Priority field of None.

## Before you start

1. Start **XAMPP** → Apache + MySQL  
2. Start Flask: double-click `C:\xampp\htdocs\CP2_V1.6\ai\start_classifier.bat` (leave open)  
3. Check http://127.0.0.1:5000/health → `"ok": true`  
4. Use **Ctrl+F5** after any code change  

## Progress (18–20 Sep 2026)

| Section | Status |
|---------|--------|
| A1 Login page | pass |
| B User + AI (#1005) | pass (B-053 fixed mid-way) |
| C Admin assign / queue | pass |
| D Techn → Confirming | pass |
| E Not Solved Yet → Ongoing | pass |
| F Mailbox / Settings / Utilities | **in discussion** |
| G AI down | **pass** (#1006 manual submit) |
| F Settings / Utilities | optional |


---

## A. Login / roles

| # | Steps | Pass if |
|---|--------|---------|
| A1 | Open login page | Login/signup form loads |
| A2 | Log in as **user** | Opens user dashboard / tickets |
| A3 | Log out → log in as **technician** | Opens technician pages |
| A4 | Log out → log in as **admin** | Opens admin dashboard |
| A5 | Wrong password | Stays on login with error (no crash) |
| A6 | Inactive account (if you have one) | Cannot enter work pages |

---

## B. User — New Ticket + AI (Stage 7)

| # | Steps | Pass if |
|---|--------|---------|
| B1 | User → **+ New Ticket** | Full title **Submit New Ticket** visible (not clipped) |
| B2 | Subject: `Monitor blank` / Desc: `One lab monitor shows nothing` → **Suggest with AI** | Category ≈ hardware; Priority fills |
| B3 | Subject: `Locked out` / Desc: `Forgot password` → Suggest | Category ≈ account |
| B4 | Subject: `No wifi` / Desc: `Cannot browse on Wi-Fi` → Suggest | Category ≈ network |
| B5 | Change dropdowns manually, then **Submit Ticket** | Returns to user Tickets; new row appears |
| B6 | Admin Tickets → find that ticket | **Priority** matches what you submitted (not None) |
| B7 | Submit with Priority **None** | Ticket saves; priority stays None/NULL |

---

## C. Admin — assign, priority, queue

| # | Steps | Pass if |
|---|--------|---------|
| C1 | Admin → Tickets | List loads; filters All / Pending / … / Confirming / Resolved |
| C2 | Set Priority Critical/Moderate/Low + Save | Value sticks after refresh |
| C3 | Assign to a technician + Save | Assigned name shows |
| C4 | Check **Priority queue** | Counts match tickets with priority set (not resolved) |
| C5 | Filter **Confirming** | Only `awaiting_confirmation` tickets |

---

## D. Technician — status (Stage 6)

| # | Steps | Pass if |
|---|--------|---------|
| D1 | Technician → Tickets | Sees assigned (or available) tickets |
| D2 | Open status dropdown | Has Pending / Ongoing / Processing / **Confirming** — **no Resolved** |
| D3 | Set **Confirming** + Save | Status becomes Confirming |
| D4 | Try to set Resolved | Not available (or rejected) |

---

## E. User — confirmation (Stage 6)

| # | Steps | Pass if |
|---|--------|---------|
| E1 | User who owns a **Confirming** ticket → Tickets | **Solved** and **Not Solved Yet** buttons show |
| E2 | Click **Solved** | Status → Resolved; buttons gone |
| E3 | Put another ticket to Confirming → **Not Solved Yet** | Status → **Ongoing** (not Pending) |

---

## F. Mailbox / Settings (smoke)

| # | Steps | Pass if |
|---|--------|---------|
| F1 | Open Mailbox (user or tech with a ticket thread) | Messages load; can send |
| F2 | Settings → theme toggle | Theme switches and persists after refresh |
| F3 | Admin → Utilities | Role filters + Edit / Deactivate / Delete work |

---

## G. AI service down (expected failure)

| # | Steps | Pass if |
|---|--------|---------|
| G1 | Stop Flask (Ctrl+C in classifier window) | |
| G2 | Suggest with AI | Clear error (cannot reach AI) — site does not crash |
| G3 | Still Submit Ticket manually | Ticket still saves without AI |

---

## When done

- Mark each row Pass / Fail in your notes or backlog (**A-056** testing pass, or new **B-** for fails).  
- Failures → screenshot → new B-issue → fix → retest.  
- Optional: push GitHub tag **V1.5** after a clean pass.
