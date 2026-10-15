# Developer A verification

Node syntax and seven frontend tests pass; static contracts pass. PHP lint, PHP unit tests, live HTTP/database tests, uploads and browser layout remain unrun because PHP/MySQL are unavailable.

Run node --test tests/frontend.test.cjs and python tests/static_check.py. Once runtimes are approved, lint all PHP, run php tests/run.php, provision an isolated TEST_DB_DATABASE ending in _test with the shared schema, and run python tests/http_smoke.py. The HTTP suite covers registration, cross-session profile password preservation, authorization, CSRF, inactive sessions, specialization deletion protection, and doctor upload/deactivation.
