# Deploy & live test — Azure (then Hostinger)

ZPGC Services is PHP + MySQL. Use **Azure** for a short live smoke test, then move the same repo to **Hostinger** for longer hosting.

Local XAMPP still works with no env vars (defaults in `logic/config.php`).

---

## 1. What to create in Azure (live test)

### A. MySQL (required)

1. Portal → **Create a resource** → **Azure Database for MySQL Flexible Server**.
2. Pick a cheap/dev SKU (Burstable B1ms is fine for testing).
3. Note: **Server name**, **admin login**, **password**.
4. Networking: allow public access for the test, and add your client IP + **Allow public access from any Azure service**.
5. In **Azure Cloud Shell** or MySQL Workbench, create the app database:

```sql
CREATE DATABASE zpgc_services_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

6. Import schema from this repo (phpMyAdmin / Workbench / `mysql` CLI), in order:
   - base `zpgc_services_db` dump (current V1.6 data/schema)
   - then `database/v1.6_ticket_archive.sql` if `tickets.archived_at` is missing
   - then `database/v1.6_satisfaction.sql` if `tickets.satisfaction` is missing (Customer Satisfaction survey + chart)
   - optional: `database/v1.6_performance_indexes.sql` for faster ticket queries
   - any other feature SQL your team already documented in `database/`

### B. Web app (PHP)

1. Portal → **Create a resource** → **Web App**.
2. Runtime: **PHP 8.2** (or 8.3 if listed).
3. OS: **Linux** is simplest for PHP; Windows also works with the included `web.config`.
4. After create, open the app → **Configuration** → **Application settings** and add:

| Name | Example |
|------|---------|
| `DB_HOST` | `your-server.mysql.database.azure.com` |
| `DB_USER` | `adminuser@your-server` (Azure often needs `user@server`) |
| `DB_PASSWORD` | *(MySQL password)* |
| `DB_NAME` | `zpgc_services_db` |
| `DB_PORT` | `3306` |
| `DB_SSL` | `1` |
| `MAIL_HOST` | `smtp.gmail.com` (must match the mailbox — Gmail→gmail.com, TSU Outlook→`smtp.office365.com`) |
| `MAIL_PORT` | `587` |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_USERNAME` | your Gmail |
| `MAIL_PASSWORD` | Gmail App Password (no spaces) |
| `MAIL_FROM` | same as username |
| `MAIL_FROM_NAME` | `ZPGC Services` |
| `MAIL_APP_URL` | `https://YOUR-APP.azurewebsites.net` |
| `OPENAI_API_KEY` | *(new secret key — required for Luna on Azure)* |
| `OPENAI_MODEL` | `gpt-5.6-luna` |
| `OPENAI_REASONING_EFFORT` | `none` |
| `ZPGC_ALLOW_RUNTIME_DDL` | `1` (default). Set `0` after SQL migrations are applied for a hardened deploy. |

PHP calls OpenAI **directly** on Azure (no Flask). Do not set `AI_CLASSIFIER_URL` unless you host the Python helper elsewhere.

After deploy, as **admin**, open `/pages/ai_status.php?probe=1` on the live site.  
Expect `openai_key_present: true`, `openai_key_looks_valid: true`, and `probe.method: "openai"`.  
If `probe.method` is `keyword`, read `probe.error` / `probe.fallback_reason`.

5. Save → app restarts.

6. **Health Check** (fixes Azure “Health Check Feature Not Utilized”):  
   App Service → **Health check** → enable → path `/health.php` → save.  
   Azure will ping this every minute and recycle unhealthy instances.

7. **Uploads survive deploy automatically** — mailbox images are stored under Azure’s persistent `/home/site/uploads` (not inside the Git deploy folder). Optional override: App Setting `ZPGC_UPLOAD_ROOT=/home/site/uploads`.  
   Images uploaded *before* this change may already be gone (redeploys wiped `wwwroot/uploads`); re-attach those photos once.

8. **Single-instance alert**: Free **F1** App Service cannot scale to 2+ instances. That Azure warning is expected on the student/free plan. Use a paid B1+ plan only if you need zero-downtime platform upgrades.

### C. Deploy the code

**Option 1 — GitHub (recommended)**  
App Service → **Deployment Center** → connect `Acee98/ZPGC-Services-Capstone-2-Project` → branch `main` → deploy.

**Option 2 — ZIP**  
Zip the project (exclude `.git`, `docs`, `ai/data/*.csv`, `.venv`) → App Service → **Advanced Tools (Kudu)** → ZIP deploy, or use VS Code Azure extension.

**Current live App Service (Korea):**  
`https://zpgc-services-jp-dudeeuefc4eqdgek.koreacentral-01.azurewebsites.net`

Default entry: open `https://YOUR-APP.azurewebsites.net/pages/login_signup.php`  
(If the site root does not redirect, bookmark that URL for the test.)

---

## 2. Live smoke test checklist (Azure)

Do these in order after deploy:

1. **Login page loads** (no MySQL connection error).
2. **Sign up / verify email** (or use a known admin) — confirm Gmail SMTP works with `MAIL_*` settings.
3. **Create a ticket** as a user.
4. **Admin / technician** can open, update, resolve.
5. After resolve: ticket leaves active lists and appears under **Ticket History**.
6. **Performance** still shows resolved work.
7. **Utilities → Data retention**: eligible disposal count may be `0` until rated≥30d / unrated≥60d.
8. Confirm session survives refresh (cookie path `/` on Azure root).

If login “does nothing”, check `MAIL_APP_URL` matches the real `https://…azurewebsites.net` URL (no trailing slash) and that the site is HTTPS.

Tear down the Azure MySQL + Web App when the smoke test is done so you are not billed after moving to Hostinger.

---

## 3. Hostinger (production after Azure)

1. Hostinger hPanel → **Websites** → create site / subdomain.
2. **PHP** 8.1+ (prefer 8.2).
3. **MySQL**: create database + user; import the same SQL as Azure.
4. Upload via Git deploy, File Manager, or FTP — same repo contents.
5. Set environment / `.env` / panel variables the same way (`DB_*`, `MAIL_*`).
   - On shared hosting without App Settings, edit `logic/mail.env` on the server only and set `MAIL_APP_URL=https://your-domain.com`.
   - For MySQL on Hostinger, usually `DB_SSL` is **not** needed; leave unset.
6. Point the domain document root at the project folder (or `public` if you later add one). Entry remains `pages/login_signup.php` unless you add an `index.php` redirect.

---

## 4. Do not deploy these to the public internet

- `pages/dev_fix_admin.php` (local password helper — blocked off localhost, still do not rely on it in cloud)
- `tools/fix_admin_password.php`
- Real Gmail App Passwords in chat or screenshots
- Large `ai/data/*.csv` dumps

Prefer Azure/Hostinger **Application settings** for `DB_PASSWORD` and `MAIL_PASSWORD` instead of committing secrets.

---

## 5. Package for an external IT reviewer

Zip or clone the app without local-only folders:

- Exclude `docs/`, `API Key/`, `backup_db/`, `database/backups/`
- Exclude secret files: `ai/.env`, `logic/mail.env`, `Flexible_Server_Creds.txt`, `Google SMTP Pass.txt`
- Include runtime code under `pages/`, `logic/`, `css/`, `js/`, `database/`, `ai/` (without `.env`)

OpenAI / Luna classification is an intentional product feature — keep those files.
