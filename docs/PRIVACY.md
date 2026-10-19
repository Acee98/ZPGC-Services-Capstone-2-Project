# ZPGC Services — privacy and ticket-text protection

This note is the privacy write-up for the V1.6 helpdesk. It describes what the product stores, who can see it, and how ticket text is protected. It is not a legal opinion and it is not a claim of ISO/IEC 27001 certification.

## What we collect

- Account name, email, role (Student, Staff, Technician, Administrator), password hash, language, and email-notification preference.
- Ticket subject, description, category, severity, status, assignment, timestamps, optional satisfaction rating, and in-app mailbox messages.
- An admin audit log of account and ticket-admin actions.
- Optional resume files on technician applications.

We do not collect a phone number. Contact is email and the in-app mailbox.

## Who can see what

| Role | Access |
|------|--------|
| Student / Staff (User) | Own tickets, own mailbox, own profile |
| Technician | Tickets in their specialty (assigned or still unassigned) |
| Administrator | All tickets, users, performance, audit log, ISO/service view |

Pages check the signed-in role on every request. A deactivated account cannot keep using an old session.

## How ticket text is protected

- Passwords are stored with PHP `password_hash` (bcrypt/argon as configured). They are never stored in plain text.
- Ticket **subject**, **description**, and **AI guidance** are encrypted at rest with **AES-256-GCM**. Ciphertext is prefixed `Z1:`. The key lives in `logic/ticket.key` (or `ZPGC_TICKET_KEY`) on the server and is not committed to Git.
- A SHA-256 **subject hash** is stored so the paper’s “same subject × 30” escalation still works without keeping a second plaintext copy for matching.
- The site is served over HTTPS on the public host. Session cookies are HTTP-only and HTTPS-aware.
- State-changing forms use a CSRF token.
- Uploads are image-only, size-limited, and tied to a ticket the caller may access.

Mailbox message bodies are still stored as written by the sender. Do not paste passwords into tickets or chat.

## What this does not do

- It does not encrypt every column in MySQL (IDs, status, category, and times stay searchable).
- It does not hide ticket text from administrators or the assigned technician — they need it to do the work.
- It does not replace a campus Data Privacy Act / GDPR compliance review. The office still decides retention, backup, and who is appointed as a personal-information controller.

## Retention

Resolved tickets stay in Ticket History and Performance until an administrator runs the documented retention / disposal tools. Hard-delete of a resolved ticket is not the default close path.

## User rights (campus practice)

A student or staff member may ask an administrator to correct their name or email (email change re-triggers verification). Account deletion is an administrator action on Utilities. Questions about stored tickets should go to the campus IT office that operates this helpdesk.
