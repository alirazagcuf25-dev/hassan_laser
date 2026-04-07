async function postJson(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  return res.json();
}

function show(data) {
  const out = document.getElementById('output');
  if (out) {
    out.textContent = JSON.stringify(data, null, 2);
  }
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
    });
  });
}

async function searchCustomersByName(term) {
  const res = await fetch('../api/orders/search_customers.php?q=' + encodeURIComponent(term));
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
    btn.textContent = row.party_name + ' (' + (row.phone || 'no-phone') + ')';
    btn.addEventListener('click', () => {
      document.getElementById('customerSearch').value = row.party_name;
      document.getElementById('customerPartyId').value = row.id;
      box.innerHTML = '';
    });
    box.appendChild(btn);
  });
}

setupAuthTabs();
setLoggedInUI(window.CURRENT_USER || null);

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await postJson('../api/auth/login.php', {
      username: form.get('username'),
      password: form.get('password')
    });
    show(data);

    if (data.ok) {
      window.location.reload();
    }
  });
}

const signupForm = document.getElementById('signupForm');
if (signupForm) {
  signupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await postJson('../api/auth/signup.php', {
      username: form.get('username'),
      phone: form.get('phone'),
      email: form.get('email'),
      password: form.get('password')
    });
    show(data);
  });
}

const forgotForm = document.getElementById('forgotForm');
if (forgotForm) {
  forgotForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = new FormData(e.target);
    const data = await postJson('../api/auth/forgot_password.php', {
      username: form.get('username'),
      new_password: form.get('new_password')
    });
    show(data);
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

      const data = await searchCustomersByName(term);
      renderCustomerResults(data.rows || []);
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

    const data = await postJson('../api/orders/create.php', {
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
    const data = await postJson('../api/orders/complete.php', {
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
    const data = await postJson('../api/inventory/movement.php', {
      item_id: Number(form.get('item_id')),
      movement_type: form.get('movement_type'),
      quantity: Number(form.get('quantity')),
      unit_name: form.get('unit_name'),
      unit_rate: Number(form.get('unit_rate'))
    });
    show(data);
  });
}
