const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/js/app.js'), 'utf8');

function setup(fetch, overrides = {}) {
  const feedback = { textContent: '', className: '' };
  const context = { window: {}, fetch, document: {
    querySelector(selector) {
      if (selector === '#request-feedback') return feedback;
      if (selector === 'meta[name="csrf-token"]') return { content: 'session-token' };
      return null;
    },
    querySelectorAll() { return []; },
    ...overrides,
  } };
  vm.runInNewContext(source, context);
  return { ...context.window.Hospital, feedback };
}

test('JSON mutations include CSRF token and same-origin credentials', async () => {
  const app = setup(async (url, options) => {
    assert.equal(url, '/api/users/toggle-active');
    assert.equal(options.method, 'POST');
    assert.equal(options.credentials, 'same-origin');
    assert.equal(options.headers['X-CSRF-Token'], 'session-token');
    assert.deepEqual(JSON.parse(options.body), { user_id: 12 });
    return { ok: true, json: async () => ({ ok: true, is_active: false }) };
  });
  assert.equal((await app.request('/api/users/toggle-active', 'POST', { user_id: 12 })).is_active, false);
});
test('JSON error responses surface the server message', async () => {
  const app = setup(async () => ({ ok: false, json: async () => ({ ok: false, message: 'Forbidden' }) }));
  await assert.rejects(app.request('/api/users/toggle-active', 'POST', {}), /Forbidden/);
});
test('non-JSON responses produce a useful error', async () => {
  const app = setup(async () => ({ ok: false, json: async () => { throw new SyntaxError(); } }));
  await assert.rejects(app.request('/api/doctors/stats'), /Unexpected server response/);
});
test('network failures remain errors', async () => {
  const app = setup(async () => { throw new Error('Network unavailable'); });
  await assert.rejects(app.request('/api/doctors/stats'), /Network unavailable/);
});
test('feedback uses text rather than HTML', () => {
  const app = setup(async () => {});
  app.announce('<script>alert(1)</script>', true);
  assert.equal(app.feedback.textContent, '<script>alert(1)</script>');
  assert.equal(app.feedback.className, 'notice error');
});
