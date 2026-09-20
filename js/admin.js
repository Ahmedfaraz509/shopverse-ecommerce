/* ============================================================
   ShopVerse – admin.js
   Admin shell, dashboard charts and CRUD simulation
   (products, categories, orders, customers, reports).
   ============================================================ */

/* ---------------- Admin shell ---------------- */
SV.renderAdminShell = function (active, title, subtitle) {
  const links = [
    ['index.php', 'Dashboard', 'bi-speedometer2', 'dashboard'],
    ['products.php', 'Products', 'bi-box-seam', 'products'],
    ['categories.php', 'Categories', 'bi-grid', 'categories'],
    ['orders.php', 'Orders', 'bi-receipt', 'orders'],
    ['customers.php', 'Customers', 'bi-people', 'customers'],
    ['reports.php', 'Reports', 'bi-graph-up-arrow', 'reports']
  ];
  document.getElementById('adminSidebar').innerHTML = `
    <a href="../index.php" class="sv-logo d-flex align-items-center gap-2 px-2 py-2 mb-3">
      <i class="bi bi-bag-check-fill"></i><span class="text-white">Shop<span style="color:#a5b4fc">Verse</span></span>
    </a>
    <small class="text-uppercase px-2" style="letter-spacing:.1em;font-size:.7rem">Main</small>
    <nav class="mt-2">
      ${links.map((l) => `<a class="side-link ${active === l[3] ? 'active' : ''}" href="${l[0]}"><i class="bi ${l[2]}"></i>${l[1]}</a>`).join('')}
    </nav>
    <small class="text-uppercase px-2 d-block mt-4" style="letter-spacing:.1em;font-size:.7rem">System</small>
    <nav class="mt-2">
      <a class="side-link" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal"><i class="bi bi-gear"></i>Settings</a>
      <a class="side-link" href="../index.php"><i class="bi bi-shop"></i>View Store</a>
      <a class="side-link text-danger" href="#" onclick="SV.adminLogout(event)"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </nav>`;

  document.getElementById('adminTopbar').innerHTML = `
    <div class="d-flex align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-light border d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
        <div><h5 class="mb-0">${title}</h5><small class="text-muted-2">${subtitle || ''}</small></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="input-group d-none d-md-flex" style="width:250px">
          <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
          <input class="form-control border-start-0" placeholder="Search admin..." id="adminGlobalSearch">
        </div>
        <button class="sv-icon-btn" data-bs-toggle="dropdown"><i class="bi bi-bell"></i><span class="sv-count">3</span></button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><h6 class="dropdown-header">Notifications</h6></li>
          <li><span class="dropdown-item-text small"><i class="bi bi-bag-check text-success me-2"></i>New order SV-24580 received</span></li>
          <li><span class="dropdown-item-text small"><i class="bi bi-exclamation-triangle text-warning me-2"></i>3 products low on stock</span></li>
          <li><span class="dropdown-item-text small"><i class="bi bi-person-plus text-primary me-2"></i>2 new customers registered</span></li>
        </ul>
        <div class="dropdown">
          <button class="btn btn-light border d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <img src="https://picsum.photos/seed/svadmin/60/60" class="rounded-circle" width="28" height="28" alt="">
            <span class="d-none d-sm-inline small fw-semibold">Admin</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#settingsModal">Settings</a></li>
            <li><a class="dropdown-item" href="../index.php">Storefront</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#" onclick="SV.adminLogout(event)">Logout</a></li>
          </ul>
        </div>
      </div>
    </div>`;

  // Settings modal (shared across admin pages)
  document.body.insertAdjacentHTML('beforeend', `
  <div class="modal fade" id="settingsModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered">
    <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Store Settings</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form onsubmit="event.preventDefault();SV.toast('Settings saved');bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label">Store name</label><input class="form-control" value="ShopVerse"></div>
          <div class="mb-3"><label class="form-label">Support email</label><input type="email" class="form-control" value="help@shopverse.com"></div>
          <div class="mb-3"><label class="form-label">Currency</label><select class="form-select"><option>USD ($)</option><option>EUR (€)</option><option>GBP (£)</option></select></div>
          <div class="form-check form-switch"><input class="form-check-input" type="checkbox" checked id="maint"><label class="form-check-label" for="maint">Enable order notifications</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div>
      </form></div></div></div>`);

  const toggle = document.getElementById('sidebarToggle');
  if (toggle) toggle.onclick = () => {
    const sb = document.getElementById('adminSidebar');
    sb.classList.add('open');
    const bd = document.createElement('div');
    bd.className = 'admin-backdrop';
    bd.onclick = () => { sb.classList.remove('open'); bd.remove(); };
    document.body.appendChild(bd);
  };
  const gs = document.getElementById('adminGlobalSearch');
  if (gs) gs.addEventListener('keydown', (e) => { if (e.key === 'Enter') SV.toast(`Searching for "${gs.value}"...`, 'info'); });
};

SV.adminLogout = function (e) {
  e.preventDefault();
  SV.toast('Signing out of admin panel...', 'info');
  setTimeout(() => (location.href = '../logout.php'), 500);
};

/* ---------------- Admin data store (CRUD) ---------------- */
SV.adminProducts = function () {
  const stored = SV.store.get('admin_products', null);
  if (stored) return stored;
  SV.store.set('admin_products', SV.PRODUCTS);
  return SV.PRODUCTS;
};
SV.saveAdminProducts = (p) => SV.store.set('admin_products', p);
SV.adminCategories = function () {
  const stored = SV.store.get('admin_categories', null);
  if (stored) return stored;
  SV.store.set('admin_categories', SV.CATEGORIES);
  return SV.CATEGORIES;
};
SV.saveAdminCategories = (c) => SV.store.set('admin_categories', c);

/* ---------------- Dashboard ---------------- */
SV.initAdminDashboard = async function () {
  const orders = SV.getOrders();
  const products = SV.adminProducts();
  const sales = orders.filter((o) => o.status !== 'Cancelled').reduce((s, o) => s + o.total, 0);
  const customers = [...new Set(orders.map((o) => o.email))];

  const cards = [
    ['Total Sales', SV.money(sales), 'bi-currency-dollar', 'primary', '+12.5% vs last month'],
    ['Total Orders', orders.length, 'bi-receipt', 'success', '+8 new this week'],
    ['Total Customers', customers.length, 'bi-people', 'warning', '+3 new customers'],
    ['Total Products', products.length, 'bi-box-seam', 'info', `${products.filter((p) => p.stock < 20).length} low in stock`]
  ];
  document.getElementById('adminStats').innerHTML = cards.map((c) => `
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card">
        <div class="d-flex justify-content-between align-items-start">
          <div><small class="text-muted-2">${c[0]}</small><h3 class="mt-1">${c[1]}</h3></div>
          <div class="ic text-${c[3]}" style="background:var(--bs-${c[3]}-bg-subtle)"><i class="bi ${c[2]}"></i></div>
        </div>
        <small class="text-success"><i class="bi bi-graph-up-arrow me-1"></i>${c[4]}</small>
      </div>
    </div>`).join('');

  // Recent orders
  document.getElementById('recentOrdersBody').innerHTML = orders.slice(0, 6).map((o) => `
    <tr><td class="fw-semibold">${o.id}</td><td>${o.customer}</td><td>${o.date}</td>
    <td class="fw-bold">${SV.money(o.total)}</td><td>${SV.statusBadge(o.status)}</td></tr>`).join('');

  // Top products
  document.getElementById('topProducts').innerHTML = [...products].sort((a, b) => b.sold - a.sold).slice(0, 5).map((p) => `
    <div class="d-flex align-items-center gap-3 py-2 border-bottom">
      <img src="${p.images[0]}" width="44" height="44" class="rounded-2" style="object-fit:cover" alt="">
      <div class="flex-grow-1"><div class="small fw-semibold">${p.name.slice(0, 34)}</div><small class="text-muted-2">${p.sold} sold</small></div>
      <strong class="small">${SV.money(p.price)}</strong>
    </div>`).join('');

  // Low stock
  document.getElementById('lowStock').innerHTML = [...products].sort((a, b) => a.stock - b.stock).slice(0, 5).map((p) => `
    <tr><td><div class="d-flex gap-2 align-items-center"><img src="${p.images[0]}" width="34" height="34" class="rounded-2" style="object-fit:cover" alt="">
      <span class="small">${p.name.slice(0, 30)}</span></div></td>
      <td><span class="badge text-bg-${p.stock < 10 ? 'danger' : 'warning'}">${p.stock} left</span></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="products.php">Restock</a></td></tr>`).join('');

  SV.drawSalesChart('salesChart');
  SV.drawCategoryChart('categoryChart');
};

/* ---------------- Charts (Chart.js via CDN) ---------------- */
SV.drawSalesChart = function (id, type = 'line') {
  const el = document.getElementById(id);
  if (!el || typeof Chart === 'undefined') return;
  const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const data = [8200, 9400, 11200, 10400, 13900, 15200, 14100, 16800, 18250, 17600, 21400, 24800];
  const ctx = el.getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 300);
  grad.addColorStop(0, 'rgba(79,70,229,.35)');
  grad.addColorStop(1, 'rgba(79,70,229,0)');
  new Chart(ctx, {
    type,
    data: { labels, datasets: [
      { label: 'Revenue ($)', data, borderColor: '#4f46e5', backgroundColor: grad, fill: true, tension: .38, borderWidth: 3, pointRadius: 3, pointBackgroundColor: '#4f46e5' },
      { label: 'Orders', data: data.map((d) => Math.round(d / 120)), borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,.15)', fill: false, tension: .38, borderWidth: 2, pointRadius: 2 }
    ] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true, grid: { color: '#eef2f7' } }, x: { grid: { display: false } } } }
  });
};

SV.drawCategoryChart = function (id) {
  const el = document.getElementById(id);
  if (!el || typeof Chart === 'undefined') return;
  const products = SV.adminProducts();
  const cats = SV.adminCategories();
  new Chart(el, {
    type: 'doughnut',
    data: {
      labels: cats.map((c) => c.name),
      datasets: [{ data: cats.map((c) => products.filter((p) => p.category === c.name).reduce((s, p) => s + p.sold, 0)),
        backgroundColor: ['#4f46e5', '#7c3aed', '#f59e0b', '#16a34a', '#0ea5e9', '#ef4444', '#64748b', '#db2777'], borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right', labels: { boxWidth: 12 } } } }
  });
};

/* ---------------- Admin: Products CRUD ---------------- */
SV.initAdminProducts = function () {
  const tbody = document.getElementById('productsBody');
  let editingId = null;

  const render = (list) => {
    tbody.innerHTML = list.length ? list.map((p) => `
      <tr>
        <td><div class="d-flex align-items-center gap-2">
          <img src="${p.images[0]}" width="46" height="46" class="rounded-2" style="object-fit:cover" alt="">
          <div><div class="fw-semibold small">${p.name}</div><small class="text-muted-2">${p.sku}</small></div></div></td>
        <td><span class="badge text-bg-light border">${p.category}</span></td>
        <td class="fw-semibold">${SV.money(p.price)}</td>
        <td><span class="badge text-bg-${p.stock < 10 ? 'danger' : p.stock < 25 ? 'warning' : 'success'}">${p.stock}</span></td>
        <td>${p.status === 'Active' ? '<span class="status-badge badge text-bg-success">Active</span>' : '<span class="status-badge badge text-bg-secondary">Inactive</span>'}</td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-light border" data-view="${p.id}" title="View"><i class="bi bi-eye"></i></button>
          <button class="btn btn-sm btn-light border text-primary" data-edit="${p.id}" title="Edit"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-light border text-danger" data-del="${p.id}" title="Delete"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted-2 py-5">No products found</td></tr>';
  };

  const refresh = () => {
    const q = document.getElementById('prodSearch').value.toLowerCase();
    const cat = document.getElementById('prodCatFilter').value;
    render(SV.adminProducts().filter((p) => (!q || p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)) && (cat === 'all' || p.category === cat)));
  };

  document.getElementById('prodCatFilter').innerHTML = '<option value="all">All categories</option>' +
    SV.adminCategories().map((c) => `<option>${c.name}</option>`).join('');
  document.getElementById('prodCategoryInput').innerHTML = SV.adminCategories().map((c) => `<option>${c.name}</option>`).join('');
  refresh();

  document.getElementById('prodSearch').oninput = refresh;
  document.getElementById('prodCatFilter').onchange = refresh;

  document.getElementById('addProductBtn').onclick = () => {
    editingId = null;
    const f = document.getElementById('productForm');
    f.reset(); f.classList.remove('was-validated');
    document.getElementById('productModalTitle').textContent = 'Add New Product';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
  };

  tbody.addEventListener('click', (e) => {
    const v = e.target.closest('[data-view]'), ed = e.target.closest('[data-edit]'), dl = e.target.closest('[data-del]');
    const items = SV.adminProducts();
    if (v) {
      const p = items.find((x) => x.id === +v.dataset.view);
      document.getElementById('viewBody').innerHTML = `
        <div class="row g-3"><div class="col-md-5"><img src="${p.images[0]}" class="img-fluid rounded-3" alt=""></div>
        <div class="col-md-7"><h5>${p.name}</h5><p class="text-muted-2 small">${p.description}</p>
        <table class="table table-sm"><tbody>
          <tr><th>SKU</th><td>${p.sku}</td></tr><tr><th>Category</th><td>${p.category}</td></tr>
          <tr><th>Price</th><td>${SV.money(p.price)} <s class="text-muted-2">${SV.money(p.oldPrice)}</s></td></tr>
          <tr><th>Stock</th><td>${p.stock}</td></tr><tr><th>Rating</th><td>${p.rating} (${p.reviews} reviews)</td></tr>
          <tr><th>Status</th><td>${p.status}</td></tr></tbody></table></div></div>`;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('viewModal')).show();
    }
    if (ed) {
      const p = items.find((x) => x.id === +ed.dataset.edit);
      editingId = p.id;
      const f = document.getElementById('productForm');
      f.name.value = p.name; f.category.value = p.category; f.price.value = p.price;
      f.oldPrice.value = p.oldPrice; f.stock.value = p.stock; f.status.value = p.status;
      f.image.value = p.images[0]; f.description.value = p.description;
      document.getElementById('productModalTitle').textContent = 'Edit Product';
      bootstrap.Modal.getOrCreateInstance(document.getElementById('productModal')).show();
    }
    if (dl) {
      const p = items.find((x) => x.id === +dl.dataset.del);
      if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return;
      SV.saveAdminProducts(items.filter((x) => x.id !== p.id));
      SV.toast('Product deleted', 'warning'); refresh();
    }
  });

  document.getElementById('productForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target;
    f.classList.add('was-validated');
    if (!f.checkValidity()) return;
    const btn = document.getElementById('saveProductBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
    await SV.api.delay(600); // simulated AJAX POST
    const items = SV.adminProducts();
    const payload = {
      name: f.name.value, category: f.category.value, price: +f.price.value, oldPrice: +f.oldPrice.value || +f.price.value,
      stock: +f.stock.value, status: f.status.value, description: f.description.value
    };
    payload.discount = payload.oldPrice > payload.price ? Math.round(((payload.oldPrice - payload.price) / payload.oldPrice) * 100) : 0;
    if (editingId) {
      const p = items.find((x) => x.id === editingId);
      Object.assign(p, payload);
      if (f.image.value) p.images[0] = f.image.value;
      SV.toast('Product updated successfully');
    } else {
      const id = Math.max(...items.map((x) => x.id)) + 1;
      const image = f.image.value || `https://picsum.photos/seed/svnew${id}/800/800`;
      items.unshift({ id, sku: 'SV-' + (1000 + id), brand: 'ShopVerse', rating: 4.5, reviews: 0, sold: 0, badge: 'New',
        createdAt: new Date().toISOString().slice(0, 10), images: [image, image, image, image],
        specs: { Brand: 'ShopVerse', Model: 'SV-' + (1000 + id), Category: payload.category, Warranty: '24 months', Shipping: 'Free over $99', Returns: '30 days' }, ...payload });
      SV.toast('Product added successfully');
    }
    SV.saveAdminProducts(items);
    btn.disabled = false; btn.innerHTML = 'Save Product';
    bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
    refresh();
  };
};

/* ---------------- Admin: Categories CRUD ---------------- */
SV.initAdminCategories = function () {
  const tbody = document.getElementById('catBody');
  let editingId = null;
  const render = () => {
    const products = SV.adminProducts();
    tbody.innerHTML = SV.adminCategories().map((c) => `
      <tr>
        <td><div class="d-flex align-items-center gap-2">
          <span class="ic d-inline-flex align-items-center justify-content-center rounded-3" style="width:38px;height:38px;background:var(--sv-primary-soft);color:var(--sv-primary)"><i class="bi ${c.icon || 'bi-tag'}"></i></span>
          <strong class="small">${c.name}</strong></div></td>
        <td class="small text-muted-2">${c.description}</td>
        <td><span class="badge text-bg-light border">${products.filter((p) => p.category === c.name).length}</span></td>
        <td><span class="status-badge badge text-bg-${c.status === 'Active' ? 'success' : 'secondary'}">${c.status}</span></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-light border" href="../shop.php?category=${encodeURIComponent(c.name)}" title="View"><i class="bi bi-eye"></i></a>
          <button class="btn btn-sm btn-light border text-primary" data-edit="${c.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-light border text-danger" data-del="${c.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('');
  };
  render();

  document.getElementById('addCatBtn').onclick = () => {
    editingId = null;
    const f = document.getElementById('catForm'); f.reset(); f.classList.remove('was-validated');
    document.getElementById('catModalTitle').textContent = 'Add Category';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('catModal')).show();
  };
  tbody.addEventListener('click', (e) => {
    const ed = e.target.closest('[data-edit]'), dl = e.target.closest('[data-del]');
    const cats = SV.adminCategories();
    if (ed) {
      const c = cats.find((x) => x.id === +ed.dataset.edit);
      editingId = c.id;
      const f = document.getElementById('catForm');
      f.name.value = c.name; f.description.value = c.description; f.status.value = c.status; f.icon.value = c.icon || '';
      document.getElementById('catModalTitle').textContent = 'Edit Category';
      bootstrap.Modal.getOrCreateInstance(document.getElementById('catModal')).show();
    }
    if (dl) {
      const c = cats.find((x) => x.id === +dl.dataset.del);
      if (!confirm(`Delete category "${c.name}"?`)) return;
      SV.saveAdminCategories(cats.filter((x) => x.id !== c.id));
      SV.toast('Category deleted', 'warning'); render();
    }
  });
  document.getElementById('catForm').onsubmit = async (e) => {
    e.preventDefault();
    const f = e.target; f.classList.add('was-validated');
    if (!f.checkValidity()) return;
    await SV.api.delay(450);
    const cats = SV.adminCategories();
    if (editingId) {
      Object.assign(cats.find((x) => x.id === editingId), { name: f.name.value, description: f.description.value, status: f.status.value, icon: f.icon.value || 'bi-tag' });
      SV.toast('Category updated');
    } else {
      const id = Math.max(0, ...cats.map((c) => c.id)) + 1;
      cats.push({ id, name: f.name.value, slug: f.name.value.toLowerCase().replace(/\s+/g, '-'), description: f.description.value, status: f.status.value, icon: f.icon.value || 'bi-tag', image: `https://picsum.photos/seed/svcat${id}/800/600` });
      SV.toast('Category added');
    }
    SV.saveAdminCategories(cats);
    bootstrap.Modal.getInstance(document.getElementById('catModal')).hide();
    render();
  };
};

/* ---------------- Admin: Orders ---------------- */
SV.initAdminOrders = function () {
  const tbody = document.getElementById('adminOrdersBody');
  const statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
  const render = () => {
    const q = document.getElementById('orderSearchAdmin').value.toLowerCase();
    const fs = document.getElementById('orderFilterAdmin').value;
    const list = SV.getOrders().filter((o) => (!q || o.id.toLowerCase().includes(q) || o.customer.toLowerCase().includes(q)) && (fs === 'all' || o.status === fs));
    tbody.innerHTML = list.length ? list.map((o) => `
      <tr>
        <td class="fw-semibold">${o.id}</td>
        <td><div class="small fw-semibold">${o.customer}</div><small class="text-muted-2">${o.email}</small></td>
        <td>${o.date}</td><td class="fw-bold">${SV.money(o.total)}</td>
        <td><small>${o.payment}</small></td>
        <td><select class="form-select form-select-sm" data-status="${o.id}" style="min-width:130px">
          ${statuses.map((s) => `<option ${s === o.status ? 'selected' : ''}>${s}</option>`).join('')}</select></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-light border" data-view="${o.id}"><i class="bi bi-eye"></i></button>
          <button class="btn btn-sm btn-light border text-danger" data-del="${o.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted-2 py-5">No orders found</td></tr>';
  };
  render();
  document.getElementById('orderSearchAdmin').oninput = render;
  document.getElementById('orderFilterAdmin').onchange = render;

  tbody.addEventListener('change', async (e) => {
    const sel = e.target.closest('[data-status]');
    if (!sel) return;
    await SV.api.delay(250);
    const orders = SV.getOrders();
    orders.find((o) => o.id === sel.dataset.status).status = sel.value;
    SV.saveOrders(orders);
    SV.toast(`Order ${sel.dataset.status} marked as <strong>${sel.value}</strong>`);
    render();
  });
  tbody.addEventListener('click', (e) => {
    const v = e.target.closest('[data-view]'), d = e.target.closest('[data-del]');
    if (v) SV.showOrderModal(v.dataset.view);
    if (d) {
      if (!confirm('Delete order ' + d.dataset.del + '?')) return;
      SV.saveOrders(SV.getOrders().filter((o) => o.id !== d.dataset.del));
      SV.toast('Order deleted', 'warning'); render();
    }
  });
};

/* ---------------- Admin: Customers (data from admin/customers_api.php -> database) ---------------- */
SV.initAdminCustomers = async function () {
  const esc = SV.escHtml;
  const cap = (t) => String(t || '').charAt(0).toUpperCase() + String(t || '').slice(1);
  const tbody = document.getElementById('customersBody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div></td></tr>';

  const call = async (qs) => {
    const res = await fetch('customers_api.php?' + qs, { cache: 'no-store' });
    if (res.status === 401) { location.href = '../login.php'; throw new Error('Please log in.'); }
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json.data;
  };

  let data;
  try {
    data = await call('action=list');
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${esc(err.message)}</td></tr>`;
    return;
  }
  const customers = data.items;
  const stats = data.stats;

  const render = (list) => {
    tbody.innerHTML = list.length ? list.map((c) => `
      <tr>
        <td><div class="d-flex align-items-center gap-2">
          <img src="https://picsum.photos/seed/${encodeURIComponent(c.email)}/80/80" class="rounded-circle" width="38" height="38" alt="">
          <div><div class="fw-semibold small">${esc(c.name)}</div><small class="text-muted-2">Customer #${c.id}</small></div></div></td>
        <td class="small">${esc(c.email)}</td><td class="small">${esc(c.phone) || '—'}</td>
        <td><span class="badge text-bg-light border">${c.orders}</span></td>
        <td class="fw-bold">${SV.money(c.spent)}</td>
        <td><span class="status-badge badge text-bg-${c.status !== 'active' ? 'danger' : (c.vip ? 'primary' : 'success')}">${c.status !== 'active' ? cap(c.status) : (c.vip ? 'VIP' : 'Active')}</span></td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-light border" data-cust="${c.id}"><i class="bi bi-eye"></i></button>
          <a class="btn btn-sm btn-light border" href="mailto:${esc(c.email)}"><i class="bi bi-envelope"></i></a>
        </td>
      </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted-2 py-4">No customers found</td></tr>';
  };
  render(customers);

  document.getElementById('custSearch').oninput = (e) => {
    const q = e.target.value.toLowerCase();
    render(customers.filter((c) => c.name.toLowerCase().includes(q) || c.email.toLowerCase().includes(q)));
  };
  document.getElementById('custStats').innerHTML = [
    ['Total Customers', stats.total, 'bi-people', 'primary'],
    ['VIP Customers', stats.vip, 'bi-award', 'warning'],
    ['Avg. Spend', SV.money(stats.avg_spend), 'bi-cash-stack', 'success'],
    ['Total Revenue', SV.money(stats.revenue), 'bi-graph-up', 'info']
  ].map((c) => `<div class="col-6 col-xl-3"><div class="stat-card d-flex align-items-center gap-3">
      <div class="ic text-${c[3]}" style="background:var(--bs-${c[3]}-bg-subtle)"><i class="bi ${c[2]}"></i></div>
      <div><h3>${c[1]}</h3><small class="text-muted-2">${c[0]}</small></div></div></div>`).join('');

  tbody.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-cust]');
    if (!b) return;
    let c;
    try { c = await call('action=get&id=' + encodeURIComponent(b.dataset.cust)); }
    catch (err) { return alert(err.message); }
    const addr = c.addresses.find((a) => a.is_default) || c.addresses[0];
    document.getElementById('custModalBody').innerHTML = `
      <div class="d-flex align-items-center gap-3 mb-3">
        <img src="https://picsum.photos/seed/${encodeURIComponent(c.email)}/120/120" class="rounded-circle" width="64" height="64" alt="">
        <div><h5 class="mb-0">${esc(c.name)}</h5><small class="text-muted-2">${esc(c.email)} · ${esc(c.phone) || '—'}</small>
        <div><small class="text-muted-2">Joined ${esc(c.joined)}${c.last_login ? ' · last login ' + esc(c.last_login) : ''}${c.verified ? '' : ' · email not verified'}</small></div></div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-4"><div class="filter-box text-center py-2"><h5 class="mb-0">${c.order_count}</h5><small class="text-muted-2">Orders</small></div></div>
        <div class="col-4"><div class="filter-box text-center py-2"><h5 class="mb-0">${SV.money(c.spent)}</h5><small class="text-muted-2">Spent</small></div></div>
        <div class="col-4"><div class="filter-box text-center py-2"><h5 class="mb-0">${esc(c.last_order || '—')}</h5><small class="text-muted-2">Last order</small></div></div>
      </div>
      ${addr ? `<p class="small mb-3"><strong>Address:</strong> ${esc([addr.address_line, addr.city, addr.state, addr.postal_code, addr.country].filter(Boolean).join(', '))}</p>` : ''}
      <table class="table table-sm"><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
      <tbody>${c.orders.map((o) => `<tr><td>${esc(o.order_number)}</td><td>${esc(o.date)}</td><td>${SV.money(o.total)}</td><td>${SV.statusBadge(cap(o.status))}</td></tr>`).join('') || '<tr><td colspan="4" class="text-muted-2">No orders</td></tr>'}</tbody></table>`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('custModal')).show();
  });
};

/* ---------------- Admin: Reports ---------------- */
SV.initAdminReports = function () {
  const orders = SV.getOrders();
  const revenue = orders.filter((o) => o.status !== 'Cancelled').reduce((s, o) => s + o.total, 0);
  document.getElementById('reportStats').innerHTML = [
    ['Revenue (YTD)', SV.money(revenue * 6.4), 'bi-cash-coin', 'primary'],
    ['Orders (YTD)', orders.length * 14, 'bi-receipt', 'success'],
    ['Conversion Rate', '4.7%', 'bi-bullseye', 'warning'],
    ['Avg. Order Value', SV.money(revenue / (orders.length || 1)), 'bi-basket', 'info']
  ].map((c) => `<div class="col-6 col-xl-3"><div class="stat-card">
      <div class="d-flex justify-content-between"><div><small class="text-muted-2">${c[0]}</small><h3 class="mt-1">${c[1]}</h3></div>
      <div class="ic text-${c[3]}" style="background:var(--bs-${c[3]}-bg-subtle)"><i class="bi ${c[2]}"></i></div></div></div></div>`).join('');

  SV.drawSalesChart('reportSales');
  SV.drawCategoryChart('reportCategory');

  // Status breakdown bar chart
  const el = document.getElementById('reportStatus');
  if (el && typeof Chart !== 'undefined') {
    const statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    new Chart(el, {
      type: 'bar',
      data: { labels: statuses, datasets: [{ label: 'Orders', data: statuses.map((s) => orders.filter((o) => o.status === s).length),
        backgroundColor: ['#f59e0b', '#0ea5e9', '#4f46e5', '#16a34a', '#ef4444'], borderRadius: 8 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: '#eef2f7' } }, x: { grid: { display: false } } } }
    });
  }

  document.getElementById('topSellingBody').innerHTML = [...SV.adminProducts()].sort((a, b) => b.sold - a.sold).slice(0, 8).map((p, i) => `
    <tr><td>${i + 1}</td>
      <td><div class="d-flex gap-2 align-items-center"><img src="${p.images[0]}" width="36" height="36" class="rounded-2" style="object-fit:cover" alt=""><span class="small">${p.name}</span></div></td>
      <td>${p.category}</td><td>${p.sold}</td><td class="fw-bold">${SV.money(p.sold * p.price)}</td></tr>`).join('');

  document.getElementById('exportBtn').onclick = () => SV.toast('Report exported as CSV (simulated)', 'info');
  document.getElementById('rangeSelect').onchange = (e) => SV.toast(`Report range: ${e.target.value}`, 'info');
};
