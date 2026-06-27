// js/utils.js — shared across all pages

const API = {
  auth:           'api/auth.php',
  households:     'api/households.php',
  wastebins:      'api/wastebins.php',
  transportation: 'api/transportation.php',
  collections:    'api/collections.php',
  qa:             'api/qa.php',
  analytics:      'api/analytics.php',
};

async function apiFetch(url, opts = {}) {
  try {
    const res = await fetch(url, {
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
      ...opts,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  } catch (e) {
    throw e;
  }
}

function showAlert(containerId, message, type = 'success') {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
  setTimeout(() => { el.innerHTML = ''; }, 4000);
}

function statusBadge(status) {
  const map = {
    scheduled:  'badge-blue',
    in_transit: 'badge-yellow',
    completed:  'badge-green',
    cancelled:  'badge-grey',
    open:       'badge-yellow',
    answered:   'badge-green',
    closed:     'badge-grey',
  };
  return `<span class="badge ${map[status] || 'badge-grey'}">${status}</span>`;
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-GB', { year:'numeric', month:'short', day:'numeric' });
}

function fmtDateTime(d) {
  if (!d) return '—';
  return new Date(d).toLocaleString('en-GB', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
}

function openModal(id)  { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

// Attach close on overlay click
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) overlay.classList.add('hidden');
    });
  });
});

// Guard: redirect if not logged in
async function guardAuth(expectedRole) {
  try {
    const me = await apiFetch(`${API.auth}?action=me`);
    if (me.role !== expectedRole) {
      window.location.href = 'index.html';
    }
    return me;
  } catch {
    window.location.href = 'index.html';
  }
}
