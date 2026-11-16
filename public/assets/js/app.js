/* MAXXfam application shell */
(function () {
  'use strict';

  const ready = (fn) => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn();
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  window.maxxToast = function (message, type = 'info') {
    let host = document.getElementById('toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toast-host';
      host.className = 'fixed inset-x-4 bottom-4 z-[100] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:bottom-6';
      document.body.appendChild(host);
    }
    const colors = {
      success: 'border-emerald-400/30 bg-emerald-950/95 text-emerald-100',
      error: 'border-rose-400/30 bg-rose-950/95 text-rose-100',
      info: 'border-sky-400/30 bg-slate-950/95 text-sky-100',
      warning: 'border-amber-400/30 bg-amber-950/95 text-amber-100'
    };
    const el = document.createElement('div');
    el.className = `pointer-events-auto flex max-w-sm items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-2xl backdrop-blur transition duration-300 ${colors[type] || colors.info}`;
    el.innerHTML = `<span class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-current"></span><span>${String(message).replace(/[<>&"]/g, (c) => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;'}[c]))}</span>`;
    host.appendChild(el);
    setTimeout(() => { el.classList.add('translate-y-2', 'opacity-0'); setTimeout(() => el.remove(), 300); }, 5200);
  };

  function initTheme() {
    const stored = localStorage.getItem('maxxfam-theme');
    const dark = stored ? stored === 'dark' : document.documentElement.classList.contains('dark');
    document.documentElement.classList.toggle('dark', dark);
    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', next);
        localStorage.setItem('maxxfam-theme', next ? 'dark' : 'light');
        document.querySelectorAll('[data-theme-icon="sun"]').forEach((i) => i.classList.toggle('hidden', !next));
        document.querySelectorAll('[data-theme-icon="moon"]').forEach((i) => i.classList.toggle('hidden', next));
        window.dispatchEvent(new CustomEvent('maxx:theme', { detail: { dark: next } }));
      });
    });
  }

  function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const openers = document.querySelectorAll('[data-sidebar-open]');
    const closers = document.querySelectorAll('[data-sidebar-close]');
    if (!sidebar) return;
    const open = () => {
      sidebar.classList.remove('-translate-x-full');
      sidebar.classList.add('translate-x-0');
      if (overlay) overlay.classList.remove('hidden');
      document.body.classList.add('overflow-hidden', 'lg:overflow-auto');
    };
    const close = () => {
      sidebar.classList.add('-translate-x-full');
      sidebar.classList.remove('translate-x-0');
      if (overlay) overlay.classList.add('hidden');
      document.body.classList.remove('overflow-hidden', 'lg:overflow-auto');
    };
    openers.forEach((b) => b.addEventListener('click', open));
    closers.forEach((b) => b.addEventListener('click', close));
    overlay?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
  }

  function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((root) => {
      const trigger = root.querySelector('[data-dropdown-trigger]');
      const panel = root.querySelector('[data-dropdown-panel]');
      if (!trigger || !panel) return;
      trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        panel.classList.toggle('hidden');
      });
      document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) panel.classList.add('hidden');
      });
    });
  }

  function initConfirm() {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;
    const title = modal.querySelector('[data-confirm-title]');
    const copy = modal.querySelector('[data-confirm-copy]');
    const proceed = modal.querySelector('[data-confirm-proceed]');
    const cancel = modal.querySelector('[data-confirm-cancel]');
    let pendingForm = null;
    const close = () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); pendingForm = null; };
    cancel?.addEventListener('click', close);
    modal.querySelector('[data-confirm-backdrop]')?.addEventListener('click', close);
    document.addEventListener('submit', (e) => {
      const form = e.target;
      if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
      e.preventDefault();
      pendingForm = form;
      if (title) title.textContent = form.dataset.confirmTitle || 'Confirm this action';
      if (copy) copy.textContent = form.dataset.confirm;
      modal.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    });
    proceed?.addEventListener('click', () => {
      if (!pendingForm) return;
      pendingForm.dataset.confirmed = '1';
      const btn = proceed;
      btn.disabled = true;
      btn.innerHTML = '<span class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span> Working…';
      pendingForm.submit();
    });
  }

  function initPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.togglePassword || '');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        btn.setAttribute('aria-pressed', input.type === 'text' ? 'true' : 'false');
      });
    });
  }

  function initCopy() {
    document.querySelectorAll('[data-copy]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const value = btn.dataset.copy || '';
        try {
          await navigator.clipboard.writeText(value);
          window.maxxToast('Copied to clipboard', 'success');
          const old = btn.innerHTML;
          btn.innerHTML = '<span class="text-emerald-400">Copied</span>';
          setTimeout(() => { btn.innerHTML = old; }, 1400);
        } catch {
          window.maxxToast('Copy failed — select the text manually', 'error');
        }
      });
    });
  }

  function initCounters() {
    document.querySelectorAll('[data-count]').forEach((el) => {
      const target = parseFloat(el.dataset.count || '0');
      if (reduceMotion || !isFinite(target)) { el.textContent = el.dataset.format === 'money' ? formatMoney(target) : target.toLocaleString('en-IN'); return; }
      const duration = 900;
      const start = performance.now();
      const money = el.dataset.format === 'money';
      const frame = (now) => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        const value = target * eased;
        el.textContent = money ? formatMoney(value) : Math.round(value).toLocaleString('en-IN');
        if (p < 1) requestAnimationFrame(frame);
      };
      requestAnimationFrame(frame);
    });
  }

  function formatMoney(value) {
    return '₹' + Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function initReveal() {
    const els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    if (reduceMotion || !('IntersectionObserver' in window)) { els.forEach((el) => el.classList.add('is-visible')); return; }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) { entry.target.classList.add('is-visible'); io.unobserve(entry.target); }
      });
    }, { threshold: 0.12 });
    els.forEach((el) => io.observe(el));
  }

  function initAccordions() {
    document.querySelectorAll('[data-accordion]').forEach((item) => {
      const trigger = item.querySelector('[data-accordion-trigger]');
      const panel = item.querySelector('[data-accordion-panel]');
      if (!trigger || !panel) return;
      trigger.addEventListener('click', () => {
        const open = panel.classList.contains('grid-rows-[1fr]');
        item.closest('[data-accordion-group]')?.querySelectorAll('[data-accordion]').forEach((other) => {
          other.querySelector('[data-accordion-panel]')?.classList.remove('grid-rows-[1fr]');
          other.querySelector('[data-accordion-panel]')?.classList.add('grid-rows-[0fr]');
          other.querySelector('[data-accordion-trigger]')?.setAttribute('aria-expanded', 'false');
        });
        if (!open) {
          panel.classList.remove('grid-rows-[0fr]');
          panel.classList.add('grid-rows-[1fr]');
          trigger.setAttribute('aria-expanded', 'true');
        }
      });
    });
  }

  function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
      const buttons = root.querySelectorAll('[data-tab]');
      const panels = root.querySelectorAll('[data-tab-panel]');
      buttons.forEach((btn) => btn.addEventListener('click', () => {
        buttons.forEach((b) => b.classList.remove('is-active', 'bg-white', 'text-slate-900', 'shadow-sm', 'dark:bg-white/10', 'dark:text-white'));
        panels.forEach((p) => p.classList.add('hidden'));
        btn.classList.add('is-active', 'bg-white', 'text-slate-900', 'shadow-sm', 'dark:bg-white/10', 'dark:text-white');
        root.querySelector(`[data-tab-panel="${btn.dataset.tab}"]`)?.classList.remove('hidden');
      }));
    });
  }

  function initLoadingForms() {
    document.querySelectorAll('form[data-loading]').forEach((form) => {
      form.addEventListener('submit', () => {
        if (form.dataset.confirmed === '1' || !form.dataset.confirm) {
          const btn = form.querySelector('button[type="submit"]');
          if (btn && !btn.disabled) {
            btn.dataset.original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="h-4 w-4 animate-spin rounded-full border-2 border-current/30 border-t-current"></span><span>Please wait…</span>';
          }
        }
      });
    });
  }

  function initCharts() {
    if (typeof Chart === 'undefined') return;
    const isDark = document.documentElement.classList.contains('dark');
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.color = isDark ? '#94a3b8' : '#64748b';
    Chart.defaults.borderColor = isDark ? 'rgba(148,163,184,.12)' : 'rgba(148,163,184,.18)';

    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
      let payload = {};
      try { payload = JSON.parse(canvas.dataset.chart || '{}'); } catch { return; }
      const type = payload.type || 'line';
      const labels = payload.labels || [];
      const isCurrency = payload.currency === true;
      const datasets = (payload.datasets || []).map((ds, i) => {
        const palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        const color = ds.color || palette[i % palette.length];
        const base = {
          ...ds,
          borderColor: color,
          backgroundColor: type === 'line' ? 'transparent' : color + 'cc',
          borderWidth: type === 'line' ? 2.5 : 0,
          pointRadius: type === 'line' ? 0 : undefined,
          pointHoverRadius: 4,
          tension: 0.38,
          fill: ds.fill === true
        };
        if (ds.fill === true) {
          const ctx = canvas.getContext('2d');
          const gradient = ctx.createLinearGradient(0, 0, 0, 280);
          gradient.addColorStop(0, color + '38');
          gradient.addColorStop(1, color + '00');
          base.backgroundColor = gradient;
        }
        if (type === 'doughnut') {
          base.backgroundColor = ['#10b981', '#f59e0b', '#ef4444', '#94a3b8', '#3b82f6'];
          base.borderWidth = 0;
        }
        return base;
      });
      new Chart(canvas, {
        type,
        data: { labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: 'index', intersect: false },
          animation: reduceMotion ? false : { duration: 850, easing: 'easeOutQuart' },
          plugins: {
            legend: { display: payload.legend === true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 7, padding: 18, font: { size: 11, weight: '600' } } },
            tooltip: {
              backgroundColor: isDark ? '#0b1324' : '#0f172a',
              titleFont: { size: 11, weight: '600' },
              bodyFont: { size: 12 },
              padding: 12,
              cornerRadius: 10,
              displayColors: false,
              callbacks: { label: (ctx) => (isCurrency ? '₹' + Number(ctx.parsed.y ?? ctx.parsed).toLocaleString('en-IN', { minimumFractionDigits: 2 }) : Number(ctx.parsed.y ?? ctx.parsed).toLocaleString('en-IN')) }
            }
          },
          scales: type === 'doughnut' ? {} : {
            x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10.5 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
            y: { beginAtZero: true, grid: { color: isDark ? 'rgba(148,163,184,.08)' : 'rgba(148,163,184,.15)', drawTicks: false }, border: { display: false }, ticks: { font: { size: 10.5 }, padding: 8, callback: (v) => isCurrency ? '₹' + Number(v).toLocaleString('en-IN') : Number(v).toLocaleString('en-IN') } }
          }
        }
      });
    });
  }

  function initCountdowns() {
    document.querySelectorAll('[data-countdown]').forEach((el) => {
      const target = new Date(el.dataset.countdown).getTime();
      if (!isFinite(target)) return;
      const render = () => {
        const diff = Math.max(0, target - Date.now());
        const m = Math.floor(diff / 60000);
        const s = Math.floor((diff % 60000) / 1000);
        el.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        if (diff <= 0) {
          el.dispatchEvent(new CustomEvent('countdown:expired'));
        }
      };
      render();
      const timer = setInterval(() => {
        render();
        if (Date.now() > target + 1000) clearInterval(timer);
      }, 1000);
    });
  }

  function initQr() {
    if (typeof QRCode === 'undefined') return;
    document.querySelectorAll('[data-qr]').forEach((el) => {
      if (el.dataset.qrRendered) return;
      el.dataset.qrRendered = '1';
      new QRCode(el, {
        text: el.dataset.qr,
        width: parseInt(el.dataset.qrSize || '160', 10),
        height: parseInt(el.dataset.qrSize || '160', 10),
        colorDark: '#0f172a',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    });
  }

  function initDemoQr() {
    document.querySelectorAll('[data-demo-qr]').forEach((grid) => {
      if (grid.children.length) return;
      const size = 21;
      const cells = [];
      for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
        const finder = (x < 7 && y < 7) || (x >= size - 7 && y < 7) || (x < 7 && y >= size - 7);
        const inner = finder && ((x % 7 === 0 || x % 7 === 6 || y % 7 === 0 || y % 7 === 6) || (x % 7 >= 2 && x % 7 <= 4 && y % 7 >= 2 && y % 7 <= 4));
        const on = finder ? inner : ((x * 13 + y * 7 + x * y) % 11 < 5);
        const span = document.createElement('span');
        span.className = finder ? 'eye' : (on ? 'on' : '');
        cells.push(span);
      }
      grid.append(...cells);
    });
  }

  ready(function () {
    initTheme();
    initSidebar();
    initDropdowns();
    initConfirm();
    initCopy();
    initCounters();
    initReveal();
    initAccordions();
    initTabs();
    initLoadingForms();
    initPasswordToggles();
    initCharts();
    initCountdowns();
    initQr();
    initDemoQr();
    if (typeof lucide !== 'undefined') lucide.createIcons({ attrs: { 'stroke-width': 1.8 } });
    const flashPayload = document.getElementById('flash-data');
    if (flashPayload) {
      try {
        const messages = JSON.parse(flashPayload.textContent || '{}');
        Object.entries(messages).forEach(([type, list]) => (list || []).forEach((m) => window.maxxToast(m, type)));
      } catch { /* no-op */ }
    }
    window.addEventListener('maxx:theme', () => initCharts());
  });
})();
