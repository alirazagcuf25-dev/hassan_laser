async function postJson(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  const contentType = (res.headers.get('content-type') || '').toLowerCase();
  if (contentType.includes('application/json')) {
    return res.json();
  }

  const text = await res.text();
  return {
    ok: false,
    message: text ? 'Unexpected server response: ' + text.slice(0, 180) : 'Unexpected server response'
  };
}

function appUrl(path) {
  const base = (window.APP_BASE || '').replace(/\/$/, '');
  return base + path;
}

function show(data, typeHint) {
  const messageBox = document.getElementById('authMessage');
  if (messageBox) {
    const isOk = typeof typeHint === 'string' ? typeHint === 'success' : !!(data && data.ok);
    messageBox.classList.remove('success', 'error');
    messageBox.classList.add(isOk ? 'success' : 'error');
    messageBox.textContent = (data && data.message) ? data.message : JSON.stringify(data || {}, null, 2);
  }
}

function clearMessage() {
  const messageBox = document.getElementById('authMessage');
  if (!messageBox) {
    return;
  }
  messageBox.classList.remove('success', 'error');
  messageBox.textContent = '';
}

function setLoggedInUI(user) {
  const appPanel = document.getElementById('appPanel');
  const authCard = document.getElementById('authCard');

  if (!appPanel || !authCard) {
    return;
  }

  if (user) {
    appPanel.style.display = '';
    authCard.style.display = 'none';
    return;
  }

  appPanel.style.display = 'none';
  authCard.style.display = '';
}

function setupAuthTabs() {
  const tabButtons = document.querySelectorAll('.switch-link');
  const panes = document.querySelectorAll('.tab-pane');
  const title = document.getElementById('authTitle');
  const sub = document.getElementById('authSub');

  if (!tabButtons.length || !panes.length || !title || !sub) {
    return;
  }

  const tabMeta = {
    signin: {
      title: 'Sign In',
      sub: 'Welcome back. Enter your credentials to continue.'
    },
    signup: {
      title: 'Create Account',
      sub: 'Register a new account to start using the system.'
    },
    forgot: {
      title: 'Reset Password',
      sub: 'Set a new password for your username.'
    }
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const tab = btn.getAttribute('data-tab');

      tabButtons.forEach((b) => b.classList.remove('active'));
      panes.forEach((p) => p.classList.remove('active'));

      btn.classList.add('active');
      document.getElementById('pane-' + tab).classList.add('active');
      title.textContent = tabMeta[tab].title;
      sub.textContent = tabMeta[tab].sub;
      clearMessage();
    });
  });
}

function setupPasswordToggles() {
  const toggles = document.querySelectorAll('.password-toggle');
  if (!toggles.length) {
    return;
  }

  toggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const wrap = toggle.closest('.password-wrap');
      if (!wrap) {
        return;
      }

      const input = wrap.querySelector('input[type="password"], input[type="text"]');
      if (!input) {
        return;
      }

      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.classList.toggle('is-visible', !showing);
      toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
      toggle.setAttribute('title', showing ? 'Show password' : 'Hide password');
    });
  });
}

async function searchCustomersByName(term) {
  const res = await fetch(appUrl('/api/orders/search_customers.php?q=' + encodeURIComponent(term)));
  return res.json();
}

function renderCustomerResults(rows) {
  const box = document.getElementById('customerSearchResults');
  box.innerHTML = '';

  if (!rows.length) {
    return;
  }

  rows.forEach((row) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'search-item';
    const phoneText = row.phone ? ' (' + row.phone + ')' : '';
    btn.textContent = row.party_name + phoneText;
    btn.addEventListener('click', () => {
      document.getElementById('customerSearch').value = row.party_name;
      document.getElementById('customerPartyId').value = row.id;
      box.innerHTML = '';
    });
    box.appendChild(btn);
  });
}

setupAuthTabs();
setupPasswordToggles();
setLoggedInUI(window.CURRENT_USER || null);

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();
    const form = new FormData(e.target);
    try {
      const data = await postJson(appUrl('/api/auth/login.php'), {
        username: form.get('username'),
        password: form.get('password')
      });
      show(data);

      if (data.ok) {
        window.location.reload();
      }
    } catch (err) {
      show({ ok: false, message: 'Login failed. Please try again.' });
    }
  });
}

const signupForm = document.getElementById('signupForm');
if (signupForm) {
  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();
    const form = new FormData(e.target);
    const password = (form.get('password') || '').toString();
    const confirmPassword = (form.get('confirm_password') || '').toString();

    if (password !== confirmPassword) {
      show({ ok: false, message: 'Password and Confirm Password must match.' });
      return;
    }

    try {
      const data = await postJson(appUrl('/api/auth/signup.php'), {
        username: form.get('username'),
        phone: form.get('phone'),
        email: form.get('email'),
        password
      });
      show(data);

      if (data.ok) {
        e.target.reset();
        const signInBtn = document.querySelector('.switch-link[data-tab="signin"]');
        if (signInBtn) {
          signInBtn.click();
          show({ ok: true, message: 'Account created. Please sign in.' }, 'success');
        }
      }
    } catch (err) {
      show({ ok: false, message: 'Signup failed. Please try again.' });
    }
  });
}

const forgotForm = document.getElementById('forgotForm');
if (forgotForm) {
  forgotForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearMessage();
    const form = new FormData(e.target);
    try {
      const data = await postJson(appUrl('/api/auth/forgot_password.php'), {
        username: form.get('username'),
        new_password: form.get('new_password')
      });
      show(data);
    } catch (err) {
      show({ ok: false, message: 'Password reset failed. Please try again.' });
    }
  });
}

let searchTimer = null;
const customerSearch = document.getElementById('customerSearch');
if (customerSearch) {
  customerSearch.addEventListener('input', async (e) => {
    const term = e.target.value.trim();
    document.getElementById('customerPartyId').value = '';

    if (searchTimer) {
      clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(async () => {
      if (term.length < 2) {
        renderCustomerResults([]);
        return;
      }

      try {
        const data = await searchCustomersByName(term);
        if (!data || !data.ok || !Array.isArray(data.rows)) {
          renderCustomerResults([]);
          return;
        }

        renderCustomerResults(data.rows);
      } catch (err) {
        renderCustomerResults([]);
      }
    }, 250);
  });
}

const orderForm = document.getElementById('orderForm');
if (orderForm) {
  orderForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const customerId = Number(form.get('customer_party_id'));

    if (!customerId) {
      show({ ok: false, message: 'Please select customer from search results.' });
      return;
    }

    const data = await postJson(appUrl('/api/orders/create.php'), {
      customer_party_id: customerId,
      delivery_date: form.get('delivery_date').replace('T', ' ') + ':00',
      estimate_amount: Number(form.get('estimate_amount')),
      advance_amount: Number(form.get('advance_amount'))
    });
    show(data);
  });
}

const completeForm = document.getElementById('completeForm');
if (completeForm) {
  completeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await postJson(appUrl('/api/orders/complete.php'), {
      order_id: Number(form.get('order_id')),
      final_bill_amount: Number(form.get('final_bill_amount'))
    });
    show(data);
  });
}

const stockForm = document.getElementById('stockForm');
if (stockForm) {
  stockForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await postJson(appUrl('/api/inventory/movement.php'), {
      item_id: Number(form.get('item_id')),
      movement_type: form.get('movement_type'),
      quantity: Number(form.get('quantity')),
      unit_name: form.get('unit_name'),
      unit_rate: Number(form.get('unit_rate'))
    });
    show(data);
  });
}

// ── Ledger Report ────────────────────────────────────────────────
let currentLedgerType = 'customer';

async function loadLedgerParties(type) {
  const select = document.getElementById('ledgerPartySelect');
  if (!select) return;
  select.innerHTML = '<option value="">Loading…</option>';
  try {
    const res = await fetch(appUrl('/api/reports/parties.php?type=' + encodeURIComponent(type)));
    const data = await res.json();
    select.innerHTML = '<option value="">-- Select Party --</option>';
    if (data.ok && Array.isArray(data.rows)) {
      data.rows.forEach((row) => {
        const opt = document.createElement('option');
        opt.value = row.id;
        opt.textContent = row.party_name + (row.phone ? ' (' + row.phone + ')' : '');
        select.appendChild(opt);
      });
    }
  } catch (_) {
    select.innerHTML = '<option value="">-- Failed to load --</option>';
  }
}

function formatNum(n) {
  return Number(n).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatBalance(n) {
  return n >= 0 ? formatNum(n) + ' Dr' : formatNum(Math.abs(n)) + ' Cr';
}

function renderLedger(data) {
  const box = document.getElementById('ledgerResult');
  if (!box) return;

  if (!data.ok) {
    box.innerHTML = '<p class="ledger-error">' + (data.message || 'Error loading ledger') + '</p>';
    return;
  }

  const party = data.party || {};
  const rows = data.rows || [];

  let html = '<div class="ledger-party-info">'
    + '<strong>' + party.party_name + '</strong>'
    + (party.party_code ? ' <span class="ledger-code">' + party.party_code + '</span>' : '')
    + ' &mdash; <em>' + (party.party_type || '') + '</em>'
    + (party.phone ? ' &nbsp;Tel: ' + party.phone : '')
    + '</div>';

  if (!rows.length) {
    html += '<p class="ledger-empty">No transactions found.</p>';
    box.innerHTML = html;
    return;
  }

  html += '<div class="ledger-table-wrap"><table class="ledger-table">'
    + '<thead><tr><th>#</th><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>'
    + '<tbody>';

  rows.forEach((row, i) => {
    html += '<tr>'
      + '<td>' + (i + 1) + '</td>'
      + '<td>' + (row.tx_date || '').slice(0, 16) + '</td>'
      + '<td>' + (row.description || '') + '</td>'
      + '<td class="num">' + (row.debit > 0 ? formatNum(row.debit) : '') + '</td>'
      + '<td class="num">' + (row.credit > 0 ? formatNum(row.credit) : '') + '</td>'
      + '<td class="num bal">' + formatBalance(row.balance) + '</td>'
      + '</tr>';
  });

  html += '</tbody></table></div>';

  html += '<div class="ledger-summary">'
    + '<span>Total Debit: <strong>' + formatNum(data.total_debit) + '</strong></span>'
    + '<span>Total Credit: <strong>' + formatNum(data.total_credit) + '</strong></span>'
    + '<span>Net Balance: <strong>' + formatBalance(data.balance) + '</strong></span>'
    + '</div>';

  box.innerHTML = html;
}

const ledgerTypeBtns = document.querySelectorAll('.ledger-type-btn');
if (ledgerTypeBtns.length) {
  ledgerTypeBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      ledgerTypeBtns.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentLedgerType = btn.getAttribute('data-type');
      const box = document.getElementById('ledgerResult');
      if (box) box.innerHTML = '';
      loadLedgerParties(currentLedgerType);
    });
  });

  loadLedgerParties(currentLedgerType);
}

const ledgerLoadBtn = document.getElementById('ledgerLoadBtn');
if (ledgerLoadBtn) {
  ledgerLoadBtn.addEventListener('click', async () => {
    const select = document.getElementById('ledgerPartySelect');
    const partyId = select ? select.value : '';
    if (!partyId) {
      const box = document.getElementById('ledgerResult');
      if (box) box.innerHTML = '<p class="ledger-error">Please select a party first.</p>';
      return;
    }
    const box = document.getElementById('ledgerResult');
    if (box) box.innerHTML = '<p class="ledger-loading">Loading…</p>';
    try {
      const res = await fetch(appUrl('/api/reports/ledger.php?party_id=' + encodeURIComponent(partyId)));
      const data = await res.json();
      renderLedger(data);
    } catch (_) {
      if (box) box.innerHTML = '<p class="ledger-error">Failed to load ledger.</p>';
    }
  });
}
