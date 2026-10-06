# ZPGC Services — progress report

**System:** ZPGC Services, a web-based AI-assisted IT helpdesk  
**Active folder:** `C:\xampp\htdocs\CP2_V1.6`  
**Report date:** 1 October 2026  
**Paper:** ZPGC Capstone Paper REVISED, section 1.3 Objectives

The general objective is to design and develop an Intelligent IT Helpdesk Ticketing System that uses an OpenAI GPT model for college students and university staff, so that ticket handling is less manual, response is faster, and the office can see what is happening from the data.

The specific build objectives are the user, admin, and technician modules, plus AI classification, performance analysis, ticket routing, and communication. The study also has four evaluation objectives: classification accuracy, behavior under many users, service measures such as average response time and resolution rate, and security (encryption, role control, an audit log, and privacy). Those four are not finished measurements. They are written at the end of this report.

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
| Not started | Stage 9 | Deployment notes and the defense package. | Closing the study. Not a running feature. |

V1.6 is the build to show. Stages 1 through 6 are closed. V1.5 added the AI path. Stage 8 is only partly done. Stage 9 has not started.

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

Say what the system does, then the limit. Do not claim the evaluation objectives are complete.

**“Is ISO 20000 implemented?”**  
No. The paper uses ISO/IEC 20000-1:2018 as a way to judge the system later: service levels, incident resolution, and how efficiently the office runs. The system handles incidents and stores times. It does not check itself against that standard, and there is no ISO screen.

**“Did you measure AI accuracy?”**  
No. The paper discusses about 92% for an OpenAI-style model as a published comparison. This system does not score its own right and wrong classifications. That belongs to the accuracy evaluation, which has not been run.

**“What happens when many users submit at once?”**  
Not tested. There is no load test of concurrent sessions or a large burst of tickets. The scalability objective is still open.

**“What is the average response time and the resolution rate?”**  
Not reported as one number. Each resolved ticket can show its own response time and resolution time, and Performance counts how many were resolved in each category. An office-wide average and a resolution rate are not on the screen. That is the service-performance evaluation, still open.

**“Is there an audit log?”**  
No. Admin Audit Security Check is in the objectives. The system records passwords as hashes and keeps each role out of the other role’s pages. It does not keep a log of who changed a user, a ticket, or a setting.

**“Is data encrypted, and do you meet a privacy regulation?”**  
Passwords are hashed, and pages check the signed-in role. There is no separate encryption of the ticket text, and no privacy-compliance review has been written. That part of the security objective is not done.

**“Can the user rate the service?”**  
No. The satisfaction chart is filled from how many tickets are resolved versus still open. It is not a rating the user gave. The paper’s evaluation mentions user satisfaction. That survey is not in the product.

**“Do email and SMS notifications go out?”**  
No. My Profile saves the switches, the phone number, and the language. Nothing sends an email or a text from those switches.

**“Are students and staff different accounts?”**  
No. Both use the User role. The technician and the administrator are separate roles.

**“Does it route to a hardware team or a network team?”**  
No. Assignment is the active technician with the fewest open tickets. The paper’s routing objective is met as workload routing, not as specialist teams.

**“Does the AI always answer?”**  
No. If the paid API account has no credits, the helper uses word rules and the database stores `kw-quota`. The ticket is still filed and still scored. Say that the live model is `gpt-5.6-luna`. The paper names GPT-4o mini or a later successor.

**“Does a widespread outage jump the queue?”**  
Only when 30 or more unresolved tickets have the exact same subject. The score then gains 40 points and becomes Critical, which matches Table 5. Similar wording that is not the same subject does not count. The Table 4 caption also says “+30.” The code follows Table 5 and Table 6.

## Still partial, in one list

| Item | Date it first appeared | Deliverable that exists | What is still short, and which objective |
|------|------------------------|-------------------------|------------------------------------------|
| One User role for students and staff | 14 Jul – 5 Aug 2026 | Login role `user` | No separate staff type. User module. |
| Account category | 6 – 24 Aug 2026 | Categories Hardware, Software, Network, Account, Other | Password and RFID concerns are typed in the description. They are not their own workflows. Issue requests. |
| AI call | 16 – 20 Sep 2026 | Helper program and `kw-quota` when credits are gone | Classification is the model only when the API answers. AI classification objective. |
| Urgency and impact | 25 Sep – 1 Oct 2026 | Score stored on the ticket | The 1–3 axes come from the model only on a successful call. Otherwise word rules set them. Classification objective. |
| Same-subject +40 | 25 Sep – 1 Oct 2026 | Escalation when the subject text matches | Not a meaning match. Tables 5 and 6, not the “+30” caption in Table 4. |
| Who receives the ticket | 16 – 20 Sep 2026 | Least-busy active technician | Not a specialist team. Routing objective, partial. |
| Performance | 25 Sep – 1 Oct 2026 | Counts by category and per-ticket clocks | No single average response time or resolution rate. Performance objective, partial. The evaluation objective is not done. |
| Mailbox | 25 Aug – 9 Sep 2026 | Messages saved on the ticket | Refreshes about every 3 seconds only while Mailbox is open. Communication objective, partial. |
| Profile switches | 1 Oct 2026 | Phone, email switch, SMS switch, language saved | Messages are not sent. |
| Charts | 16 – 20 Sep 2026 | Chart.js from live counts; matplotlib available for PNG export | Satisfaction chart is not a user rating. Admin dashboard, partial. |

## Not in the system

| Item | Why it is named | What to say |
|------|-----------------|-------------|
| ISO/IEC 20000-1:2018 | Paper evaluation: service levels, incident handling, operational efficiency | Not implemented. No ISO screen or checklist. |
| Classification accuracy study | Evaluation objective on how often the category and severity are right | Not run. The 92% figure in the paper is a published comparison, not a result from this system. |
| Load test | Evaluation objective on many users and many tickets at once | Not run. |
| Average response time and resolution rate | Evaluation objective on service performance | Clocks exist per ticket. The office-wide figures are not computed. |
| Admin audit log | Admin module objective: Admin Audit Security Check | Not built. |
| Encryption and privacy review | Security evaluation objective | Only password hashes and role checks are in place. |
| User satisfaction rating | Paper evaluation of service quality | Users cannot rate a closed ticket. |
