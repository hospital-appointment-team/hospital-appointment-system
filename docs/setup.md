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

## Run and verify

Serve only public/: php -S 127.0.0.1:8000 -t public public/router.php

See testing.md for available checks. PHP/MySQL runtime validation is still pending. Developer A implements authentication and doctor management; booking and appointment-dashboard source remains in a separate uncommitted working-tree increment for Developer B review. Git identity is repository-local and confirmed by the account owner.
