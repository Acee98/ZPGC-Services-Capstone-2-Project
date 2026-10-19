# Chapter 5  
# Presentation of the Developed System

**System / paper label:** ZPGC Services **V1.6**  
**Documentation freeze:** 16 October 2026  
**Live URL:** https://zpgc-services.online  
**Working folder:** `CP2_V1.6` (same product version; Stages 8–9 are not a new v1.7)  

This chapter presents **ZPGC Services V1.6**. Use that exact wording in captions, the title page, and the system specification. The live chrome shows **ZPGC Services V1.6** under the sidebar logo (role pages) and on public screens. Figures follow the requester’s path, then technician hiring, the approved technician workspace, and the administrator workspace. Optional extras (empty ticket form, subject typeahead, quick-view modal, rating overlay, initials menu, Light settings, and header Search) are omitted.

Paste each captured screenshot immediately under its figure caption.

---

## 5.1 Public access (unauthenticated)

Campus users reach the service without an account, then create or recover one.

**Figure 5.1 (S01).** Landing page of ZPGC Services, with welcome copy, **Signup now!**, and **Login**.

**Figure 5.2 (S02).** Login: TSU or Gmail address, password, **Forgot Password?**, and a link to signup.

**Figure 5.3 (S03).** Signup: name, campus or Gmail address, password, **I am a…** (Student / Staff / Technician), Terms checkbox, and **Signup**.

**Figure 5.4 (S04).** Terms of Service (opening sections): who may use the system and required acceptance.

**Figure 5.4b (S04b).** Terms (later sections): ticket routing, Low-ticket troubleshooting, ISO/IEC 25010, and account changes.

**Figure 5.5 (S05).** Forgot password: email field and send control.

**Figure 5.5b (S05b).** After send: notice that a reset message is issued if the address is registered.

**Figure 5.5c (S05c).** TSU Outlook: *Reset your ZPGC Services password* (crop the token in the URL for print).

**Figure 5.5d (S05d).** Reset Password: new password, confirm, **Save password**.

**Figure 5.5e (S05e).** Login after a successful reset: *Password updated. You can log in now.*

**Figure 5.6 (S06).** Verify Email: campus address, 6-digit field, **Verify email** and **Resend code**.

**Figure 5.6b (S06b).** TSU Outlook Inbox: *Your ZPGC Services verification code* (blur the code and mailbox list for print).

---

## 5.2 Requester (Student / Staff)

A verified student or staff account submits IT tickets, follows AI troubleshooting on Low tickets, confirms resolution, and messages the assigned technician.

**Figure 5.7 (S08).** User Dashboard: Ongoing, Processing, and Resolved counts with the requester’s tickets (severity, status, View).

**Figure 5.8 (S09).** Tickets: open list, **New Ticket**, collapsed **AI troubleshooting tips**, and Ticket History below.

**Figure 5.9 (S10).** Submit New Ticket: subject *Monitor shows only black screen*, description, word counts, **Submit Ticket**. Category is assigned by classification, not by picking from the subject list.

**Figure 5.10 (S12).** After submit: *Ticket #1087 submitted. Priority set to Low (score 1)* and automatic technician assignment; Hardware / Ongoing.

**Figure 5.11 (S13).** AI troubleshooting tips expanded for a blank monitor: numbered steps, **These steps worked** and **Still not fixed — Request Technician**.

**Figure 5.12 (S15).** User Tickets: ticket in **Confirming** with **Solved** and **Not Solved Yet** in the Confirm column (ticket #1089, Hardware, *Monitor display problem*).

**Figure 5.13 (S15b).** After **Not Solved Yet**: *Ticket sent back as Not Solved Yet*; the ticket returns to **Ongoing**.

**Figure 5.14 (S16b).** After the requester rates the visit: *Thanks. Ticket #1087 was marked Very satisfied and archived from your active list*; the row appears under Ticket History as Resolved.

**Figure 5.15 (S17).** Ticket History of resolved and archived tickets, including satisfaction labels such as Very satisfied and Hate it.

**Figure 5.16 (S19).** User Mailbox: thread list and an open ticket (for example #1068) with a message composer. Only the requester and the assigned technician can send.

**Figure 5.17 (S21).** My Profile as **Student** (name, email, account type; no phone field).

**Figure 5.18 (S22).** Settings: Theme (Dark) and change-password fields.

---

## 5.3 Technician application and hiring

A signup as Technician verifies email first, then submits a specialty and résumé. The administrator approves or declines; Outlook carries both outcomes.

**Figure 5.19 (S27).** Technician application: specialties (Hardware, Software, Network, Account, Other) and résumé drop zone. Email is already verified; admin approval is still required.

**Figure 5.19b (S27b).** Application form with **Submit application** and **Sign out**.

**Figure 5.19c (S27c).** Application ready to submit (specialty selected, PDF attached).

**Figure 5.20 (S28).** After submit: **Pending** until an administrator reviews the application.

**Figure 5.21 (S28c).** Outlook after **approve**: *Your ZPGC technician account is active*.

**Figure 5.21b (S28d).** Outlook after **reject**: *ZPGC technician application declined*.

---

## 5.4 Approved technician

Technicians see only tickets in their specialty. They move work through Pending, Ongoing, Processing, and Confirming. They do not mark Resolved; the requester confirms Solved or Not Solved Yet.

**Figure 5.22 (S29).** Technician Dashboard: Ongoing, Processing, Resolved, and Pending counts plus assigned tickets.

**Figure 5.23 (S30).** Technician Tickets: status control, queue timer, View / Save / transfer, Priority filter, and Ticket History.

**Figure 5.23b (S30b).** Technician Ticket History: registration time, resolution time, and satisfaction (including Hate it). Satisfaction is display-only; technicians cannot rate.

**Figure 5.24 (S31).** Status options: Pending, Ongoing, Processing, Confirming.

**Figure 5.24b (S31b).** After Save: *Ticket updated to Confirming.*

**Figure 5.25 (S32).** Quick view of an assigned ticket (subject, status, priority, description, category).

**Figure 5.26 (S34).** Technician Performance: office KPIs (resolution rate, average response, average resolution, open tickets) and resolved work by category.

**Figure 5.27 (S35).** Technician My Profile.

**Figure 5.28 (S36).** Technician Settings (theme and password).

---

## 5.5 Administrator

The administrator oversees all tickets, the nine-slot severity queue, hiring, accounts, performance against ISO/IEC 25010:2023 characteristics, and a concurrent-user check. The administrator mailbox is read-only.

**Figure 5.29 (S37).** Admin Dashboard: status overview, charts, and recent history.

**Figure 5.30 (S38).** Negative (Hate it) ratings on the admin dashboard, when present.

**Figure 5.31 (S39).** Admin Tickets: campus-wide ticket list and status filters.

**Figure 5.32 (S40).** Nine-slot priority queue on Tickets (three Critical, three Moderate, three Low).

**Figure 5.33 (S41).** Performance: four office KPI cards (resolution rate, average response, average resolution, open tickets).

**Figure 5.34 (S42).** ISO/IEC 25010:2023 table and **Run concurrent user check**.

**Figure 5.35 (S43).** After the check: last-run line (for example 30/30 successful requests).

**Figure 5.36 (S44).** Performance detail: category counts, response and resolution times.

**Figure 5.37 (S45).** Utilities: Technician applications (none pending), account list, **+ Add User**, and filters **All**, **Student**, **Staff**, **Technician**, **Administrator**, and **Pending Approval**. There is no Requesters tab. Student and Staff list only those account kinds; if none exist, the table shows *No accounts match this filter.*

**Figure 5.38 (S46).** Utilities → **New user**: name, email, Student / Staff, temporary password, **Create account**.

**Figure 5.38b (S46b).** After save: *Account … created and active.*

**Figure 5.39 (S47).** Edit user: role, Student or staff (when the role is requester), or Technical role when the account is a technician.

**Figure 5.40 (S48).** Technician applications awaiting review.

**Figure 5.41 (S49).** Review application: approve or decline.

**Figure 5.42 (S50).** Audit security check on Utilities.

**Figure 5.43 (S51).** Admin Mailbox: thread open, read-only (*Only the user and the assigned technician can send messages*).

**Figure 5.44 (S52).** Admin My Profile (Administrator).

**Figure 5.45 (S53).** Admin Settings: theme and password.

---

## 5.6 How the screens map to the process

| Process step | Who acts | Figures |
|--------------|----------|---------|
| Create and verify an account | Public | 5.1–5.6 |
| Submit an IT ticket; AI classifies category and priority | Student / Staff | 5.7–5.10 |
| Try Low-priority self-help, or request a technician | Student / Staff | 5.11 |
| Work the ticket; set Confirming | Technician | 5.22–5.24 |
| Confirm Solved or Not Solved Yet; CSAT is stored on history | Student / Staff | 5.12–5.15 |
| Message on the ticket | User and assigned technician | 5.16; technician mailbox if included |
| Hire technicians | Applicant + Administrator | 5.19–5.21, 5.40–5.41 |
| Oversee queue, accounts, ISO, and capacity | Administrator | 5.29–5.45 |

The requester, not the technician or administrator, closes the visit (Solved / Not Solved Yet) and records satisfaction. History on technician and admin screens shows that rating as text, not as a Rate visit control.

---

## Notes for layout in Word

1. Keep one screenshot per figure; crop the browser frame if the format requires it.  
2. Blur 6-digit codes, reset URLs, and personal mailboxes.  
3. Dark-theme captures are acceptable; they match the live default Appearance setting.  
4. Insert Figure 5.12 using the Confirming shot with **Solved** and **Not Solved Yet** on ticket #1089.  
5. Captions and the paper title page must say **ZPGC Services V1.6**. Recapture role pages after the version mark is live so the sidebar shows that label.
