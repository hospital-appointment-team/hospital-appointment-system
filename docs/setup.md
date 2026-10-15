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

## Run and verify

Serve only public/: php -S 127.0.0.1:8000 -t public public/router.php

See testing.md for available checks. PHP/MySQL runtime validation is still pending. Developer A implements authentication and doctor management; booking and appointment-dashboard source remains in a separate uncommitted working-tree increment for Developer B review. Git identity is repository-local and confirmed by the account owner.
