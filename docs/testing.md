# Verification and acceptance

Runtime dependencies are absent in the inspected environment. PHP syntax checks, PHP unit/service tests, MySQL integration/concurrency tests, HTTP acceptance, and live browser/layout verification are NOT reported as passed. See the final development report for actual checks run.

Renderer remediation: `php tests/view-rendering.php` exercises actual profile, doctor, validation-recovery, and specialization-edit HTML without a database, including escaping and reserved renderer locals. It is also included in `php tests/run.php`. These PHP regression tests are authored but unrun while PHP is unavailable.

Available dependency-free checks:

- `node --check public/assets/js/app.js`
- `node --test tests/frontend.test.cjs`: JSON/CSRF transport, network/non-JSON errors, safe feedback, account updates/failures, shared PUT action, stale slot/filter response ordering, safely rendered doctor names, and encoded filters. Eleven tests passed in the full reviewed snapshot.
- `python tests/static_check.py`: MVC paths, route/action/view references, strict PHP declarations, basic query-pattern and schema checks, whitespace. This cannot parse PHP or verify SQL correctness.

Once approved PHP/MySQL tools are available, run lint, `php tests/run.php`, `php tests/integration.php`, and `python tests/http_smoke.py` as described in setup.md. The live suites require a separately provisioned `_test` database. They are authored but remain unrun here.

## Manual acceptance matrix

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

The database schema has not been imported or executed. Static contract checks and JS tests do not establish end-to-end correctness. Audit-file writes and database commits are separate resources; request logs may outlive failed transactions. Slot-duration changes are blocked by the approved protected baseline; legacy bookings without a baseline require deliberate recovery. Existing unconfigured schedules display stored bookings without inventing slot times.

## Review remediation coverage (authored, runtime execution pending)

`tests/run.php` includes actual rendered edit/profile forms, duration freeze and adjacent-interval rules, account/IP limits, backoff/expiry, corrupt state and concurrent success-reservation handling. It requires PHP but no database.

`tests/integration.php` requires a NEW empty MySQL 8 database ending in `_test`, the supplied schema, TEST_DB_FRESH_CONFIRM=true, and PHP with PDO MySQL/mbstring and proc_open enabled. It covers duration drift, adjacent slots, two-doctor ownership, overlap, simultaneous booking, new-booking versus cancelled-appointment reactivation, and concurrent cancellation/confirmation. Workers use independent database connections and a start gate; repeat races on the target MySQL/server environment before deployment.

`python tests/http_smoke.py` requires a DIFFERENT new empty `_test` database and PHP on PATH. It starts loopback servers with explicit fixture-only hours/duration, temporary private state and auth storage. Tests exercise successful booking POST/confirmation, duplicate conflict, patient/doctor ownership, enabled and disabled booking, rendered form values and specialization edit action/ID, PNG uploads at 2 MB and above the limit, and unknown-account throttling. The fixture server sets upload_max_filesize=3M and post_max_size=8M so application size validation is reached. These are HTTP tests, not live browser or visual verification. Missing prerequisites skip the suite with zero tests executed.

No suite provisions, drops or migrates a database. Have an authorized operator create a unique empty database and import database/schema.sql before each suite, following setup.md; never reuse an existing database. No local credentials belong in Git. Real mobile/desktop interaction, rendering, proxy IP behavior, shared-storage locking and concurrent requests still need verification in an approved environment.
