# Verification and acceptance

Automated verification completed on 2026-10-16 using Windows 11, PHP 8.4.26 NTS x64 and MySQL 8.4.11. This records executed local tests, not production certification or manual UI review.

| Executed command/check | Result |
|---|---|
| PHP lint on all project PHP files | 61 passed |
| php tests/run.php | 86 checks passed |
| php tests/password-policy.php | 10 focused checks passed (also included in the unit suite) |
| php tests/integration.php | 35 checks passed, including independent-process booking/status races |
| python tests/http_smoke.py --failfast | 8 tests passed |
| node --check public/assets/js/app.js | Passed |
| node --test tests/frontend.test.cjs | 11 tests passed |
| python tests/static_check.py | 422 static checks passed |

No failures, skipped suites or unrun required suites occurred in this verification. Each database suite's exact approved _test database and restricted account were checked before fixture writes. Both databases had zero rows in all four tables after cleanup. Test servers and uploads were cleaned up; protected cancellation-request audit entries were retained as documented. No database was dropped or recreated. Credentials and runtime files stayed outside Git.

The former race fixture overlapped a 09:45-10:15 reservation; its coordinated setup/worker/assertion time is now 11:30. HTTP run_id preserves unittest.TestCase.run(). Login uses the central explicit bcrypt cost-12 policy, with a matching guarded dummy hash. Focused tests compare algorithm/options; the HTTP regression compares normalized unknown-email and wrong-password response bodies and HTTP 422 statuses. Diagnostic averages were 236.19 ms and 236.75 ms for three local verification samples per path, respectively; no timing threshold is used as a pass criterion.

Renderer remediation: `php tests/view-rendering.php` exercises actual profile, doctor, validation-recovery, and specialization-edit HTML without a database, including escaping and reserved renderer locals. It passed as part of `php tests/run.php`.

Available dependency-free checks:

- `node --check public/assets/js/app.js`
- `node --test tests/frontend.test.cjs`: JSON/CSRF transport, network/non-JSON errors, safe feedback, account updates/failures, shared PUT action, stale slot/filter response ordering, safely rendered doctor names, and encoded filters. Eleven tests passed in the full reviewed snapshot.
- `python tests/static_check.py`: MVC paths, route/action/view references, strict PHP declarations, basic query-pattern and schema checks, whitespace. This cannot parse PHP or verify SQL correctness.

Reproduce verification using setup.md. Keep integration and HTTP fixtures in separate dedicated `_test` databases and verify their connection targets before any writes.

## Acceptance coverage matrix

This matrix lists intended behaviors; it is not a claim that every cell was exercised manually or exhaustively by the automated suites.

| Area | Required checks |
|---|---|
| Registration | All fields invalid/empty; duplicate email; escaped name; password hash in database; patient role forced despite supplied role |
| Login/session | Three role redirects; wrong password; inactive message; session ID rotates; logout invalidates identity; deactivated existing session loses access |
| Authorization/CSRF | Anonymous page redirect/API 401; cross-role 403; cross-patient/doctor ownership; absent/forged CSRF; malformed JSON; invalid IDs |
| Profile | Name/phone/DOB validation; wrong/current password; password replacement; upcoming count includes only Pending/Confirmed dated today or later |
| Accounts | Admin AJAX toggle success/error; self-deactivation blocked; inactive status reflected |
| Specializations | Create/edit/duplicate/delete; assigned specialization protected, including concurrent doctor creation |
| Doctors | Linked users/doctor transaction; unique email; prefilled edit; blank password preserves hash; soft deletion; zero appointment stats included |
| Uploads | JPEG/PNG at limit; >2MB; fake MIME; malformed upload; random names; forbidden script execution; failure cleanup; current photo retained on no upload |
| Availability | Monday–Friday only; comma-separated canonical order; empty availability; no weekend booking |
| Discovery/slots | Active-only filter without reload; empty/error/loading states; stale responses; seven dates; unconfigured policy blocked; within-hours and future slots only |
| Booking | Slot off grid/outside hours/unavailable weekday; overlapping existing interval; two concurrent requests produce one reservation; Pending confirmation ID; cancelled slot can be rebooked |
| Patient list | Own records only; all status groups; own Pending cancellation only; badge/group updates; failed request preserves state |
| Doctor view | Today sorted by time; current Monday–Friday grid; expanded details; own Completed/No-Show; terminal state overwrite blocked |
| Admin appointments | Doctor/date/status filters reload; Pending confirm; cancel any status with reason; audit unavailable fails safely; patient reason preserved; cancelled reactivation cannot double-book |
| Interface | Mobile/tablet/desktop; keyboard focus and labels; readable status text; horizontal schedule/table scrolling; no PHP warnings; AJAX errors visible |

## Remaining verification limits

The schema and HTTP journeys have executed successfully in the dedicated local environment. Automated tests do not establish visual layout, browser accessibility/usability, deployment-specific proxy and shared-storage behavior, every possible concurrency ordering, or production security. Imported legacy password hashes with other algorithms/work factors need a separate migration decision; no existing production hashes were inspected or modified. Audit-file writes and database commits are separate resources; request logs may outlive failed transactions. Slot-duration changes are blocked by the approved protected baseline; legacy bookings without a baseline require deliberate recovery. Existing unconfigured schedules display stored bookings without inventing slot times.

## Review remediation coverage

`tests/run.php` includes actual rendered edit/profile forms, duration freeze and adjacent-interval rules, account/IP limits, backoff/expiry, corrupt state and concurrent success-reservation handling. It requires PHP but no database.

`tests/integration.php` requires a NEW empty MySQL 8 database ending in `_test`, the supplied schema, TEST_DB_FRESH_CONFIRM=true, and PHP with PDO MySQL/mbstring and proc_open enabled. It covers duration drift, adjacent slots, two-doctor ownership, overlap, simultaneous booking, new-booking versus cancelled-appointment reactivation, and concurrent cancellation/confirmation. Workers use independent database connections and a start gate; repeat races on the target MySQL/server environment before deployment.

`python tests/http_smoke.py` requires a DIFFERENT new empty `_test` database and PHP on PATH. It starts loopback servers with explicit fixture-only hours/duration, temporary private state and auth storage. Tests exercise successful booking POST/confirmation, duplicate conflict, patient/doctor ownership, enabled and disabled booking, rendered form values and specialization edit action/ID, PNG uploads at 2 MB and above the limit, and unknown-account throttling. The fixture server sets upload_max_filesize=3M and post_max_size=8M so application size validation is reached. These are HTTP tests, not live browser or visual verification. Missing prerequisites skip the suite with zero tests executed.

No suite provisions, drops or migrates a database. By default provision unique empty test databases and import database/schema.sql following setup.md. Reuse of an existing dedicated test database requires explicit authorization and verification that all four tables are empty; the recorded runs used that authorization. Never use a shared or production database. No local credentials belong in Git. No manual browser verification occurred or is claimed.
