/* Hosted checkout: server-authoritative status polling */
(function () {
  'use strict';

  const root = document.getElementById('checkout-root');
  if (!root) return;

  const token = root.dataset.token;
  const csrf = root.dataset.csrf;
  const statusUrl = root.dataset.statusUrl;
  let timer = null;
  let failures = 0;
  let redirectTimer = null;

  const el = (id) => document.getElementById(id);
  const setText = (id, value) => { const node = el(id); if (node) node.textContent = value; };

  function showState(state) {
    ['waiting', 'success', 'expired', 'failed', 'cancelled'].forEach((name) => {
      const node = el('state-' + name);
      if (node) node.classList.toggle('hidden', name !== state);
    });
    root.dataset.state = state;
    if (state === 'success' || state === 'expired' || state === 'failed' || state === 'cancelled') {
      stopPolling();
      document.querySelectorAll('[data-countdown]').forEach((n) => { n.dataset.stopped = '1'; });
    }
  }

  async function poll() {
    if (document.hidden) return;
    try {
      const response = await fetch(statusUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
        body: new URLSearchParams({ _token: csrf, token }),
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();
      failures = 0;
      if (!data.success) throw new Error(data.error?.message || 'Status unavailable');
      const order = data.data;
      if (order.state === 'success') {
        setText('receipt-link', 'Download receipt');
        const link = el('receipt-link');
        if (link && order.receipt_url) link.href = order.receipt_url;
        const redirect = el('redirect-link');
        if (redirect && order.redirect_url) redirect.href = order.redirect_url;
        showState('success');
        if (order.redirect_url) startRedirect(order.redirect_url);
      } else if (order.state === 'expired') {
        showState('expired');
      } else if (order.state === 'failed') {
        setText('failure-reason', order.failure_message || 'The provider reported this payment as failed.');
        showState('failed');
      } else if (order.state === 'cancelled') {
        showState('cancelled');
      } else {
        showState('waiting');
      }
    } catch (e) {
      failures++;
      const note = el('poll-note');
      if (note) {
        note.classList.remove('hidden');
        note.textContent = failures > 2 ? 'Connection issue — retrying. No payment is confirmed until the provider verifies it.' : 'Checking provider status…';
      }
    }
  }

  function startRedirect(url) {
    let seconds = 8;
    const node = el('redirect-countdown');
    if (!node) return;
    node.classList.remove('hidden');
    const tick = () => {
      node.textContent = `Returning to the merchant in ${seconds}s`;
      if (seconds <= 0) { window.location.href = url; return; }
      seconds--;
      redirectTimer = setTimeout(tick, 1000);
    };
    tick();
  }

  function stopPolling() {
    if (timer) { clearInterval(timer); timer = null; }
    if (redirectTimer) { clearTimeout(redirectTimer); redirectTimer = null; }
  }

  timer = setInterval(poll, 4000);
  setTimeout(poll, 800);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

  document.addEventListener('countdown:expired', () => {
    if (root.dataset.state === 'waiting') {
      showState('waiting');
      const node = el('waiting-note');
      if (node) node.textContent = 'Session time elapsed. Checking final provider status…';
    }
  });

  const cancelForm = document.getElementById('cancel-form');
  cancelForm?.addEventListener('submit', (e) => {
    if (!confirm('Cancel this payment? Only do this if you have not paid.')) { e.preventDefault(); }
  });
})();
