"""Live PHP/MySQL acceptance tests. Only launches against an explicitly named _test database."""
import http.cookiejar
import base64
import datetime
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import subprocess
import sys
import time
import unittest
from urllib import request, parse, error

ROOT = Path(__file__).resolve().parents[1]
PHP = shutil.which('php')
DATABASE = os.environ.get('TEST_DB_DATABASE', '')

class Client:
    def __init__(self, base):
        self.base = base
        self.opener = request.build_opener(request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def call(self, path, body=None, method=None, csrf=None, content_type=None):
        headers = {}
        if isinstance(body, dict):
            if csrf is not None:
                headers = {'Content-Type': 'application/json', 'X-CSRF-Token': csrf}
                body = json.dumps(body).encode()
            else:
                body = parse.urlencode(body).encode()
        if content_type:
            headers['Content-Type'] = content_type
        req = request.Request(self.base + path, data=body, method=method, headers=headers)
        try:
            response = self.opener.open(req, timeout=5)
        except error.HTTPError as exc:
            response = exc
        with response:
            return response.status, response.read().decode(), response.headers, response.url

    def token(self, path='/login'):
        status, body, _, _ = self.call(path)
        if status != 200:
            raise AssertionError(f'CSRF page returned {status}')
        return re.search(r'<meta name="csrf-token" content="([a-f0-9]+)">', body).group(1)

    def login(self, role, run):
        return self.call('/login', {'_csrf': self.token(), 'email': f'{role}-{run}@example.test', 'password': 'Http-test-only-839!'})

class HospitalHTTP(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        if not PHP or not re.fullmatch(r'[a-zA-Z0-9_]+_test', DATABASE):
            raise unittest.SkipTest('PHP on PATH and an isolated TEST_DB_DATABASE ending in _test are required')
        cls.env = os.environ.copy()
        cls.run = secrets.token_hex(8)
        cls.env.update(DB_DATABASE=DATABASE, HTTP_TEST_RUN=cls.run, APP_ENV='local', SESSION_SECURE='false', BOOKING_POLICY_CONFIRMED='false')
        cls.hidden = {'creationflags': subprocess.CREATE_NO_WINDOW} if os.name == 'nt' else {}
        fixture = subprocess.run([PHP, 'tests/http-fixtures.php', 'setup'], cwd=ROOT, env=cls.env, capture_output=True, text=True, **cls.hidden)
        if fixture.returncode:
            # Avoid printing raw PDO exceptions which can contain sensitive configuration.
            subprocess.run([PHP, 'tests/http-fixtures.php', 'cleanup'], cwd=ROOT, env=cls.env, capture_output=True, **cls.hidden)
            raise RuntimeError('HTTP fixture setup failed. Inspect the dedicated test database configuration.')
        cls.ids = json.loads(fixture.stdout)
        cls.addClassCleanup(lambda: subprocess.run([PHP, 'tests/http-fixtures.php', 'cleanup'], cwd=ROOT, env=cls.env, capture_output=True, **cls.hidden))
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        cls.base = f'http://127.0.0.1:{port}'
        cls.server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=cls.env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **cls.hidden)
        def stop():
            cls.server.terminate()
            try:
                cls.server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                cls.server.kill()
                cls.server.wait()
        cls.addClassCleanup(stop)
        for _ in range(50):
            try:
                if Client(cls.base).call('/')[0] == 200:
                    return
            except (OSError, error.URLError):
                pass
            time.sleep(0.1)
        raise RuntimeError('Local test server did not become ready')

    def test_registration_validation_duplicate_and_profile(self):
        client = Client(self.base)
        data = {'_csrf': client.token('/register'), 'name': 'New patient', 'email': f'new-{self.run}@example.test', 'password': 'Http-test-only-839!', 'dob': '1990-01-01', 'blood_group': 'O+', 'phone': '01234567890', 'role': 'admin'}
        invalid = dict(data, dob='2030-02-31')
        self.assertEqual(client.call('/register', invalid)[0], 422)
        result = client.call('/register', data)
        self.assertEqual(result[0], 200)
        self.assertTrue(result[3].endswith('/login'))
        data['_csrf'] = client.token('/register')
        self.assertEqual(client.call('/register', data)[0], 422)
        result = client.call('/login', {'_csrf': client.token(), 'email': data['email'], 'password': data['password']})
        self.assertEqual(result[0], 200)
        self.assertTrue(result[3].endswith('/patient'))
        other = Client(self.base)
        self.assertEqual(other.call('/login', {'_csrf': other.token(), 'email': data['email'], 'password': data['password']})[0], 200)
        token = client.token('/patient/profile')
        result = client.call('/patient/profile', {'_csrf': token, 'name': 'Updated name', 'dob': '1990-01-01', 'phone': '01234567890', 'current_password': 'wrong', 'new_password': 'Changed-test-839!'})
        self.assertEqual(result[0], 422)
        result = client.call('/patient/profile', {'_csrf': token, 'name': 'Updated name', 'dob': '1990-01-01', 'phone': '01234567890', 'current_password': data['password'], 'new_password': 'Changed-test-839!'})
        self.assertEqual(result[0], 200)
        self.assertIn('Updated name', result[1])
        result = other.call('/patient/profile', {'_csrf': other.token('/patient/profile'), 'name': 'Other session', 'dob': '1990-01-01', 'phone': '01234567890'})
        self.assertEqual(result[0], 200)
        result = other.call('/patient/profile', {'_csrf': other.token('/patient/profile'), 'name': 'Other session', 'dob': '1990-01-01', 'phone': '01234567890', 'current_password': data['password'], 'new_password': 'Should-not-replace-839!'})
        self.assertEqual(result[0], 422)
        client.call('/logout', {'_csrf': client.token('/patient/profile')})
        self.assertEqual(client.call('/login', {'_csrf': client.token(), 'email': data['email'], 'password': 'Changed-test-839!'})[0], 200)

    def test_role_csrf_inactive_and_json_contracts(self):
        patient = Client(self.base)
        self.assertEqual(patient.login('patient', self.run)[0], 200)
        self.assertIn('<strong>2</strong>', patient.call('/patient/profile')[1])
        self.assertEqual(patient.call('/admin/users')[0], 403)
        self.assertEqual(patient.call('/doctor/dashboard')[0], 403)
        self.assertEqual(patient.call('/api/users/toggle-active', {'user_id': self.ids['patient']}, method='POST', csrf=patient.token('/patient/profile'))[0], 403)
        self.assertEqual(patient.call('/api/users/toggle-active', {'user_id': self.ids['patient']}, method='POST', csrf='forged')[0], 419)
        admin = Client(self.base)
        self.assertEqual(admin.login('admin', self.run)[0], 200)
        token = admin.token('/admin')
        try:
            result = admin.call('/api/users/toggle-active', {'user_id': self.ids['patient']}, method='POST', csrf=token)
            self.assertFalse(json.loads(result[1])['is_active'])
            # Existing patient session must be invalidated on the next authenticated request.
            self.assertTrue(patient.call('/patient/profile')[3].endswith('/login'))
            result = patient.login('patient', self.run)
            self.assertIn('Your account has been deactivated. Contact admin.', result[1])
        finally:
            admin.call('/api/users/toggle-active', {'user_id': self.ids['patient']}, method='POST', csrf=token)
        result = admin.call('/api/doctors/stats')
        self.assertIn('application/json', result[2]['Content-Type'])
        self.assertTrue(any(int(item['id']) == self.ids['doctor_profile'] for item in json.loads(result[1])['doctors']))

    def test_doctor_management_upload_and_specialization_protection(self):
        admin = Client(self.base)
        admin.login('admin', self.run)
        token = admin.token('/admin')
        result = admin.call('/admin/specializations', {'_csrf': token, 'action': 'delete', 'id': self.ids['specialization']})
        self.assertEqual(result[0], 422)
        self.assertIn('Cannot delete', result[1])
        name = f'Unused {self.run}'
        self.assertEqual(admin.call('/admin/specializations', {'_csrf': token, 'action': 'create', 'name': name})[0], 200)
        self.assertEqual(admin.call('/admin/specializations', {'_csrf': token, 'action': 'create', 'name': name})[0], 422)
        def upload(photo):
            boundary = 'HospitalTestBoundary'
            fields = {'_csrf': token, 'name': 'HTTP Created doctor', 'email': f'created-{self.run}@example.test', 'password': 'Http-test-only-839!', 'specialization_id': str(self.ids['specialization']), 'bio': 'Test biography', 'consultation_fee': '400.00', 'available_days[]': 'Monday'}
            chunks = []
            for key, value in fields.items():
                chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="photo.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + photo + b'\r\n')
            chunks.append(f'--{boundary}--\r\n'.encode())
            return admin.call('/admin/doctors/create', b''.join(chunks), method='POST', content_type=f'multipart/form-data; boundary={boundary}')
        self.assertEqual(upload(b'not an image')[0], 422)
        png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8WQAAAAASUVORK5CYII=')
        result = upload(png)
        self.assertEqual(result[0], 200)
        match = re.search(r'<tr><td>HTTP Created doctor</td>.*?/admin/doctors/(\d+)/edit', result[1], re.S)
        self.assertIsNotNone(match)
        doctor_id = match.group(1)
        page = admin.call(f'/admin/doctors/{doctor_id}/edit')
        self.assertIn('Test biography', page[1])
        self.assertEqual(admin.call(f'/admin/doctors/{doctor_id}/deactivate', {'_csrf': token})[0], 200)
        patient = Client(self.base)
        patient.login('patient', self.run)
        self.assertIn('Inactive', admin.call('/admin/doctors')[1])

if __name__ == '__main__':
    unittest.main(verbosity=2)
