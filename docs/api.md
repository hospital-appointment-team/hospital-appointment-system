# HTTP and API contracts

All mutations require a session-bound CSRF token, supplied as `_csrf` in a form or `X-CSRF-Token` for JSON. APIs use same-origin session cookies and return `Content-Type: application/json`. Invalid/expired CSRF returns 419. Unauthenticated API access returns 401; forbidden roles/ownership return 403. Pages redirect unauthenticated users to `/login`.

Errors: `{"ok":false,"message":"..."}` with 400 malformed JSON, 404 missing resource, 409 slot collision/unavailable doctor, 422 invalid fields/status, 503 unconfigured booking/audit storage failure, or generic 500 without internal details. Database deadlocks/errors roll back; no automatic mutation retry is assumed.

| Method/path | Role | Input | Success |
|---|---|---|---|
| POST /api/users/toggle-active | admin | `{user_id}` | `{ok:true,is_active:boolean}` |
| GET /api/doctors/stats | admin | none | `{ok:true,doctors:[{id,appointment_count}]}` |
| GET /api/doctors | patient | optional specialization_id query | `{ok:true,doctors:[{id,name,specialization,consultation_fee,photo_path}]}` |
| GET /api/doctors/{id}/slots | patient | date=YYYY-MM-DD | JSON array such as `["09:00","09:30"]`; example only, no default hours |
| POST /api/appointments/cancel | patient | `{appointment_id}` | `{ok:true,new_status:"Cancelled"}` |
| PUT /api/appointments/{id} | doctor/admin | `{status,reason?}` | `{ok:true,new_status:"Completed"}` (requested valid status) |

IDs/counts returned by PDO may be JSON strings; clients normalize IDs before constructing URLs. Monetary values are decimal strings. Slots exclude elapsed times, unavailable weekdays, and all overlapping non-Cancelled reservations. An unavailable eligible-day booking policy returns 503; a valid unavailable weekday returns an empty array.

## Page and form routes

GET `/`, `/register`, `/login`; POST `/register`, `/login`, `/logout`.

Patient: GET `/patient` (also `/doctors`), `/doctors/{id}`, `/patient/profile`, `/patient/appointments`, `/patient/appointments/{id}/confirmation`; POST `/patient/profile`, `/appointments` (doctor_id, appointment_date, appointment_time, reason).

Admin: GET `/admin`, `/admin/users`, `/admin/specializations`, `/admin/doctors`, `/admin/doctors/create`, `/admin/doctors/{id}/edit`, `/admin/appointments`; POST specialization CRUD to `/admin/specializations` with action=create/edit/delete; POST doctor multipart forms to create/edit URLs; POST `/admin/doctors/{id}/deactivate`.

Admin appointment filters use doctor_id, date, and status GET parameters and a page reload. Status actions then use the shared PUT endpoint and update badges in place. Cancellation requires a 3–4000-character reason retained in the private audit file.

Doctor: GET `/doctor/dashboard`; own appointments today sorted by time and the current Monday–Friday grid. Details expand with native accessible disclosure controls.
