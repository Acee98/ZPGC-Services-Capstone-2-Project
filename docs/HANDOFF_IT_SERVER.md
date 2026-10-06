# ZPGC Services — hand off to IT (no Docker)

Give campus IT a **zip of the site folder** plus a **MySQL dump**. They install it on any ordinary **Apache + PHP + MySQL/MariaDB** stack (XAMPP, WAMP, LAMP, or their existing campus server). Docker is not required.

Working copy to package: `C:\xampp\htdocs\CP2_V1.6`

## What you send them

| Item | How to make it | Notes |
|------|----------------|-------|
| Site zip | Zip the `CP2_V1.6` folder | **Exclude** secrets (see below) |
| Database dump | Export `users_db` from phpMyAdmin or `mysqldump` | Includes users, tickets, messages, tokens |
| This guide | `docs/HANDOFF_IT_SERVER.md` + Word `docs/docx/HANDOFF_IT_SOFTWARE_AND_TRANSFER.docx` | Point them here |
| Optional: AI helper | Zip `ai/` only if they will run Flask | Needs Python + OpenAI key separately |

### Do **not** put these in the shared zip

- `logic/mail.env` (Gmail App Password)
- `.env`, `ai/.env`
- `Google SMTP Pass.txt`, `API Key/`
- Local uploads that contain private attachments (optional: clear `uploads/` first)

Send mail/OpenAI secrets through a **secure channel** (IT ticket / password vault), not email attachment inside the zip.

## Package the site (developer machine)

1. In File Explorer, copy `CP2_V1.6` to a temp folder (e.g. Desktop).
2. Delete or empty secret files listed above from the copy.
3. Right-click the copy → **Send to → Compressed (zipped) folder**.
4. Name it clearly, e.g. `ZPGC_Services_CP2_V1.6_YYYY-MM-DD.zip`.

## Export the database

### Option A — phpMyAdmin

1. Open `http://localhost/phpmyadmin`
2. Select **users_db**
3. **Export** → method **Quick** → format **SQL** → **Go**
4. Save as `users_db_YYYY-MM-DD.sql`

### Option B — command line (XAMPP)

```bat
cd C:\xampp\mysql\bin
mysqldump -u root users_db > C:\Users\ADMIN\Desktop\users_db_YYYY-MM-DD.sql
```

If MySQL root has a password on their server, they will use `-p`.

## What IT installs (their server)

### Requirements

- Apache (or equivalent) with PHP **8.x** preferred (7.4+ usually works)
- MySQL or MariaDB
- PHP extensions commonly enabled in XAMPP: `mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`
- Optional: Python 3.11+ only if they enable the Flask AI helper

### Steps

1. Unzip into the web root, e.g.  
   - Windows XAMPP: `C:\xampp\htdocs\CP2_V1.6\`  
   - Linux: `/var/www/html/CP2_V1.6/`
2. Create database `users_db` (utf8mb4).
3. Import `users_db_YYYY-MM-DD.sql` into `users_db`.
4. Edit `logic/config.php` if their MySQL user/password/host are not `root` / blank / `localhost`:

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "users_db";
```

5. If the folder is **not** named `CP2_V1.6`, update the session cookie path in `logic/session_config.php`:

```php
define('ZPGC_COOKIE_PATH', '/CP2_V1.6/');
```

Change `/CP2_V1.6/` to match the URL path (leading and trailing slash).

6. Create `logic/mail.env` on the server (never commit it) using values IT provides, same keys as local:

```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=their-gmail@gmail.com
MAIL_PASSWORD=their-app-password
MAIL_FROM_NAME=ZPGC Services
```

7. Ensure `uploads/` is writable by the web server (mailbox attachments).

8. Start Apache + MySQL. Open:

`http://<server-host>/CP2_V1.6/pages/login_signup.php`

### Optional Flask AI (not required for login/tickets)

Follow `docs/SETUP_STAGE7_PYTHON.md` / `docs/SETUP_OPENAI.md` on the server. Without Flask, keyword fallback still classifies tickets.

## Smoke test checklist for IT

- [ ] Login page loads with CSS (not unstyled HTML)
- [ ] Signup + email verification (if SMTP configured)
- [ ] Admin activates a user from Utilities
- [ ] User submits a ticket
- [ ] Admin assigns technician; techn updates status
- [ ] Mailbox send (if SMTP + attachments folder OK)
- [ ] Performance page opens; search filters by ticket ID
- [ ] Hard refresh still keeps session (`ZPGCSESSID` cookie path matches folder)

## Folder rename / subdirectory notes

Session cookies are scoped to `/CP2_V1.6/`. If IT hosts at a different path (e.g. `/zpgc/`), they **must** change `ZPGC_COOKIE_PATH` or logins will appear to “forget” on refresh.

## Related docs already in the repo

- `docs/SETUP_DATABASE.md` — empty DB table scripts if dump import fails
- `docs/SETUP_V1.4_TO_V1.6.md` — upgrades from older campus copies
- `docs/SETUP_OPENAI.md` — AI helper
- `docs/PRE_DEFENSE.md` — defense checklist

## Privacy reminder

Treat the SQL dump as **personal data** (names, emails, ticket text). Share only with authorized IT, and delete temporary copies from USB drives after install.
