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

The database schema has not been imported or executed. Static contract checks and JS tests do not establish end-to-end correctness. Audit-file writes and database commits are separate resources; request logs may outlive failed transactions. Slot-duration changes need an agreed migration policy because the specified schema stores no duration. Existing unconfigured schedules display stored bookings without inventing slot times.
