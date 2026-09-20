/* ============================================================
   ShopVerse – auth.js
   Login, register, account dashboard & orders page.
   Dummy credentials: user@shopverse.com / 123456
   ============================================================ */

SV.DEMO_USER = { name: 'Demo Customer', email: 'user@shopverse.com', password: '123456', phone: '+1 202 555 0199', address: '128 Market Street, Springfield, CA 94016' };

/* ---------------- LOGIN ---------------- */
SV.loginUser = async function (email, password) {
  await SV.api.delay(600); // simulated AJAX
  const registered = SV.store.get('registered_users', []);
  const match = [SV.DEMO_USER, ...registered].find((u) => u.email.toLowerCase() === email.toLowerCase() && u.password === password);
  if (!match) return { ok: false, message: 'Invalid email or password. Try user@shopverse.com / 123456' };
  const { password: _pw, ...safe } = match;
  return { ok: true, user: { ...safe, loggedAt: new Date().toISOString() } };
};

SV.initLogin = function () {
  const form = document.getElementById('loginForm');
  const btn = document.getElementById('loginBtn');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.classList.add('was-validated'); return; }
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';
    const res = await SV.loginUser(form.email.value, form.password.value);
    btn.disabled = false;
    btn.innerHTML = 'Login';
    if (!res.ok) { SV.toast(res.message, 'danger'); return; }
    SV.store.set('user', res.user);
    if (form.remember.checked) SV.store.set('remember_email', form.email.value);
    SV.toast(`Welcome back, ${res.user.name.split(' ')[0]}!`);
    setTimeout(() => (location.href = 'account.php'), 800);
  });
  document.getElementById('fillDemo').onclick = () => { form.email.value = SV.DEMO_USER.email; form.password.value = SV.DEMO_USER.password; };
  const remembered = SV.store.get('remember_email', '');
  if (remembered) { form.email.value = remembered; form.remember.checked = true; }
  document.getElementById('forgotForm').onsubmit = (e) => {
    e.preventDefault();
    bootstrap.Modal.getInstance(document.getElementById('forgotModal')).hide();
    SV.toast('Password reset link sent to your email');
  };
};

/* ---------------- REGISTER ---------------- */
SV.registerUser = async function (data) {
  await SV.api.delay(700);
  const users = SV.store.get('registered_users', []);
  if (users.some((u) => u.email.toLowerCase() === data.email.toLowerCase()) || data.email === SV.DEMO_USER.email)
    return { ok: false, message: 'An account with this email already exists' };
  users.push(data);
  SV.store.set('registered_users', users);
  return { ok: true };
};

SV.passwordStrength = function (pw) {
  let score = 0;
  if (pw.length >= 6) score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/\d/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    { w: '10%', c: '#ef4444', t: 'Very weak' }, { w: '30%', c: '#f97316', t: 'Weak' },
    { w: '55%', c: '#f59e0b', t: 'Fair' }, { w: '78%', c: '#22c55e', t: 'Good' }, { w: '100%', c: '#16a34a', t: 'Strong' }
  ];
  return levels[Math.max(0, Math.min(score - 1, 4))];
};

SV.initRegister = function () {
  const form = document.getElementById('registerForm');
  const pw = form.password, cpw = form.confirm;
  const bar = document.querySelector('.strength-bar span');
  const label = document.getElementById('strengthLabel');

  pw.addEventListener('input', () => {
    const s = SV.passwordStrength(pw.value);
    bar.style.width = pw.value ? s.w : '0';
    bar.style.background = s.c;
    label.textContent = pw.value ? s.t : '';
    label.style.color = s.c;
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let valid = form.checkValidity();
    if (pw.value !== cpw.value) { cpw.setCustomValidity('nomatch'); valid = false; SV.toast('Passwords do not match', 'danger'); }
    else cpw.setCustomValidity('');
    form.classList.add('was-validated');
    if (!valid) return;

    const btn = document.getElementById('registerBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating account...';
    const res = await SV.registerUser({
      name: form.fullname.value, email: form.email.value, phone: form.phone.value,
      password: pw.value, address: form.address.value
    });
    btn.disabled = false; btn.innerHTML = 'Create Account';
    if (!res.ok) return SV.toast(res.message, 'danger');
    SV.toast('Account created successfully! You can now log in.');
    setTimeout(() => (location.href = 'login.php'), 1000);
  });
};

/* ---------------- GUARD ---------------- */
SV.requireAuth = function () {
  const user = SV.getUser();
  if (!user) { location.href = 'login.php'; return null; }
  return user;
};

/* ---------------- ACCOUNT DASHBOARD (data from account_api.php -> database) ---------------- */
SV.accountApi = async function (action, body) {
  const opt = body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
    : { cache: 'no-store' };
  const res = await fetch((SV.BASE || '') + 'account_api.php?action=' + action, opt);
  if (res.status === 401) { location.href = 'login.php'; throw new Error('Please log in.'); }
  const json = await res.json();
  if (!json.success) throw new Error(json.message || 'Request failed.');
  return json;
};

SV.initAccount = async function () {
  const user = SV.requireAuth();
  if (!user) return;
  const esc = SV.escHtml;
  const cap = (t) => String(t || '').charAt(0).toUpperCase() + String(t || '').slice(1);

  let data;
  try {
    data = (await SV.accountApi('overview')).data;
  } catch (err) {
    document.getElementById('accountStats').innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${esc(err.message)}</div></div>`;
    return;
  }

  const renderAddresses = (list) => {
    document.getElementById('addressList').innerHTML = list.length ? list.map((a) => `
      <div class="col-md-6"><div class="filter-box h-100">
        ${a.is_default ? '<span class="badge text-bg-primary mb-2">Default</span>' : '<span class="badge text-bg-secondary mb-2">Saved</span>'}
        <p class="mb-1 fw-semibold">${esc(a.name)}</p>
        <p class="text-muted-2 small mb-2">${esc(a.address_line)}<br>${esc([a.city, a.state, a.postal_code].filter(Boolean).join(', '))}${a.city ? '' : '<span class="text-danger">City missing</span>'}<br>${esc(a.country)}${a.phone ? ' · ' + esc(a.phone) : ''}</p>
        <button class="btn btn-sm btn-light border" data-edit-address="${a.id}">Edit</button>
      </div></div>`).join('')
      : '<div class="col-12 text-muted-2">No saved addresses yet.</div>';
  };
  let addresses = data.addresses;
  renderAddresses(addresses);

  document.getElementById('userName').textContent = data.user.name;
  document.getElementById('userEmail').textContent = data.user.email;
  document.getElementById('userAvatar').src = `https://picsum.photos/seed/${encodeURIComponent(data.user.email)}/120/120`;

  const st = data.stats;
  const stats = [
    ['Total Orders', st.orders, 'bi-bag-check', 'primary'],
    ['Pending Orders', st.pending, 'bi-hourglass-split', 'warning'],
    ['Completed Orders', st.completed, 'bi-check2-circle', 'success'],
    ['Wishlist Items', st.wishlist, 'bi-heart', 'danger']
  ];
  document.getElementById('accountStats').innerHTML = stats.map((s) => `
    <div class="col-6 col-lg-3">
      <div class="stat-card d-flex align-items-center gap-3">
        <div class="ic text-bg-${s[3]}-subtle text-${s[3]}" style="background:var(--bs-${s[3]}-bg-subtle)"><i class="bi ${s[2]}"></i></div>
        <div><h3>${s[1]}</h3><small class="text-muted-2">${s[0]}</small></div>
      </div>
    </div>`).join('');

  document.getElementById('recentOrders').innerHTML = data.recent_orders.map((o) => `
    <tr>
      <td class="fw-semibold">${esc(o.order_number)}</td><td>${esc(o.date)}</td><td>${o.item_count} item(s)</td>
      <td class="fw-bold">${SV.money(o.total)}</td><td>${SV.statusBadge(cap(o.status))}</td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="orders.php">View</a></td>
    </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted-2 py-4">No orders yet</td></tr>';

  /* Profile */
  const pf = document.getElementById('profileForm');
  pf.elements.fullname.value = data.user.name;
  pf.elements.email.value = data.user.email;
  pf.elements.phone.value = data.user.phone || '';
  pf.elements.address.value = data.default_address_line || '';
  pf.onsubmit = async (e) => {
    e.preventDefault();
    try {
      const r = await SV.accountApi('update_profile', { fullname: pf.elements.fullname.value, phone: pf.elements.phone.value, address: pf.elements.address.value });
      SV.toast(r.message || 'Profile updated');
      setTimeout(() => location.reload(), 700);
    } catch (err) { SV.toast(err.message, 'danger'); }
  };

  /* Password */
  const sf = document.getElementById('settingsForm');
  sf.onsubmit = async (e) => {
    e.preventDefault();
    try {
      const r = await SV.accountApi('change_password', { current: sf.elements.current.value, new: sf.elements.new.value, confirm: sf.elements.confirm.value });
      SV.toast(r.message || 'Password changed');
      sf.reset();
    } catch (err) { SV.toast(err.message, 'danger'); }
  };

  /* Address book */
  const af = document.getElementById('addressForm');
  const modal = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('addressModal'));
  const openAddress = (a) => {
    af.reset();
    af.elements.address_id.value = a ? a.id : '';
    af.elements.address_line.value = a ? a.address_line : '';
    af.elements.city.value = a ? a.city : '';
    af.elements.state.value = a ? a.state : '';
    af.elements.postal_code.value = a ? a.postal_code : '';
    af.elements.country.value = a ? a.country : 'Pakistan';
    af.elements.is_default.checked = a ? a.is_default : addresses.length === 0;
    modal().show();
  };
  document.getElementById('addAddressBtn').onclick = () => openAddress(null);
  document.getElementById('addressList').onclick = (e) => {
    const b = e.target.closest('[data-edit-address]');
    if (b) openAddress(addresses.find((x) => x.id === Number(b.dataset.editAddress)));
  };
  af.onsubmit = async (e) => {
    e.preventDefault();
    try {
      const r = await SV.accountApi('save_address', {
        id: af.elements.address_id.value ? Number(af.elements.address_id.value) : 0,
        address_line: af.elements.address_line.value, city: af.elements.city.value, state: af.elements.state.value,
        postal_code: af.elements.postal_code.value, country: af.elements.country.value, is_default: af.elements.is_default.checked
      });
      addresses = r.addresses;
      renderAddresses(addresses);
      modal().hide();
      SV.toast(r.message || 'Address saved');
    } catch (err) { SV.toast(err.message, 'danger'); }
  };

  /* Wishlist preview */
  document.getElementById('wishPreview').innerHTML = data.wishlist_preview.length
    ? data.wishlist_preview.map((p) => `
      <div class="col-6 col-lg-3">
        <a href="product-details.php?id=${p.id}" class="text-decoration-none text-reset">
          <div class="card h-100 p-2">
            <img src="${esc(p.image || '')}" class="rounded-2 mb-2" style="width:100%;aspect-ratio:1;object-fit:cover" alt="">
            <div class="small fw-semibold text-truncate">${esc(p.name)}</div>
            <div class="small">${SV.money(p.price)}</div>
          </div>
        </a>
      </div>`).join('')
    : '<div class="col-12 text-muted-2">No saved products yet. <a href="shop.php">Browse the shop</a>.</div>';
};

SV.statusBadge = function (status) {
  const map = { Pending: 'warning', Processing: 'info', Shipped: 'primary', Delivered: 'success', Cancelled: 'danger' };
  return `<span class="status-badge badge text-bg-${map[status] || 'secondary'}">${status}</span>`;
};

/* ---------------- ORDERS PAGE ---------------- */
SV.initOrdersPage = async function () {
  const tbody = document.getElementById('ordersBody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';
  const orders = await SV.loadOrders();

  const render = (list) => {
    tbody.innerHTML = list.length ? list.map((o) => `
      <tr>
        <td class="fw-semibold">${o.id}</td>
        <td>${o.date}</td>
        <td><div class="d-flex align-items-center gap-2">
          ${o.items.slice(0, 3).map((i) => `<img src="${i.image}" width="36" height="36" class="rounded-2" style="object-fit:cover" alt="">`).join('')}
          <small class="text-muted-2">${o.items.length} item(s)</small></div></td>
        <td class="fw-bold">${SV.money(o.total)}</td>
        <td><small>${o.payment}</small></td>
        <td>${SV.statusBadge(o.status)}</td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-primary" data-order="${o.id}">Details</button>
          ${['Pending', 'Processing'].includes(o.status) ? `<button class="btn btn-sm btn-outline-danger" data-cancel="${o.id}">Cancel</button>` : ''}
        </td>
      </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted-2 py-5">No orders found</td></tr>';
  };
  render(orders);

  document.getElementById('orderStatusFilter').onchange = (e) => {
    const v = e.target.value;
    render(v === 'all' ? orders : orders.filter((o) => o.status === v));
  };
  document.getElementById('orderSearch').oninput = (e) => {
    const q = e.target.value.toLowerCase();
    render(orders.filter((o) => o.id.toLowerCase().includes(q) || o.customer.toLowerCase().includes(q)));
  };

  tbody.addEventListener('click', (e) => {
    const d = e.target.closest('[data-order]');
    const c = e.target.closest('[data-cancel]');
    if (d) SV.showOrderModal(d.dataset.order);
    if (c) {
      const all = SV.getOrders();
      const o = all.find((x) => x.id === c.dataset.cancel);
      o.status = 'Cancelled'; SV.saveOrders(all);
      SV.toast('Order ' + o.id + ' cancelled', 'warning');
      SV.initOrdersPage();
    }
  });
};

SV.showOrderModal = function (id) {
  const o = SV.getOrders().find((x) => x.id === id);
  if (!o) return;
  document.getElementById('orderModalBody').innerHTML = `
    <div class="row g-3 mb-3">
      <div class="col-md-4"><small class="text-muted-2">Order ID</small><div class="fw-bold">${o.id}</div></div>
      <div class="col-md-4"><small class="text-muted-2">Date</small><div class="fw-bold">${o.date}</div></div>
      <div class="col-md-4"><small class="text-muted-2">Status</small><div>${SV.statusBadge(o.status)}</div></div>
      <div class="col-md-6"><small class="text-muted-2">Customer</small><div class="fw-bold">${o.customer}</div><small>${o.email} · ${o.phone}</small></div>
      <div class="col-md-6"><small class="text-muted-2">Shipping address</small><div>${o.address}</div></div>
    </div>
    <div class="table-responsive"><table class="table align-middle">
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th class="text-end">Total</th></tr></thead>
      <tbody>${o.items.map((i) => `<tr>
        <td><div class="d-flex gap-2 align-items-center"><img src="${i.image}" width="44" height="44" class="rounded-2" style="object-fit:cover" alt=""><span>${i.name}</span></div></td>
        <td>${SV.money(i.price)}</td><td>${i.qty}</td><td class="text-end fw-semibold">${SV.money(i.price * i.qty)}</td></tr>`).join('')}</tbody>
    </table></div>
    <div class="row justify-content-end"><div class="col-md-6">
      <div class="summary-row"><span>Subtotal</span><strong>${SV.money(o.subtotal)}</strong></div>
      <div class="summary-row"><span>Shipping</span><strong>${o.shipping ? SV.money(o.shipping) : 'Free'}</strong></div>
      <div class="summary-row"><span>Tax</span><strong>${SV.money(o.tax)}</strong></div>
      <div class="summary-row summary-total"><span>Total</span><span>${SV.money(o.total)}</span></div>
      <p class="text-muted-2 small mt-2 mb-0">Payment method: <strong>${o.payment}</strong></p>
    </div></div>`;
  bootstrap.Modal.getOrCreateInstance(document.getElementById('orderModal')).show();
};
