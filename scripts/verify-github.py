"""Read-only GitHub identity/permission check. Credentials stay in process memory."""
import argparse
import json
import os
import subprocess
import sys
from urllib import request, error

parser = argparse.ArgumentParser()
parser.add_argument('--account', required=True, help='Account selected from Git Credential Manager')
parser.add_argument('--expected-user', required=True)
parser.add_argument('--email', required=True, help='Only this supplied email is compared; email lists are never printed')
parser.add_argument('--email-attested', action='store_true', help='Owner explicitly confirmed this email; does not claim API verification')
args = parser.parse_args()
if any('\n' in value or '\r' in value for value in vars(args).values() if isinstance(value, str)):
    sys.exit('Invalid argument')
env = os.environ.copy()
env.update(GIT_TERMINAL_PROMPT='0', GCM_INTERACTIVE='never')
result = subprocess.run(['git', '-c', 'credential.interactive=never', 'credential', 'fill'],
    input=f'protocol=https\nhost=github.com\nusername={args.account}\n\n',
    capture_output=True, text=True, env=env, timeout=20)
fields = dict(line.split('=', 1) for line in result.stdout.splitlines() if '=' in line)
token = fields.get('password')
if result.returncode or not token:
    sys.exit('No usable stored credential was returned; no Git settings changed.')

def get(path):
    req = request.Request('https://api.github.com' + path, headers={
        'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json',
        'X-GitHub-Api-Version': '2022-11-28', 'User-Agent': 'hospital-repository-verification',
    })
    try:
        with request.urlopen(req, timeout=20) as response:
            return response.status, json.load(response)
    except error.HTTPError as exc:
        return exc.code, None  # Never print raw credential/API error bodies.
    except (error.URLError, TimeoutError):
        return 'unavailable', None

status, user = get('/user')
if status != 200:
    sys.exit(f'Authenticated account verification failed (HTTP {status}); no settings changed.')
login = user.get('login')
email_status, emails = get('/user/emails')
email_verified = isinstance(emails, list) and any(item.get('email', '').casefold() == args.email.casefold() and item.get('verified') is True for item in emails)
public_email_status, public_emails = get('/user/public_emails')
public_email_verified = isinstance(public_emails, list) and any(item.get('email', '').casefold() == args.email.casefold() and item.get('verified') is True for item in public_emails)
repo_status, repo = get('/repos/hospital-appointment-team/hospital-appointment-system')
permissions = repo.get('permissions', {}) if isinstance(repo, dict) else {}
report = {
    'authenticated_login': login,
    'matches_expected_username': isinstance(login, str) and login.casefold() == args.expected_user.casefold(),
    'email_api_status': email_status,
    'supplied_email_verified_on_account': email_verified,
    'public_email_api_status': public_email_status,
    'supplied_public_email_verified': public_email_verified,
    'supplied_email_owner_attested': args.email_attested,
    'repository_api_status': repo_status,
    'repository_push_permission': permissions.get('push'),
    'repository_pull_permission': permissions.get('pull'),
    'repository_admin_permission': permissions.get('admin'),
}
print(json.dumps(report, indent=2))
sys.exit(0 if report['matches_expected_username'] and (email_verified or public_email_verified or args.email_attested) and permissions.get('push') is True else 1)
