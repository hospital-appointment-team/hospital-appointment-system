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

function fakeButton(dataset = {}) {
  return { dataset, disabled: false, textContent: '', listeners: {},
    addEventListener(type, callback) { this.listeners[type] = callback; },
    setAttribute() {},
  };
}
test('activation updates the label only after server success', async () => {
  const button = fakeButton({ toggleUser: '12' }); button.textContent = 'Deactivate';
  const label = { textContent: 'Active' };
  button.closest = () => ({ querySelector: () => label });
  setup(async () => ({ ok: true, json: async () => ({ ok: true, is_active: false }) }), {
    querySelectorAll(selector) { return selector === '[data-toggle-user]' ? [button] : []; },
  });
  await button.listeners.click();
  assert.equal(label.textContent, 'Inactive'); assert.equal(button.textContent, 'Activate'); assert.equal(button.disabled, false);
});
test('failed activation preserves the displayed state and allows retry', async () => {
  const button = fakeButton({ toggleUser: '12' }); button.textContent = 'Deactivate';
  const label = { textContent: 'Active' }; button.closest = () => ({ querySelector: () => label });
  setup(async () => ({ ok: false, json: async () => ({ ok: false, message: 'Forbidden' }) }), {
    querySelectorAll(selector) { return selector === '[data-toggle-user]' ? [button] : []; },
  });
  await button.listeners.click();
  assert.equal(label.textContent, 'Active'); assert.equal(button.textContent, 'Deactivate'); assert.equal(button.disabled, false);
});
test('doctor action uses the shared PUT endpoint and updates its badge', async () => {
  const button = fakeButton({ id: '20', status: 'Completed' });
  const badge = { textContent: 'Confirmed', className: 'badge confirmed' };
  const row = {
    querySelectorAll(selector) { return selector === '[data-status-badge]' ? [badge] : selector === '[data-status]' ? [button] : []; },
    matches() { return false; }, closest() { return null; },
  };
  button.closest = () => row;
  setup(async (url, options) => {
    assert.equal(url, '/api/appointments/20'); assert.equal(options.method, 'PUT');
    assert.equal(JSON.parse(options.body).status, 'Completed');
    return { ok: true, json: async () => ({ ok: true, new_status: 'Completed' }) };
  }, { querySelectorAll(selector) {
    if (selector === '[data-status]') return [button];
    if (selector.startsWith('[data-appointment-id="20"]')) return [row];
    return [];
  } });
  await button.listeners.click();
  assert.equal(badge.textContent, 'Completed'); assert.equal(badge.className, 'badge completed'); assert.equal(button.disabled, true);
});
test('stale slot response cannot replace the latest selected day', async () => {
  const first = fakeButton({ date: '2026-10-19' }); const second = fakeButton({ date: '2026-10-20' });
  const form = { elements: { appointment_date: { value: '' }, appointment_time: { value: '09:00' } }, addEventListener() {} };
  const submit = { disabled: false }; const message = { textContent: '' };
  const slots = { children: [], replaceChildren() { this.children = []; }, append(child) { this.children.push(child); } };
  const booking = { dataset: { bookingDoctor: '5' }, querySelectorAll() { return [first, second]; } };
  const pending = [];
  setup(() => new Promise(resolve => pending.push(resolve)), {
    querySelector(selector) {
      return { '[data-booking-doctor]': booking, '#booking-form': form, '#book-submit': submit,
        '#slot-options': slots, '#slot-message': message, 'meta[name="csrf-token"]': { content: 'token' } }[selector] || null;
    },
    createElement() { return fakeButton(); },
  });
  const oldRequest = first.listeners.click();
  const newRequest = second.listeners.click();
  assert.equal(form.elements.appointment_time.value, ''); assert.equal(submit.disabled, true);
  pending[1]({ ok: true, json: async () => ['10:00'] }); await newRequest;
  pending[0]({ ok: true, json: async () => ['09:00'] }); await oldRequest;
  assert.equal(slots.children.length, 1); assert.equal(slots.children[0].textContent, '10:00');
  assert.equal(form.elements.appointment_date.value, '2026-10-20');
});

function discoverySetup(fetch) {
  const select = fakeButton(); select.value = '1';
  const filter = { elements: { specialization_id: select }, listeners: {}, addEventListener(type, listener) { this.listeners[type] = listener; } };
  const cards = { children: [], attributes: {}, setAttribute(key, value) { this.attributes[key] = value; }, replaceChildren(...nodes) { this.children = nodes; } };
  const makeElement = tag => ({ tag, children: [], append(...children) { this.children.push(...children); } });
  setup(fetch, {
    querySelector(selector) { return { '#doctor-filter': filter, '#doctor-cards': cards, 'meta[name="csrf-token"]': { content: 'token' } }[selector] || null; },
    createElement: makeElement,
  });
  return { filter, select, cards };
}
test('doctor filtering encodes the filter and renders names safely as text', async () => {
  const view = discoverySetup(async url => {
    assert.equal(url, '/api/doctors?specialization_id=1%26other%3D2');
    return { ok: true, json: async () => ({ ok: true, doctors: [{ id: 7, name: '<img onerror=alert(1)>', specialization: 'Cardiology', consultation_fee: '500.00', photo_path: '/uploads/doctors/unsafe.php' }] }) };
  });
  view.select.value = '1&other=2';
  await view.filter.listeners.submit({ preventDefault() {} });
  assert.equal(view.cards.children[0].children[0].textContent, '<img onerror=alert(1)>');
  assert.equal(view.cards.children[0].children[0].tag, 'h2');
  assert.equal(view.cards.children[0].children.at(-1).href, '/doctors/7');
  assert.equal(view.cards.attributes['aria-busy'], 'false');
});
test('stale doctor filter responses cannot replace the latest doctor list', async () => {
  const pending = [];
  const view = discoverySetup(() => new Promise(resolve => pending.push(resolve)));
  const oldRequest = view.select.listeners.change();
  view.select.value = '2';
  const newRequest = view.select.listeners.change();
  const response = name => ({ ok: true, json: async () => ({ ok: true, doctors: [{ id: 1, name, specialization: 'Test', consultation_fee: '1.00' }] }) });
  pending[1](response('Latest doctor')); await newRequest;
  pending[0](response('Old doctor')); await oldRequest;
  assert.equal(view.cards.children[0].children[0].textContent, 'Latest doctor');
});
