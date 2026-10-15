'use strict';
(() => {
  const feedback = document.querySelector('#request-feedback');
  const announce = (message, error = false) => {
    if (!feedback) return;
    feedback.textContent = message;
    feedback.className = error ? 'notice error' : 'notice';
  };
  const request = async (url, method = 'GET', body) => {
    const response = await fetch(url, {
      method, credentials: 'same-origin',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    let data;
    try { data = await response.json(); } catch { throw new Error('Unexpected server response. Please reload and try again.'); }
    if (!response.ok || data.ok === false) throw new Error(data.message || 'The request failed.');
    return data;
  };
  window.Hospital = { request, announce };
  const element = (tag, text, className) => {
    const node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  };
  const filter = document.querySelector('#doctor-filter');
  if (filter) {
    let revision = 0;
    const refresh = async event => {
      event?.preventDefault();
      const current = ++revision;
      const cards = document.querySelector('#doctor-cards');
      cards.setAttribute('aria-busy', 'true');
      announce('Loading doctors…');
      try {
        const data = await request('/api/doctors?specialization_id=' + encodeURIComponent(filter.elements.specialization_id.value));
        if (current !== revision) return;
        const nodes = data.doctors.map(doctor => {
          const card = element('article', undefined, 'card doctor-card');
          if (/^\/uploads\/doctors\/[a-f0-9]{40}\.(jpg|png)$/.test(doctor.photo_path || '')) {
            const image = element('img', undefined, 'avatar'); image.src = doctor.photo_path; image.alt = doctor.name; card.append(image);
          }
          card.append(element('h2', doctor.name), element('p', doctor.specialization), element('p', 'Consultation fee: ' + doctor.consultation_fee));
          const link = element('a', 'View availability', 'button'); link.href = '/doctors/' + Number(doctor.id); card.append(link);
          return card;
        });
        cards.replaceChildren(...(nodes.length ? nodes : [element('p', 'No active doctors match this selection.', 'card')]));
        announce(nodes.length + ' doctors found.');
      } catch (error) { if (current === revision) announce(error.message, true); }
      finally { if (current === revision) cards.setAttribute('aria-busy', 'false'); }
    };
    filter.addEventListener('submit', refresh);
    filter.elements.specialization_id.addEventListener('change', refresh);
  }
  document.querySelectorAll('[data-toggle-user]').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const data = await request('/api/users/toggle-active', 'POST', { user_id: Number(button.dataset.toggleUser) });
      button.textContent = data.is_active ? 'Deactivate' : 'Activate';
      button.closest('tr').querySelector('[data-active-label]').textContent = data.is_active ? 'Active' : 'Inactive';
      announce('Account access updated.');
    } catch (error) { announce(error.message, true); } finally { button.disabled = false; }
  }));
  if (document.querySelector('[data-doctor-stats]')) {
    request('/api/doctors/stats').then(data => {
      data.doctors.forEach(doctor => {
        const cell = document.querySelector(`[data-stat-id="${Number(doctor.id)}"]`);
        if (cell) cell.textContent = String(doctor.appointment_count);
      });
    }).catch(error => {
      document.querySelectorAll('[data-stat-id]').forEach(cell => { cell.textContent = 'Unavailable'; });
      announce(error.message, true);
    });
  }
})();
