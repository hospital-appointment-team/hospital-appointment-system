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
import tempfile
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
        if not PHP or not re.fullmatch(r'[a-zA-Z0-9_]+_test', DATABASE) or os.environ.get('TEST_DB_FRESH_CONFIRM') != 'true':
            raise unittest.SkipTest('PHP and a NEW empty _test database with TEST_DB_FRESH_CONFIRM=true are required')
        cls.private_state = tempfile.TemporaryDirectory(prefix='hospital-http-state-')
        cls.addClassCleanup(cls.private_state.cleanup)
        cls.env = os.environ.copy()
        cls.auth_storage = tempfile.TemporaryDirectory(prefix='hospital-http-auth-')
        cls.addClassCleanup(cls.auth_storage.cleanup)
        cls.env['AUTH_THROTTLE_DIR'] = cls.auth_storage.name
        cls.run = secrets.token_hex(8)
        cls.env.update(DB_DATABASE=DATABASE, HTTP_TEST_RUN=cls.run, APP_ENV='local', SESSION_SECURE='false', BOOKING_POLICY_CONFIRMED='false', STATE_STORAGE_DIR=cls.private_state.name, SLOT_MINUTES='30', CLINIC_OPEN='09:00', CLINIC_CLOSE='12:00')
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
        cls.server = subprocess.Popen([PHP, '-d', 'upload_max_filesize=3M', '-d', 'post_max_size=8M', '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=cls.env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **cls.hidden)
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
        self.assertIn('value="Updated name"', result[1])
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
        self.assertEqual(patient.call('/api/appointments/cancel', {'appointment_id': 1}, method='POST', csrf='forged')[0], 419)
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

    def test_pages_and_booking_configuration_gate(self):
        patient = Client(self.base)
        patient.login('patient', self.run)
        doctor = Client(self.base)
        doctor.login('doctor', self.run)
        self.assertEqual(doctor.call('/doctor/dashboard')[0], 200)
        result = patient.call('/api/doctors')
        self.assertTrue(json.loads(result[1])['doctors'])
        self.assertNotIn('password_hash', result[1])
        self.assertEqual(patient.call('/patient/appointments')[0], 200)
        # Slot configuration is covered by pure PHP tests; no invented clinic defaults here.
        self.assertEqual(patient.call('/api/doctors/0/slots?date=invalid')[0], 404)
        self.assertEqual(patient.call('/.env')[0], 404)
        self.assertEqual(patient.call('/config/database.php')[0], 404)
        date = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=6))).date() + datetime.timedelta(days=1)
        while date.weekday() >= 5:
            date += datetime.timedelta(days=1)
        self.assertEqual(patient.call(f'/api/doctors/{self.ids["doctor_profile"]}/slots?date={date}')[0], 503)

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
        page = admin.call(f'/admin/specializations?edit={self.ids["specialization"]}')[1]
        self.assertIn('name="action" value="edit"', page)
        self.assertIn(f'name="id" value="{self.ids["specialization"]}"', page)
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
        self.assertEqual(upload(png + b'0' * (2 * 1024 * 1024 + 1 - len(png)))[0], 422)
        result = upload(png + b'0' * (2 * 1024 * 1024 - len(png)))
        self.assertEqual(result[0], 200)
        match = re.search(r'<tr><td>HTTP Created doctor</td>.*?/admin/doctors/(\d+)/edit', result[1], re.S)
        self.assertIsNotNone(match)
        doctor_id = match.group(1)
        page = admin.call(f'/admin/doctors/{doctor_id}/edit')
        self.assertIn('Test biography', page[1])
        self.assertIn('value="HTTP Created doctor"', page[1])
        self.assertIn(f'value="created-{self.run}@example.test"', page[1])
        self.assertRegex(page[1], r'value="Monday"[^>]*checked')
        self.assertEqual(admin.call(f'/admin/doctors/{doctor_id}/deactivate', {'_csrf': token})[0], 200)
        patient = Client(self.base)
        patient.login('patient', self.run)
        self.assertNotIn('HTTP Created doctor', patient.call('/api/doctors')[1])

    def test_status_endpoints_and_cancellation_reason(self):
        doctor = Client(self.base)
        doctor.login('doctor', self.run)
        token = doctor.token('/doctor/dashboard')
        result = doctor.call(f'/api/appointments/{self.ids["appointment_0"]}', {'status': 'Completed'}, method='PUT', csrf=token)
        self.assertEqual(result[0], 200)
        self.assertEqual(json.loads(result[1]), {'ok': True, 'new_status': 'Completed'})
        patient = Client(self.base)
        patient.login('patient', self.run)
        result = patient.call('/api/appointments/cancel', {'appointment_id': self.ids['appointment_1']}, method='POST', csrf=patient.token('/patient/profile'))
        self.assertEqual(json.loads(result[1])['new_status'], 'Cancelled')
        admin = Client(self.base)
        admin.login('admin', self.run)
        token = admin.token('/admin')
        self.assertEqual(admin.call(f'/api/appointments/{self.ids["appointment_0"]}', {'status': 'Cancelled'}, method='PUT', csrf=token)[0], 422)
        result = admin.call(f'/api/appointments/{self.ids["appointment_0"]}', {'status': 'Cancelled', 'reason': 'HTTP integration cancellation fixture'}, method='PUT', csrf=token)
        self.assertEqual(result[0], 200)
        self.assertEqual(json.loads(result[1])['new_status'], 'Cancelled')

    def test_enabled_booking_journey_and_two_doctor_ownership(self):
        # A second server shares the fresh isolated database/state but enables explicit TEST hours.
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        env = dict(self.env, BOOKING_POLICY_CONFIRMED='true')
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, **self.hidden)
        def stop():
            server.terminate()
            try:
                server.wait(timeout=5)
            except subprocess.TimeoutExpired:
                server.kill()
                server.wait()
        self.addCleanup(stop)
        base = f'http://127.0.0.1:{port}'
        for _ in range(50):
            try:
                if Client(base).call('/')[0] == 200:
                    break
            except (OSError, error.URLError):
                pass
            time.sleep(0.1)
        else:
            self.fail('Enabled local server did not become ready')
        patient = Client(base)
        self.assertEqual(patient.login('journey', self.run)[0], 200)
        date = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=6))).date() + datetime.timedelta(days=1)
        while date.weekday() >= 5:
            date += datetime.timedelta(days=1)
        doctor_id = self.ids['doctor_profile']
        page_path = f'/doctors/{doctor_id}'
        self.assertEqual(patient.call(page_path)[0], 200)
        result = patient.call(f'/api/doctors/{doctor_id}/slots?date={date}')
        self.assertEqual(result[0], 200)
        slots = json.loads(result[1])
        self.assertIn('10:00', slots)
        body = {'_csrf': patient.token(page_path), 'doctor_id': doctor_id, 'appointment_date': str(date), 'appointment_time': '10:00', 'reason': 'Enabled HTTP booking journey'}
        result = patient.call('/appointments', body)
        self.assertEqual(result[0], 200)
        match = re.search(r'/patient/appointments/(\d+)/confirmation$', result[3])
        self.assertIsNotNone(match)
        appointment_id = int(match.group(1))
        self.assertIn('Pending', result[1])
        self.assertIn('Enabled HTTP booking journey', patient.call('/patient/appointments')[1])
        self.assertEqual(patient.call('/appointments', body)[0], 409)
        stranger = Client(base)
        stranger.login('patient', self.run)
        self.assertEqual(stranger.call(f'/patient/appointments/{appointment_id}/confirmation')[0], 404)
        self.assertEqual(stranger.call('/api/appointments/cancel', {'appointment_id': appointment_id}, method='POST', csrf=stranger.token('/patient/profile'))[0], 403)
        otherdoctor = Client(base)
        otherdoctor.login('otherdoctor', self.run)
        self.assertEqual(otherdoctor.call(f'/api/appointments/{appointment_id}', {'status': 'Completed'}, method='PUT', csrf=otherdoctor.token('/doctor/dashboard'))[0], 403)
        doctor = Client(base)
        doctor.login('doctor', self.run)
        self.assertEqual(doctor.call(f'/api/appointments/{appointment_id}', {'status': 'Completed'}, method='PUT', csrf=doctor.token('/doctor/dashboard'))[0], 200)

    def test_z_login_throttling_unknown_account(self):
        # Last test: shared loopback IP budget intentionally retained throughout the suite.
        client = Client(self.base)
        body = {'_csrf': client.token(), 'email': f'unknown-{self.run}@example.test', 'password': 'wrong'}
        for _ in range(5):
            self.assertEqual(client.call('/login', body)[0], 422)
        result = client.call('/login', body)
        self.assertEqual(result[0], 429)
        self.assertGreater(int(result[2]['Retry-After']), 0)
        self.assertIn('Sign-in temporarily unavailable.', result[1])

if __name__ == '__main__':
    unittest.main(verbosity=2)
