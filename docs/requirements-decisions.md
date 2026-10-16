# Requirements and pending decisions

Source: the supplied P3.pdf, six pages. The user's explicit two-developer allocation takes precedence over the PDF's four-student code-sharing restriction; the team should resolve that submission difference with its instructor.

Preserve the four required tables and their columns. No existing database has been inspected or changed. Roles: patient, doctor, admin. Appointment statuses: Pending, Confirmed, Completed, Cancelled, No-Show. Availability stays a comma-separated list of Monday-Friday weekday names.

Clinic opening/closing times and duration remain unspecified. The user explicitly required booking to remain unavailable until valid values are configured later without source changes. `.env.example` therefore leaves these values blank and disables booking. Admin/doctor management, profiles, and existing appointment dashboards remain usable.

Documented configurable proposal: seven dates including today (set BOOKING_INCLUDE_TODAY=false to start tomorrow). Pending, Confirmed, Completed, and No-Show reserve slots; Cancelled frees them. These policies were proposed earlier; they are not explicit PDF wording. Confirm the hospital's policy before enabling booking. Slots fit within configured hours, follow doctor weekdays, and are checked for interval overlap as well as duplicate starts.

The user approved a protected audit file for cancellation reasons. `storage/audit/cancellations.jsonl` stores cancellation requests outside the public root; no schema column is added, and the patient reason remains intact. See setup.md for failure semantics and retention.

The overview mentions doctors managing schedules, but the detailed availability requirement assigns editing to admins. Implement the detailed admin requirement unless clarified.

Initial admin provisioning uses `scripts/create-admin.php` with password input over stdin. There are no shipped production credentials. Test-only passwords occur only in isolated test fixtures.

Doctor transition policy: own Pending/Confirmed appointments may become Completed/No-Show; doctors cannot overwrite terminal states. Admins can set any of the five specified statuses, subject to collision checks when reserving a slot. Patients can cancel only their own Pending appointments. Patient POST cancellation and staff PUT status updates call the same service, preserving both PDF endpoints.

Review clarification: existing reservations can change non-Cancelled status while new booking is disabled. Restoring any Cancelled appointment to a reserving status requires configured duration and a conflict check. Restoring to Pending/Confirmed also requires an active doctor and eligible future slot. No implicit duration is assumed.
