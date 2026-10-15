# Local development

## Prerequisites

- PHP 8.2 or newer with PDO MySQL, fileinfo, mbstring, and sessions.
- MySQL 8.0 or newer using InnoDB for transactional booking locks.
- Git. Composer is not required. Node and Python run the dependency-free frontend/static checks.

Use the installation process approved for your workstation. Never paste credentials into chat.

## Configuration

1. Copy `.env.example` to `.env` locally. Do not commit `.env`.
2. Supply a local database username/password in `.env`; use a least-privilege account.
3. Values are literal text after `=`; do not add shell-style quotes or inline comments. Existing process environment values take precedence.
4. `APP_TIMEZONE` must match the hospital's agreed operating timezone. Asia/Dhaka is the initial development setting, not an established business rule.
5. `SESSION_SECURE=false` is permitted only with `APP_ENV=local` for local HTTP development. Use HTTPS and `SESSION_SECURE=true` outside local development.

## Database and initial admin

Create a new empty MySQL database using utf8mb4, then import `database/schema.sql` with your approved database client. The SQL creates only the four specified tables; it does not drop or alter tables. Stop if a database already contains shared tables: compare its DDL before importing anything. Do not use a production account with schema privileges for normal requests.

Configure database credentials in `.env`. Provision the first administrator with:

```text
php scripts/create-admin.php "Real Admin Name" "admin@example.invalid" < protected-password-file
```

The redirection example uses shell stdin redirection (for example, cmd.exe). PowerShell does not support `<` in the same way. Use a local protected file or your approved secret manager to supply stdin without putting the password in arguments, chat, or shell history. Delete any temporary password file afterwards. No default admin password is shipped. Doctor accounts are created through the admin panel; patients register themselves.

## Booking configuration

Booking is intentionally unavailable until all of these values are configured in `.env` or the service environment:

- `CLINIC_OPEN`: `HH:MM`, 24-hour format.
- `CLINIC_CLOSE`: `HH:MM`, later than opening on the same day.
- `SLOT_MINUTES`: positive integer minutes, with at least one full slot fitting within the same-day clinic hours.
- `BOOKING_POLICY_CONFIRMED=true` once the hospital approves its policy.

No actual clinic hours or duration are assumed. Only full slots that fit before closing are generated. The next seven calendar dates include today by default; `BOOKING_INCLUDE_TODAY=false` starts tomorrow. Only dates matching the doctor's comma-separated Monday–Friday availability are enabled. Elapsed times are excluded.

All statuses except `Cancelled` reserve their appointment interval. Cancelled slots may be rebooked. Booking and status changes lock the doctor row, then revalidate conflicts with locking reads inside an InnoDB transaction. This protects requests using this application; direct database writers must follow the same protocol.

The required schema has no per-appointment duration. Overlap checks use the current configured duration for existing appointments too. Do not change duration while existing bookings need a different duration; agree a migration strategy first. Overnight hours, per-doctor durations, and schedule exceptions are not specified and are not assumed.

Availability edits affect future booking options; they preserve existing appointments. Admin reactivation of a cancelled Pending/Confirmed reservation must fit current availability and must not conflict.

Any Cancelled-to-reserving transition requires valid configured duration and a conflict check; no duration is guessed. Existing reservations may be confirmed, completed, or marked No-Show while new booking is disabled. Status mutations recheck the caller's active role under a database lock.

Duration is now frozen by a protected, durable per-database baseline before the first insert. Changed/missing legacy/corrupt baselines fail closed. Configure shared STATE_STORAGE_DIR for multiple hosts and back it up with the database. See [duration-policy.md](duration-policy.md) for locking, recovery, and deliberately conservative failure semantics.

## Cancellation audit

Admin cancellations require a reason. The approved file approach saves it under `storage/audit/cancellations.jsonl`, outside `public/` and excluded from Git. The patient visit reason is preserved. Give only the application service account and authorized administrators access (use NTFS ACLs on Windows). Back up and retain the file according to the hospital's approved policy.

An event records an admin cancellation **request**, its actor, database, appointment ID, timestamp, and reason before updating the database. Audit write failure rolls back the database update. A later transaction failure or process crash can leave a request without a committed cancellation; consult the database for actual status. This file is not an atomic database audit trail. Never expose it through a web route. Test cancellations are labelled with the test database name and remain in the audit file as request evidence.

## Run locally

From the repository root:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

Open http://127.0.0.1:8000. The PHP built-in server is for development only.

For deployment, use `public/` as the document root and route application requests to `public/index.php`. Never serve the repository root. Apache rewrite and upload-protection `.htaccess` files are supplied; enable the required overrides or apply equivalent server configuration. For Nginx/IIS, configure a front-controller rewrite, allow PHP execution only for `index.php`, disable directory listing, and serve uploads strictly as static images. The PHP built-in server uses `public/router.php` for development. Configure `upload_max_filesize` to at least 2M and `post_max_size` above 2M to allow form overhead. The application validates actual MIME, image dimensions, upload origin, and size; filenames are generated randomly.

## Verification

```powershell
php tests/run.php
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check public/assets/js/app.js
node --test tests/frontend.test.cjs
python tests/static_check.py
```

Manual smoke checks: `/` returns the portal; an unknown URL returns 404; `/.env` and `/config/database.php` must not expose file contents; inspect response security headers. Repeat checks at a narrow mobile viewport and with keyboard navigation. See `docs/testing.md` for the role-based acceptance matrix.

For database tests, create an isolated database whose name ends in `_test`, import the schema, and set `TEST_DB_DATABASE` in the process environment. The normal `.env` connection credentials must have access to that database. Never point tests at shared data.

```powershell
$env:TEST_DB_DATABASE = 'hospital_integration_unique_run_test'
$env:TEST_DB_FRESH_CONFIRM = 'true'
php tests/integration.php
# Provision ANOTHER new empty database and import schema.sql before the HTTP suite.
$env:TEST_DB_DATABASE = 'hospital_http_unique_run_test'
python tests/http_smoke.py
```

Provision a separate newly created empty database and import the schema before EACH suite; use a unique _test name for each run. TEST_DB_FRESH_CONFIRM is your explicit confirmation of that setup. Both suites refuse nonempty tables; neither creates, drops, overwrites, or migrates databases. Private state is isolated in temporary test directories. They create tagged fixtures and clean only those records; a crash can leave fixtures, so provision another new test database rather than reusing it. The integration suite races independent PHP workers. The HTTP suite uses a loopback server. The static checker is not a PHP parser or runtime proof.

## Contributor workflow

The responsible human developer must review changes and use their own legitimate repository-local Git identity. A's seven commits and draft PR #1 are preserved. B uses verified account sabekunnaher-17 on feature/developer-b-appointments; the public-email API verified the configured commit email. Global settings are unchanged. See developer-b-handoff.md. Git authentication and author identity are separate; never embed tokens in remote URLs. Verify the selected account and write permission before pushing. Use reviewed PRs, never a direct push to main.

Developer A owns authentication and doctor management. Developer B owns booking and dashboards. The latest user instruction permits continuing local coding without commits while identity is unconfirmed. Never assign unreviewed agent-produced work to a real developer or switch identities to manufacture contributions. Follow `docs/commit-plan.md` for small review/commit boundaries.

Git Credential Manager is available in the inspected environment. To connect an account through its supported browser flow, run locally:

```powershell
git credential-manager github login --browser
```

Complete sign-in in the official browser flow; do not paste tokens into chat. Before a push, verify the authenticated account and the repository's effective write permission using GitHub's authenticated account/repository APIs without printing credentials. A public clone or a configured Git author does not prove write permission. Only push a reviewed feature branch after the responsible contributor identity and permission have been verified.
