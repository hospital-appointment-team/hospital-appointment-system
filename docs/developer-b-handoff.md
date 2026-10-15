# Developer B: independent identity and review workflow

Developer A's seven source commits are preserved at `5cecfe5` on `feature/developer-a-foundation`. Draft PR #1 targets main and is not merged. The patch checksum, base, and reverse-apply check were verified: its changes were already present, so it was not reapplied. Developer B now works on `feature/developer-b-appointments` with authenticated account `sabekunnaher-17` and actual author Sabekunnaher Antora <antorasabekunnaher@gmail.com>. GitHub verified write permission and the commit email's verified-public status. Only repository-local identity changed; global settings and A's commits were preserved.

The following steps document reproduction in another checkout. Do not reapply the handoff in the current checkout. Review fixes removed a guessed-duration fallback, rechecked active caller roles under lock, and expanded tests. The original patch and checksum manifest remain unchanged outside the repository.

## Work in your own checkout

Use your own workstation/account and an unused local directory. The original mixed checkout must remain intact.

```powershell
git clone https://github.com/hospital-appointment-team/hospital-appointment-system.git hospital-appointment-system-b
cd hospital-appointment-system-b
git fetch origin
git switch -c feature/developer-b origin/feature/developer-a-foundation
git rev-parse HEAD
```

The handoff patch is based on `5cecfe5` (verify the full base hash in the handoff manifest). If the A branch advances, apply at the recorded base in your new branch, review conflicts, and integrate later without discarding either developer's changes. Never reset or clean another developer's checkout.

## Authenticate yourself and configure your own identity

Run `git credential-manager github login --browser --username YOUR_ACTUAL_USERNAME`, substituting your real GitHub username. Complete the official browser flow. Never paste credentials into chat or add them to remote URLs. Having Developer A's account available in the same credential manager does not authenticate yours.

Run the committed verifier with your own username and email:

```text
python scripts/verify-github.py --account YOUR_ACTUAL_USERNAME --expected-user YOUR_ACTUAL_USERNAME --email YOUR_OWN_EMAIL
```

It checks the selected credential against GitHub's /user and repository-permissions APIs without printing credentials. If the email endpoint is unavailable, explicitly confirm ownership of your email before adding `--email-attested`; this flag records personal attestation, not API verification. A failed account or write-permission check must be resolved before pushing.

Only after your identity is confirmed, set your own repository-local author settings:

```text
git config --local user.name "YOUR REAL NAME"
git config --local user.email "YOUR OWN EMAIL"
git var GIT_AUTHOR_IDENT
```

These placeholders must be replaced by the responsible real developer in a reproduction checkout. Developer B has now been verified and configured locally in the original checkout as recorded above. Never assign Developer A's identity to Developer B's new commits.

## Apply, review, test, and commit your increment

Copy the generated developer-b patch and JSON manifest from the handoff directory. Compare its SHA-256 with the manifest, then run in your clean B checkout:

```text
git apply --check PATH_TO_HANDOFF_PATCH
git apply PATH_TO_HANDOFF_PATCH
git status --short
node --check public/assets/js/app.js
node --test tests/frontend.test.cjs
python tests/static_check.py
```

The patch contains content changes only, with no fabricated author metadata or commits. Review and adopt the code yourself, implement any corrections you identify, and take responsibility for the changes you commit. Inspect shared route, navigation, JavaScript, CSS, and test diffs carefully; they extend the real committed A implementation.

Once approved PHP/MySQL tools are available, run PHP lint, `php tests/run.php`, isolated `_test` database integration/concurrency tests, and the HTTP suite described in setup.md. Until then, explicitly record these as unrun. Do not enable booking until clinic hours/duration are configured. Cancellation reasons use the approved private audit file.

Create coherent small commits rather than one patch-sized commit. Suggested groups: doctor discovery/configured availability; transactional booking and patient cancellation with the shared status service; daily/weekly doctor schedule and admin status/filter UI; integration tests and documentation. Use `git add -p` for shared-file hunks, inspect `git diff --cached`, verify your author, and test each staged snapshot. `git add -N` can make new files available for interactive hunk staging. Keep each commit's referenced classes/routes present and usable; adjust groups when tightly coupled code must stay together.

After account/write checks and commit review succeed, push your own branch using your selected account:

```text
git -c credential.username=YOUR_ACTUAL_USERNAME push -u origin feature/developer-b
```

The latest explicit user instruction requires a draft PR targeting `main`, titled `Developer B: Appointment Booking and Dashboard`. It inherits A's existing commits while PR #1 remains unmerged; disclose that dependency and preserve the actual authors. Do not merge either PR. Complete runtime validation, review, and end-to-end acceptance before integration. Do not push directly to main or force-push shared branches.
