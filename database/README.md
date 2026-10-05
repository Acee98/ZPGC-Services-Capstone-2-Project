# Database migrations (V1.6)

Database name used by the app: **`zpgc_services_db`** (`logic/config.php` / `DB_NAME`).

## Recommended import order

1. **Base dump** — full schema + seed data from your team backup (not stored in git; keep dumps out of public zips).
2. Feature patches as needed (safe if columns already exist — check file comments):
   - `v1.2_messages.sql`
   - `v1.2_stage4.sql`
   - `v1.5_openai_features.sql` / `v1.6_new_features_team.sql`
   - `v1.6_ticket_archive.sql` (`archived_at`)
   - `v1.6_performance_times.sql` (`responded_at`, `resolved_at`)
   - `v1.6_performance_indexes.sql` (indexes for lists / Performance)
   - `v1.6_attachments_audit.sql`, `v1.6_severity_matrix.sql`, `v1.6_user_profile_columns.sql` as required by your dump age
3. Optional for Azure DB sessions: table `php_sessions` (created automatically when runtime DDL is allowed; see `logic/session_db.php`)

## Runtime DDL

If a column/table is missing, PHP may create it while `ZPGC_ALLOW_RUNTIME_DDL` is enabled (default). For IT-hardened environments:

1. Apply the SQL files above on a clean database.
2. Set App Setting / env: `ZPGC_ALLOW_RUNTIME_DDL=0`.

The app will then skip `CREATE`/`ALTER` bootstrap and expect the schema to already match V1.6.
