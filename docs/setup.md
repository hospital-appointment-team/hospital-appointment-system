# Development setup

Requires PHP 8.2+ (PDO MySQL, fileinfo, mbstring, sessions) and MySQL 8+; no runtime is installed by this repository. Copy .env.example to .env and configure local credentials. Import database/schema.sql only into a new empty database. Serve only public/.

Run: php -S 127.0.0.1:8000 -t public public/router.php

Available check: python tests/static_check.py. PHP checks: php tests/run.php and php -l per PHP file. PHP/MySQL are unavailable in the inspected environment; runtime checks have NOT run. Never commit .env or credentials.
