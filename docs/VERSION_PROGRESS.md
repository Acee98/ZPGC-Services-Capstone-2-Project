# ZPGC Services — progress report

**System / paper label:** ZPGC Services **V1.6** (documentation freeze 16 October 2026)  
**Active folder:** `C:\xampp\htdocs\CP2_V1.6`  
**Report date:** 16 October 2026 (1 October body kept; October follow-up below)  
**Paper:** ZPGC Capstone Paper REVISED, section 1.3 Objectives

The general objective is to design and develop an Intelligent IT Helpdesk Ticketing System that uses an OpenAI GPT model for college students and university staff, so that ticket handling is less manual, response is faster, and the office can see what is happening from the data.

The specific build objectives are the user, admin, and technician modules, plus AI classification, performance analysis, ticket routing, and communication. The study also has four evaluation objectives: classification accuracy, behavior under many users, service measures such as average response time and resolution rate, and security (encryption, role control, an audit log, and privacy). Those four are not finished measurements. They are written at the end of this report.

**Software size (19 October 2026).** Application PHP (pages + logic, no probes) is about **15,900** non-blank lines. Client JS we wrote is about **2,800**. Styles (including `css/critical_ui_fixes.css`) are about **12,900**. Chart.js and `ai/` / `tools/` probes are **not** counted as application code. See `docs/SOFTWARE_SIZE.md`. Layout CSS is a `.css` file, not a 4,800-line PHP dump; screens and behavior are unchanged.

## Deliverables by date

| Date | Version | Deliverable | Objective it serves |
|------|---------|-------------|---------------------|
| 27 Jun – 13 Jul 2026 | CP2 | Landing page, signup, login, and the user, technician, and admin page shells. | Start of the user, technician, and admin modules. |
| 14 Jul – 5 Aug 2026 | v1.1 | Users database and login that uses prepared statements. | User access. Passwords are stored as hashes, which is the part of the security objective that is in place. |
| 6 – 24 Aug 2026 | v1.1.2 | Admin user list, account approval, and a ticket form that saves a ticket. | Admin user management. User issue-request management. |
| 25 Aug – 9 Sep 2026 | v1.2 | Ticket lists for each role, technician assignment, separate sessions, and a mailbox on the ticket that refreshes about every 3 seconds. | Technician ticket handling. Communication module. Admin ticket control. |
| 10 – 14 Sep 2026 | v1.3 | Manual priority, a fixed 9-slot queue (3 Critical, 3 Moderate, 3 Low), sample dashboard charts, and a theme switch. | Early admin dashboard and queue. Priority was still chosen by a person. |
| 14 – 15 Sep 2026 | v1.4 | After the technician finishes, the user chooses Solved or Not solved yet. | Technician resolution management and the user side of closing a ticket. |
| 16 – 20 Sep 2026 | v1.5 | A helper program on this PC that can call OpenAI, word-rule backup, least-busy assignment, low-priority troubleshooting steps, and live dashboard charts. | AI ticket classification, ticket routing, troubleshooting suggestions, and the admin dashboard. |
| 25 Sep – 1 Oct 2026 | v1.6 | Submit itself scores urgency times impact (paper Tables 4–6), adds 40 points when 30 open tickets share the same subject (Table 5), lends empty seats in the 9-slot queue (Table 7), reports resolved tickets by category with response and resolution clocks, and My Profile matches the paper layout. | Priority assignment inside classification, performance analysis from real closed tickets, and the user profile described in the paper. |
| Not a dated folder | Stage 8 | Functional checks of the paths already built. | Evaluation has started only as “does this screen work.” Accuracy, load, and formal service rates are not done. |
| 4–15 Oct 2026 | Stage 9 (in progress) | Azure smoke test, Hostinger live (`zpgc-services.online`), desktop ticket tables aligned (**B-092** / **A-116**). Defense package still open. | Deployment preparation. |
| 16 Oct 2026 | Stage 8–9 freeze | ISO judgment + KPIs, student/staff, encryption, concurrent 10×3, user-only CSAT, Utilities Student/Staff filters, Chapter 5 `.docx` (**A-118–A-123**). | Documentation + remaining paper-gap product. |

V1.6 is the build to show. Stages 1 through 7 are closed in the **product** (the official timeline PNGs still show Stage 6 In Progress and 7–9 Planned). Stage 8 is functional testing plus a browser concurrent check; the Luna accuracy study is not run. Stage 9 is live hosting + Chapter 5, not the oral-defense script.

## What is in place, by objective

**User — issue requests.** A user submits a category, subject, and description. Categories are Hardware, Software, Network, Account, and Other.

**User — troubleshooting.** A Low ticket shows steps first. The user can close it or ask for a technician.

**User — monitoring.** The user dashboard counts Ongoing, Processing, and Resolved and lists recent tickets.

**Admin — dashboard and ticket control.** Admin sees status, priority, the assigned technician, the 9-slot queue, and live charts.

**Admin — user management.** Admin can create accounts, set a role, and approve an inactive account.

**Technician — status, handling, and resolution.** The technician moves a ticket through Pending, Ongoing, Processing, and Confirming. The user marks it solved, or sends it back.

**AI classification.** A helper program reads the text. When the paid OpenAI account answers, it can set the category and the urgency and impact numbers. The label is then Low (score 1–2), Moderate (3–6), or Critical (7 and above).

**Ticket routing.** Moderate and Critical tickets go to the active technician with the fewest open tickets.

**Communication.** Mailbox stores messages on the ticket and refreshes while that tab is open.

**Performance analysis.** Resolved tickets are counted by category, split into Critical, Moderate, and Low. Tickets closed after the clocks were added show a response time and a resolution time.

## If the adviser asks what is not finished

**Updated 16 October 2026.** The 1 October answers below this box are **stale** on ISO, KPIs, CSAT, student/staff, encryption, audit, email, and concurrent users. Use this box in the defense.

**Still open:** Luna vs keyword **accuracy study**; dummy emails that bounce (**B-090**); API credits (`kw-quota`); oral-defense script.

**Now in the product:** ISO/IEC 25010:2023 **judgment table** (not a certificate); office KPI cards including average response/resolution; user **How was this visit?** (admin/technician history shows the label or —); Student vs Staff `account_kind`; ticket text **AES-256-GCM**; Utilities **Audit security check**; Gmail SMTP (verify, reset, ticket mail); specialty routing (Hardware tech does not see Software tickets) **plus** least-busy among matching techs; browser **concurrent check** 10×3 on Performance (not a lab soak test). Phone/SMS sending was removed.

Say what the system does, then the limit. Do not claim the evaluation objectives are complete.

**“Is ISO 25010 implemented?”**  
There is an ISO/IEC 25010:2023 screen on Performance that maps product-quality characteristics to evidence in this build. It is a judgment frame, not a certification.

**“Did you measure AI accuracy?”**  
No. The paper discusses about 92% for an OpenAI-style model as a published comparison. This system does not score its own right and wrong classifications. Keyword Kaggle baseline is **A-106**. Luna side-by-side is not run.

**“What happens when many users submit at once?”**  
Performance has **Run concurrent user check** (10 rounds × 3 = 30 browser requests). That is a health check, not a laboratory load test of hundreds of sessions.

**“What is the average response time and the resolution rate?”**  
Office-wide KPI cards on Performance. Each resolved ticket also shows its own clocks.

**“Is there an audit log?”**  
Yes, **Audit security check** on Utilities (who did what to accounts).

**“Is data encrypted, and do you meet a privacy regulation?”**  
Passwords hashed; roles enforced; ticket subject/description encrypted at rest (`Z1:` AES-256-GCM). A short privacy note is `docs/PRIVACY.md`. That is not a full regulatory audit.

**“Can the user rate the service?”**  
Yes, after **Solved**. Only the requester rates. History on other roles shows the label or —.

**“Do email and SMS notifications go out?”**  
Email: yes, from zpgc111@gmail.com (verify, reset, ticket events) when the address is real. SMS: not sent; phone fields were removed.

**“Are students and staff different accounts?”**  
Same User role, with **Student** or **Staff** (`account_kind`). Utilities filters them separately.

**“Does it route to a hardware team or a network team?”**  
Yes for specialty: technicians only see matching categories. Among those, assignment is least-busy.

**“Does the AI always answer?”**  
No. If the paid API account has no credits, the helper uses word rules and the database stores `kw-quota`. The ticket is still filed and still scored.

**“Does a widespread outage jump the queue?”**  
Only when 30 or more unresolved tickets have the exact same subject. The score then gains 40 points and becomes Critical, which matches Table 5. Similar wording that is not the same subject does not count. The Table 4 caption also says “+30.” The code follows Table 5 and Table 6.

## Still partial, in one list

| Item | Date it first appeared | Deliverable that exists | What is still short, and which objective |
|------|------------------------|-------------------------|------------------------------------------|
| One User role for students and staff | 14 Jul – 5 Aug 2026 | Login role `user` plus `account_kind` Student/Staff (Oct 2026) | Closed as kinds on the same User role. |
| Account category | 6 – 24 Aug 2026 | Categories Hardware, Software, Network, Account, Other | Password and RFID concerns are typed in the description. They are not their own workflows. Issue requests. |
| AI call | 16 – 20 Sep 2026 | Helper program and `kw-quota` when credits are gone | Classification is the model only when the API answers. AI classification objective. |
| Urgency and impact | 25 Sep – 1 Oct 2026 | Score stored on the ticket | The 1–3 axes come from the model only on a successful call. Otherwise word rules set them. Classification objective. |
| Same-subject +40 | 25 Sep – 1 Oct 2026 | Escalation when the subject text matches | Not a meaning match. Tables 5 and 6, not the “+30” caption in Table 4. |
| Who receives the ticket | 16 – 20 Sep 2026 | Least-busy among technicians whose specialty matches the category | Closed as specialty + workload, not a separate “team” app. |
| Performance | 25 Sep – 1 Oct 2026 | Counts by category, per-ticket clocks, office KPI cards (Oct 2026) | Formal evaluation write-up of those numbers is still the paper’s job. |
| Mailbox | 25 Aug – 9 Sep 2026 | Messages saved on the ticket | Refreshes about every 3 seconds only while Mailbox is open. Admin cannot send. |
| Profile switches | 1 Oct 2026 | Email switch and language; phone/SMS sending removed | Email goes out when the address is real. |
| Charts | 16 – 20 Sep 2026 | Chart.js from live counts; CSAT from user ratings on resolved tickets | Hate-it block on admin dashboard when ratings are 1. |

## Not in the system

| Item | Why it is named | What to say |
|------|-----------------|-------------|
| ISO/IEC 25010:2023 certification | Paper evaluation | Judgment table is on Performance. Not a certified quality assessment. |
| Classification accuracy study | Evaluation objective on how often the category and severity are right | Not run. The 92% figure in the paper is a published comparison, not a result from this system. |
| Laboratory load test | Evaluation objective on many users and many tickets at once | Browser concurrent check 10×3 exists. A soak/load lab run does not. |
| Admin audit log | Admin module objective | Built on Utilities (account actions). |
| Encryption and privacy review | Security evaluation objective | Ticket text encrypted at rest; `docs/PRIVACY.md`. Not a regulator audit. |
| User satisfaction rating | Paper evaluation of service quality | Built: requester rates after Solved. |
