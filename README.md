# ZPGC Services — Capstone 2 (V1.6)

Web-based IT helpdesk for Zamboanga Peninsula Green Haven College. End users submit tickets; technicians update work; administrators manage queues, users, mailbox traffic, retention, and performance reports. Ticket category and priority can be suggested through the project’s OpenAI-based classifier.

## Requirements

- PHP 8.2+
- MySQL 8 (or compatible)
- Optional: OpenAI API key for classification; SMTP for verification and password reset mail

## Clone for teammates (Cursor + XAMPP)

GitHub: [Acee98/ZPGC-Services-Capstone-2-Project](https://github.com/Acee98/ZPGC-Services-Capstone-2-Project)

1. In Cursor: **File → Open Folder**, or clone first:
   ```bash
   cd C:/xampp/htdocs
   git clone https://github.com/Acee98/ZPGC-Services-Capstone-2-Project.git CP2_V1.6
   ```
2. In Cursor, open `C:\xampp\htdocs\CP2_V1.6`.
3. Create MySQL database `zpgc_services_db` and import the team dump, then patches in [`database/README.md`](database/README.md).
4. Copy env templates (do **not** commit the filled copies):
   - `logic/mail.env.example` → `logic/mail.env`
   - `ai/.env.example` → `ai/.env`
5. Start **Apache** and **MySQL** in XAMPP, then open:

`http://localhost/CP2_V1.6/pages/login_signup.php`

Secrets (`logic/mail.env`, `ai/.env`, Gmail/Azure passwords) stay on each machine. Ask an admin for SMTP and OpenAI values; never push them to GitHub.

Cloud deployment (Azure / Hostinger), App Settings, and smoke tests are in [`DEPLOY.md`](DEPLOY.md).

## User roles

| Role | Main capabilities |
|------|-------------------|
| User | Create tickets, confirm resolution, mailbox, satisfaction rating |
| Technician | Work assigned tickets, update status, mailbox |
| Administrator | Priority queue, utilities, performance, activate users, data retention |

Accounts created through signup remain inactive until the email is verified and an administrator activates them.

## Ticket classification (`ai/`)

Classification is part of the product feature set (see Terms). On Azure, PHP calls OpenAI using `OPENAI_*` settings (`logic/ai_classify.php`). The `ai/` folder holds an optional local Flask service for the same API when developing on XAMPP. Setup notes: [`ai/README.md`](ai/README.md).

## Security

- Password hashing; HTTPS-aware session cookies; optional MySQL session store for App Service
- CSRF tokens on state-changing POST requests
- Image uploads only, size-limited, authorized per ticket
- Admin-only diagnostic pages: `pages/ai_status.php`, `pages/mail_status.php`

This release is intended for campus demonstration and supervised testing, not as a full enterprise security stack (no dedicated WAF or global rate limiting).

## Database

Apply the base dump and V1.6 SQL patches as described in [`database/README.md`](database/README.md).  
By default the app may create missing tables/columns (`ZPGC_ALLOW_RUNTIME_DDL=1`). After migrations are applied in a controlled environment, set `ZPGC_ALLOW_RUNTIME_DDL=0`.

## Project layout

| Path | Contents |
|------|----------|
| `pages/` | Role dashboards and public pages |
| `logic/` | Auth, tickets, mail, classification, CSRF |
| `css/`, `js/`, `images/` | Front-end assets |
| `database/` | SQL patches and indexes |
| `ai/` | Optional local classifier service |
| `tools/` | Optional Cloudflare Worker for OpenAI proxy |

## Version

Git tag: **V1.6**.
