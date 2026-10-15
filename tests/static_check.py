"""Repository contract checks only; not a PHP parser or runtime substitute."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
failures = []
checks = 0

def check(condition, message):
    global checks
    checks += 1
    if not condition:
        failures.append(message)

for folder in ('controllers', 'models', 'views', 'config'):
    check((ROOT / folder).is_dir(), f'Missing MVC directory: {folder}')

for file in ROOT.rglob('*'):
    if not file.is_file() or any(p in ('.git', 'node_modules', 'vendor', 'storage', '__pycache__') for p in file.parts):
        continue
    if file.suffix not in ('.php', '.sql', '.js', '.cjs', '.css', '.md', '.py', '.example') and file.name != '.gitignore':
        continue
    text = file.read_text(encoding='utf-8')
    check(not any(line.rstrip() != line for line in text.splitlines()), f'Trailing whitespace: {file.relative_to(ROOT)}')
    check(not re.search(r'(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{50,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)', text), f'Possible credential/private key: {file.relative_to(ROOT)}')
    if file.suffix == '.php' and 'views' not in file.relative_to(ROOT).parts:
        check(text.startswith('<?php'), f'PHP opening tag: {file}')
        check('declare(strict_types=1);' in text, f'Strict types: {file}')
        for view in re.findall(r"\$this->view\('([^']+)'", text):
            check((ROOT / 'views' / (view + '.php')).is_file(), f'Missing view {view}')
        check(not re.search(r'->(?:exec|query)\s*\(', text.replace('self::query', '').replace('Model::query', '')), f'Raw PDO query: {file}')

routes = (ROOT / 'config/routes.php').read_text(encoding='utf-8')
seen = set()
for method, route, controller, action in re.findall(r"'(GET|POST|PUT|DELETE) ([^']+)' => \[Hospital\\Controllers\\(\w+)::class, '(\w+)'\]", routes):
    check((method, route) not in seen, f'Duplicate route {method} {route}')
    seen.add((method, route))
    file = ROOT / 'controllers' / (controller + '.php')
    check(file.is_file(), f'Missing controller {controller}')
    check(f'function {action}(' in file.read_text(encoding='utf-8'), f'Missing action {controller}::{action}')

front = (ROOT / 'public/index.php').read_text(encoding='utf-8')
check("['POST', 'PUT', 'PATCH', 'DELETE']" in front and 'Request::csrf()' in front, 'Mutation CSRF gate missing')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
check(set(re.findall(r'CREATE TABLE (\w+)', schema)) == {'users','specializations','doctors','appointments'}, 'Unexpected schema tables')
check(not re.search(r'\b(?:ALTER|DROP)\s+TABLE\b', schema, re.I), 'Destructive schema operation')
check(schema.count('ENGINE=InnoDB') == 4, 'InnoDB is required for locking')
expected = {
    'users': {'id','name','email','password_hash','role','dob','blood_group','phone','is_active','created_at'},
    'specializations': {'id','name'},
    'doctors': {'id','user_id','specialization_id','bio','consultation_fee','photo_path','available_days','created_at'},
    'appointments': {'id','patient_id','doctor_id','appointment_date','appointment_time','reason','status','created_at'},
}
for table, columns in expected.items():
    block = re.search(r'CREATE TABLE ' + table + r' \((.*?)\) ENGINE', schema, re.S).group(1)
    actual = set(re.findall(r'^    ([a-z_]+) ', block, re.M))
    check(actual == columns, f'Required columns changed in {table}')
check("ENUM('patient','doctor','admin')" in schema, 'Exact role values preserved')
check("ENUM('Pending','Confirmed','Completed','Cancelled','No-Show')" in schema, 'Exact status values preserved')
for failure in failures:
    print('FAIL:', failure)
print(f'{checks} static contract checks; {len(failures)} failures (no PHP execution)')
sys.exit(bool(failures))
