# Review and commit boundaries

Developer A's seven commits remain unchanged. Developer B's account sabekunnaher-17 now has verified write access and a verified public commit email. The repository-local author is Sabekunnaher Antora <antorasabekunnaher@gmail.com>; global author settings are unchanged.

Developer B's reviewed increment is committed on feature/developer-b-appointments, based on 5cecfe5. A's branch is unchanged. Shared files were staged as feature-specific snapshots without overwriting the full working files. Each staged snapshot was checked before committing; PHP/MySQL runtime tests remain unrun. The original proposal below is historical; git log records the actual coherent commit boundaries.

The responsible real contributor must review and adopt each increment, verify their own repository-local author identity, run available checks, and create their own commits. Shared files such as routes, layout, styles, and tests need interactive hunk staging so later features are not swept into an earlier commit. Do not run git add . and commit the entire working tree.

The sequence below was the original proposal; the actual seven Developer A commits group tightly coupled changes coherently, as recorded in development-report.md. Remaining Developer B messages are proposals, not existing commits:

1. `chore: establish secure PHP MVC foundation` — environment, PDO, routing, exception handling, session/CSRF/escaping, base layout, setup.
2. `feat(auth): add patient registration and role-based sign-in` — validation, user model, authentication controller/views, secure logout, local admin provisioning.
3. `feat(profile): add patient profile and password management` — profile controller/view and upcoming count.
4. `feat(admin): add AJAX account activation controls` — account control view/controller and JS behavior.
5. `feat(specializations): add protected specialization CRUD` — admin CRUD and reference protection.
6. `feat(doctors): add linked doctor profiles and secure photo uploads` — doctor management/model/upload service, related views.
7. `feat(doctors): add weekday availability and appointment statistics` — availability controls, stats API/JS.
8. `test(auth): cover Developer A authentication and admin workflows` — relevant test hunks and acceptance documentation.
9. `feat(booking): add active doctor discovery and available slots` — Developer B branch based on reviewed Developer A commits.
10. `feat(booking): add transactional booking and patient cancellation` — creation, confirmations, grouped appointments, collision protection.
11. `feat(dashboard): add doctor daily and weekly schedules` — schedule grid and own-status actions.
12. `feat(appointments): add admin filters and shared status updates` — status service/API and cancellation audit.
13. `test(appointments): cover status permissions and concurrent booking` — booking/status/concurrency tests and final setup/API docs.

Adjust boundaries to keep every commit coherent and testable; do not create artificial commits or include unresolved references. Foundation/auth dependencies may require combining tightly coupled pieces. Create reviewable PRs for the actual committed feature branches; never force-push or push directly to main. Do not fabricate hashes, authors, pushes, or merges in reports.
