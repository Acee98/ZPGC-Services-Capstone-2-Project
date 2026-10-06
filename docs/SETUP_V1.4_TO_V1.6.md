# ZPGC Services — setup from V1.4 through V1.6

This guide is for someone who can already run **V1.3** (XAMPP Apache and MySQL, login, tickets, the 9-slot queue). It does not repeat the V1.3 install. It covers what was added after that: user confirmation, Python and Flask, OpenAI, matplotlib, and the V1.6 score, performance, and profile columns.

The working copy used for the defense is:

`C:\xampp\htdocs\CP2_V1.6`

Open the site only at:

`http://localhost/CP2_V1.6/pages/login_signup.php`

The session cookie path is `/CP2_V1.6/`. A tab that is still on the V1.3 address will not stay signed in here.

## What you already have from V1.3

Keep using these. Do not reinstall them.

- XAMPP: Apache, MySQL, phpMyAdmin, PHP
- Database name used by this copy: **users_db**
- A browser

Python is **not** part of V1.3. It starts at V1.5.

## 1. Place the V1.6 folder

Copy or clone the project so the site folder is `C:\xampp\htdocs\CP2_V1.6`. Start **Apache** and **MySQL** in the XAMPP Control Panel. If login from V1.3 already works, `users_db` is the database to update. Do not create a second database.

## 2. V1.4 — confirmation status

V1.4 lets the user choose **Solved** or **Not solved yet** after the technician marks the ticket ready. MySQL must allow the status `awaiting_confirmation`.

In phpMyAdmin, select **users_db**, open the **SQL** tab, and run:

```sql
ALTER TABLE tickets
    MODIFY status ENUM(
        'pending',
        'ongoing',
        'processing',
        'awaiting_confirmation',
        'resolved'
    ) NOT NULL DEFAULT 'pending';
```

If phpMyAdmin says the column already has that list, skip this step.

Do not run `database/patch_user_confirmation_v1_4.sql` as written. That file names a database `zpgc_services_db`. This project uses **users_db**.

Check: Structure → `tickets` → `status` includes `awaiting_confirmation`.

## 3. V1.5 — install Python

Install **Python 3.13** from python.org. On the first installer screen, tick **Add python.exe to PATH**.

Close and reopen PowerShell, then check:

```bat
python --version
python -m pip --version
```

You should see Python 3.13 and a pip version. If Windows says `python` is not recognized, the PATH box was not ticked. Reinstall Python with that box checked, or use the `py` launcher (`py --version`).

## 4. V1.5 — Python packages and the API key file

In PowerShell:

```bat
cd C:\xampp\htdocs\CP2_V1.6\ai
python -m pip install -r requirements.txt
copy .env.example .env
notepad .env
```

`requirements.txt` installs Flask, flask-cors, requests, openai, python-dotenv, and matplotlib. Matplotlib is the chart library from the paper. One install covers both the AI service and the PNG charts.

Edit `ai\.env` so it contains:

```env
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-5.6-luna
OPENAI_REASONING_EFFORT=none
```

Replace `sk-your-key-here` with an API key from [platform.openai.com](https://platform.openai.com/), under **API keys**. Use the model id **gpt-5.6-luna**. Do not use the short name `gpt-5.6`. `none` keeps classification cheaper and faster.

A ChatGPT chat login is not an API key. ChatGPT free or Plus credit does not fill the API balance. The key belongs only in `ai\.env` on that PC. Do not commit it, and do not paste it into the paper or a screenshot.

Until a key is present, the site still accepts tickets. Flask uses keyword rules. After a failed paid call because the API balance is zero, the ticket is stored with method `kw-quota`.

## 5. V1.5 — ticket columns for AI

In phpMyAdmin, database **users_db**, SQL tab, run `database/v1.5_openai_features.sql`, or paste:

```sql
ALTER TABLE `tickets`
  ADD COLUMN `ai_guidance` TEXT NULL DEFAULT NULL AFTER `priority`;

ALTER TABLE `tickets`
  ADD COLUMN `ai_method` VARCHAR(32) NULL DEFAULT NULL AFTER `ai_guidance`;

ALTER TABLE `tickets`
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `ai_method`;
```

Skip any line that says the column already exists.

- `ai_guidance` stores the low-priority troubleshooting text
- `ai_method` stores `openai`, `keyword`, or `kw-quota`
- `created_at` feeds the admin weekly chart

## 6. Start the AI service every time you demo

XAMPP Apache and MySQL must be running first.

Double-click:

`C:\xampp\htdocs\CP2_V1.6\ai\start_classifier.bat`

Leave that window open. Or, in PowerShell:

```bat
cd C:\xampp\htdocs\CP2_V1.6\ai
python classifier_app.py
```

Then open `http://127.0.0.1:5000/health`.

A good result looks like `"ok": true`. `"openai_configured": true` means the key was read. `"matplotlib": true` means the chart library imported. If the page cannot be reached, the black window was closed or the install step was skipped.

## 7. V1.6 — score, clocks, and profile columns

Run these in phpMyAdmin on **users_db**, in this order. Skip a statement if that column already exists.

Score columns (`database/v1.6_severity_matrix.sql`):

```sql
ALTER TABLE `tickets` ADD COLUMN `urgency` TINYINT NULL DEFAULT NULL AFTER `ai_method`;
ALTER TABLE `tickets` ADD COLUMN `impact_level` TINYINT NULL DEFAULT NULL AFTER `urgency`;
ALTER TABLE `tickets` ADD COLUMN `severity_score` INT NULL DEFAULT NULL AFTER `impact_level`;
```

Clocks (`database/v1.6_performance_times.sql`):

```sql
ALTER TABLE `tickets` ADD COLUMN `responded_at` DATETIME NULL DEFAULT NULL AFTER `created_at`;
ALTER TABLE `tickets` ADD COLUMN `resolved_at` DATETIME NULL DEFAULT NULL AFTER `responded_at`;
```

Profile (`database/v1.6_user_profile_columns.sql`):

```sql
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `users` ADD COLUMN `email_notify` TINYINT(1) NOT NULL DEFAULT 1 AFTER `phone`;
ALTER TABLE `users` ADD COLUMN `sms_notify` TINYINT(1) NOT NULL DEFAULT 0 AFTER `email_notify`;
ALTER TABLE `users` ADD COLUMN `preferred_language` VARCHAR(30) NOT NULL DEFAULT 'English' AFTER `sms_notify`;
```

Without `phone` and the preference columns, My Profile crashes. Without the score columns, submit cannot store urgency times impact. Without the clock columns, Performance cannot show response and resolution time.

## 8. What to click after setup

1. Sign in on `/CP2_V1.6/pages/login_signup.php`.
2. As a user, submit a small one-person problem. Expect **Low**, a score, and troubleshooting steps. No technician yet.
3. Submit a “server is down for the whole department” problem. Expect **Critical** and an automatic technician.
4. As admin, open Dashboard. Charts use Chart.js from the live database. Matplotlib is installed for the paper PNG export. The on-screen admin charts stay Chart.js.
5. Open Performance. Resolved tickets are grouped by category.
6. Open My Profile from the initials circle. Name, e-mail, phone, notification switches, and language should load. Save once.

## If something fails

| What you see | What to do |
|---|---|
| Login works on V1.3 but not on V1.6 | Use the `/CP2_V1.6/` address. The cookie does not carry over from V1.3. |
| `python` is not recognized | Reinstall Python 3.13 with **Add python.exe to PATH**, then open a new PowerShell window. |
| Browser says connection refused on port 5000 | Start `ai\start_classifier.bat` and leave the window open. |
| Health says `openai_configured: false` | `ai\.env` is missing, or the key line is still the placeholder. |
| Ticket saves but method is `kw-quota` | The API account has no credits. Add API credit at platform.openai.com, then restart the black Flask window. Tickets still file. |
| My Profile says unknown column `phone` | Run the V1.6 profile `ALTER` statements. |
| phpMyAdmin says duplicate column | That part is already done. Run the next statement. |
