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
  const booking = document.querySelector('[data-booking-doctor]');
  if (booking) {
    let revision = 0;
    const form = document.querySelector('#booking-form');
    const submit = document.querySelector('#book-submit');
    const slots = document.querySelector('#slot-options');
    const message = document.querySelector('#slot-message');
    booking.querySelectorAll('[data-date]').forEach(button => button.addEventListener('click', async () => {
      const current = ++revision;
      form.elements.appointment_date.value = button.dataset.date;
      form.elements.appointment_time.value = '';
      submit.disabled = true;
      slots.replaceChildren(); message.textContent = 'Loading available times…';
      booking.querySelectorAll('[data-date]').forEach(choice => choice.setAttribute('aria-pressed', String(choice === button)));
      try {
        const times = await request(`/api/doctors/${Number(booking.dataset.bookingDoctor)}/slots?date=${encodeURIComponent(button.dataset.date)}`);
        if (current !== revision) return;
        message.textContent = times.length ? 'Select a time.' : 'No available times for this date.';
        times.forEach(time => {
          const choice = element('button', time, 'secondary'); choice.type = 'button'; choice.setAttribute('aria-pressed', 'false');
          choice.addEventListener('click', () => {
            form.elements.appointment_time.value = time;
            slots.querySelectorAll('button').forEach(other => other.setAttribute('aria-pressed', String(other === choice)));
            submit.disabled = false;
          }); slots.append(choice);
        });
      } catch (error) { if (current === revision) message.textContent = error.message; }
    }));
    form.addEventListener('submit', event => {
      if (!form.elements.appointment_date.value || !form.elements.appointment_time.value) { event.preventDefault(); announce('Choose a date and time.', true); return; }
      submit.disabled = true; submit.textContent = 'Submitting…';
    });
  }
  const statusUpdate = (id, status) => {
    document.querySelectorAll(`[data-appointment-id="${id}"], [data-week-appointment="${id}"]`).forEach(container => {
      container.querySelectorAll('[data-status-badge]').forEach(badge => { badge.textContent = status; badge.className = 'badge ' + status.toLowerCase(); });
      if (container.matches('[data-week-appointment]')) container.className = 'week-appointment ' + status.toLowerCase();
      const activeFilter = document.querySelector('#status');
      if (activeFilter?.value && activeFilter.value !== status && container.closest('.table-wrap') && document.querySelector('.admin-cancel')) container.hidden = true;
      container.querySelectorAll('[data-status]').forEach(button => {
        if (status === 'Confirmed' && button.dataset.status !== 'Confirmed') return;
        button.disabled = true;
      });
    });
    announce('Appointment status updated to ' + status + '.');
  };
  document.querySelectorAll('[data-cancel-appointment]').forEach(button => button.addEventListener('click', async () => {
    button.disabled = true;
    try {
      const id = Number(button.dataset.cancelAppointment);
      const data = await request('/api/appointments/cancel', 'POST', { appointment_id: id });
      statusUpdate(id, data.new_status);
      const row = button.closest('tr'); row.lastElementChild.textContent = '—';
      document.querySelector('[data-status-group="Cancelled"] [data-group-body]').prepend(row);
      document.querySelectorAll('[data-status-group]').forEach(group => {
        group.querySelector('[data-empty-row]').hidden = Boolean(group.querySelector('[data-appointment-id]'));
      });
    } catch (error) { button.disabled = false; announce(error.message, true); }
  }));
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
