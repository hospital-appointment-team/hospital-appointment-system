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
})();
