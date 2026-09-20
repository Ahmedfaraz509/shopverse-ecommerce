/* ============================================================
   ShopVerse – app.js
   Core application: dummy data, AJAX-style API, storage helpers,
   shared header/footer components, toasts & utilities.
   ============================================================ */

const SV = (window.SV = window.SV || {});

/* ------------------------------------------------------------
   1. DUMMY DATA (fallback used when local JSON cannot be fetched)
   ------------------------------------------------------------ */
const img = (seed) => `https://picsum.photos/seed/${seed}/800/800`;

SV.CATEGORIES = [
  { id: 1, name: 'Electronics', slug: 'electronics', icon: 'bi-cpu', description: 'Phones, laptops, audio & smart gadgets', status: 'Active', image: img('sv-cat-electronics') },
  { id: 2, name: 'Fashion', slug: 'fashion', icon: 'bi-bag-heart', description: 'Clothing, footwear and accessories', status: 'Active', image: img('sv-cat-fashion') },
  { id: 3, name: 'Home & Living', slug: 'home-living', icon: 'bi-house-door', description: 'Furniture, decor and kitchen essentials', status: 'Active', image: img('sv-cat-home') },
  { id: 4, name: 'Beauty', slug: 'beauty', icon: 'bi-flower1', description: 'Skincare, fragrance and cosmetics', status: 'Active', image: img('sv-cat-beauty') },
  { id: 5, name: 'Sports', slug: 'sports', icon: 'bi-bicycle', description: 'Fitness gear, outdoor and activewear', status: 'Active', image: img('sv-cat-sports') },
  { id: 6, name: 'Watches', slug: 'watches', icon: 'bi-smartwatch', description: 'Luxury, classic and smart watches', status: 'Active', image: img('sv-cat-watches') },
  { id: 7, name: 'Footwear', slug: 'footwear', icon: 'bi-shop', description: 'Sneakers, boots, formal & casual shoes', status: 'Active', image: img('sv-cat-footwear') },
  { id: 8, name: 'Accessories', slug: 'accessories', icon: 'bi-handbag', description: 'Bags, wallets, eyewear and jewellery', status: 'Active', image: img('sv-cat-accessories') }
];

const rawProducts = [
  ['Aurora Wireless Noise-Cancelling Headphones', 'Electronics', 189, 249, 4.8, 214, 34, 'Best Seller', 980],
  ['Pulse Pro Smartphone 256GB', 'Electronics', 749, 899, 4.7, 512, 18, 'Hot', 1420],
  ['Nimbus 14" Ultrabook Laptop', 'Electronics', 1099, 1299, 4.6, 187, 9, '', 640],
  ['SoundOrb Portable Bluetooth Speaker', 'Electronics', 59, 89, 4.4, 341, 80, 'Sale', 1210],
  ['Merino Oversized Knit Sweater', 'Fashion', 79, 119, 4.5, 96, 42, 'New', 380],
  ['Classic Denim Jacket – Washed Blue', 'Fashion', 89, 129, 4.6, 158, 26, '', 520],
  ['Linen Blend Summer Shirt', 'Fashion', 45, 65, 4.3, 74, 60, 'Sale', 290],
  ['Tailored Wool Overcoat', 'Fashion', 219, 299, 4.9, 61, 12, 'Premium', 180],
  ['Scandi Oak Lounge Chair', 'Home & Living', 329, 429, 4.7, 88, 7, '', 140],
  ['Ceramic Pour-Over Coffee Set', 'Home & Living', 54, 72, 4.5, 133, 55, 'New', 410],
  ['Aroma Soy Candle Trio', 'Home & Living', 34, 49, 4.4, 201, 120, 'Sale', 760],
  ['Velvet Accent Cushion Pack', 'Home & Living', 39, 55, 4.2, 67, 95, '', 300],
  ['Glow Ritual Vitamin C Serum', 'Beauty', 42, 59, 4.8, 388, 140, 'Best Seller', 1650],
  ['Hydra Silk Facial Moisturiser', 'Beauty', 36, 48, 4.6, 173, 88, '', 520],
  ['Noir Eau de Parfum 100ml', 'Beauty', 96, 135, 4.7, 142, 31, 'Hot', 470],
  ['FlexCore Adjustable Dumbbell Set', 'Sports', 249, 319, 4.6, 118, 15, '', 260],
  ['AeroGrip Yoga Mat Pro', 'Sports', 48, 69, 4.5, 259, 110, 'Sale', 830],
  ['TrailRunner GPS Sports Watch', 'Sports', 179, 229, 4.7, 204, 24, 'New', 590],
  ['Heritage Automatic Steel Watch', 'Watches', 289, 399, 4.9, 97, 8, 'Premium', 210],
  ['Minimalist Mesh Strap Watch', 'Watches', 119, 159, 4.4, 156, 45, '', 340],
  ['CloudStep Running Sneakers', 'Footwear', 109, 149, 4.6, 421, 63, 'Best Seller', 1320],
  ['Rugged Leather Chelsea Boots', 'Footwear', 159, 219, 4.7, 112, 21, '', 300],
  ['Urban Canvas Backpack 22L', 'Accessories', 69, 95, 4.5, 288, 70, 'Sale', 910],
  ['Polarised Aviator Sunglasses', 'Accessories', 79, 109, 4.6, 164, 52, 'New', 480]
];

SV.PRODUCTS = rawProducts.map((p, i) => {
  const id = i + 1;
  const [name, category, price, oldPrice, rating, reviews, stock, badge, sold] = p;
  return {
    id, name, category, price, oldPrice, rating, reviews, stock, badge, sold,
    sku: 'SV-' + String(1000 + id),
    brand: ['ShopVerse', 'Aurora', 'Nimbus', 'Heritage', 'UrbanLab'][id % 5],
    status: stock > 0 ? 'Active' : 'Out of stock',
    createdAt: new Date(Date.now() - id * 86400000 * 3).toISOString().slice(0, 10),
    discount: Math.round(((oldPrice - price) / oldPrice) * 100),
    images: [img('svp' + id + 'a'), img('svp' + id + 'b'), img('svp' + id + 'c'), img('svp' + id + 'd')],
    description: `The ${name} blends premium materials with everyday practicality. Engineered by our design team and tested by thousands of ShopVerse customers, it delivers reliable performance, a refined finish and outstanding value. Every unit ships with a 2-year warranty and free returns within 30 days.`,
    specs: {
      Brand: ['ShopVerse', 'Aurora', 'Nimbus', 'Heritage', 'UrbanLab'][id % 5],
      Model: 'SV-' + String(1000 + id),
      Category: category,
      Warranty: '24 months',
      Shipping: 'Free over $99',
      Returns: '30 days'
    }
  };
});

SV.REVIEWS = [
  { name: 'Amelia Carter', role: 'Verified Buyer', rating: 5, text: 'Delivery was quicker than promised and the packaging was beautiful. The product quality genuinely exceeded what I paid for.', avatar: img('rev1') },
  { name: 'Daniel Okafor', role: 'Verified Buyer', rating: 5, text: 'I have ordered four times now. Consistent quality, fair pricing and their support team replies within minutes.', avatar: img('rev2') },
  { name: 'Sofia Rossi', role: 'Verified Buyer', rating: 4, text: 'Great shopping experience overall. The size guide was accurate and the free returns policy gave me confidence to buy.', avatar: img('rev3') },
  { name: 'Liam Patel', role: 'Verified Buyer', rating: 5, text: 'ShopVerse has become my default store for electronics. The order tracking is clear and honest.', avatar: img('rev4') }
];

/* ------------------------------------------------------------
   2. STORAGE HELPERS
   ------------------------------------------------------------ */
SV.store = {
  get(key, fallback) {
    try { const v = localStorage.getItem('sv_' + key); return v ? JSON.parse(v) : fallback; }
    catch (e) { return fallback; }
  },
  set(key, value) { localStorage.setItem('sv_' + key, JSON.stringify(value)); },
  remove(key) { localStorage.removeItem('sv_' + key); }
};

/* ------------------------------------------------------------
   3. AJAX-STYLE API LAYER
   Tries fetch() on local JSON, falls back to in-memory data,
   and exposes an XMLHttpRequest variant for demonstration.
   ------------------------------------------------------------ */
SV.api = {
  delay: (ms = 350) => new Promise((r) => setTimeout(r, ms)),

  /** fetch() based request with graceful fallback */
  async request(resource, fallbackData) {
    await SV.api.delay();
    try {
      const res = await fetch(`data/${resource}.json`, { cache: 'no-store' });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return await res.json();
    } catch (err) {
      // file:// protocol or missing file -> use bundled dummy data
      return fallbackData;
    }
  },

  /** XMLHttpRequest variant (kept to show classic AJAX usage) */
  xhrRequest(resource, fallbackData) {
    return new Promise((resolve) => {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', `data/${resource}.json`, true);
      xhr.onload = () => {
        try { resolve(xhr.status === 200 ? JSON.parse(xhr.responseText) : fallbackData); }
        catch (e) { resolve(fallbackData); }
      };
      xhr.onerror = () => resolve(fallbackData);
      try { xhr.send(); } catch (e) { resolve(fallbackData); }
    });
  }
};

/** Public AJAX-style functions */
SV.loadProducts = () => SV.api.request('products', SV.PRODUCTS);
SV.loadCategories = () => SV.api.xhrRequest('categories', SV.CATEGORIES);
SV.loadOrders = async () => { await SV.api.delay(250); return SV.getOrders(); };

/* ------------------------------------------------------------
   4. UTILITIES
   ------------------------------------------------------------ */
SV.money = (n) => '$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
SV.qs = (key) => new URLSearchParams(location.search).get(key);

SV.stars = (rating) => {
  let html = '';
  for (let i = 1; i <= 5; i++) {
    html += `<i class="bi ${rating >= i ? 'bi-star-fill' : rating >= i - .5 ? 'bi-star-half' : 'bi-star'}"></i>`;
  }
  return `<span class="stars">${html}</span>`;
};

SV.toast = (message, type = 'success') => {
  let holder = document.querySelector('.toast-container');
  if (!holder) {
    holder = document.createElement('div');
    holder.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(holder);
  }
  const icons = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-octagon-fill', warning: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${type} border-0 shadow`;
  el.setAttribute('role', 'alert');
  el.innerHTML = `<div class="d-flex"><div class="toast-body"><i class="bi ${icons[type] || icons.info} me-2"></i>${message}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  holder.appendChild(el);
  const t = new bootstrap.Toast(el, { delay: 2600 });
  t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
};

SV.loader = (container, count = 8) => {
  if (!container) return;
  container.innerHTML = Array.from({ length: count }).map(() => `
    <div class="col-6 col-md-4 col-xl-3">
      <div class="skeleton" style="height:230px"></div>
      <div class="skeleton mt-2" style="height:14px;width:60%"></div>
      <div class="skeleton mt-2" style="height:14px;width:85%"></div>
    </div>`).join('');
};

/* ------------------------------------------------------------
   5. CART & WISHLIST CORE
   ------------------------------------------------------------ */
SV.getCart = () => SV.store.get('cart', []);
SV.saveCart = (c) => { SV.store.set('cart', c); SV.updateBadges(); };
SV.getWishlist = () => SV.store.get('wishlist', []);
SV.saveWishlist = (w) => { SV.store.set('wishlist', w); SV.updateBadges(); };

/* JSON helper for the PHP APIs. A 401 (or login_required) means the visitor is a guest. */
SV.json = async function (url, body) {
  const opt = body === undefined
    ? { cache: 'no-store', headers: { Accept: 'application/json' } }
    : { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) };
  const res = await fetch((SV.BASE || '') + url, opt);
  let json = null;
  try { json = await res.json(); } catch (_) { /* not JSON */ }
  if (res.status === 401 || (json && json.login_required)) {
    const err = new Error('Please log in to continue.');
    err.login = true;
    throw err;
  }
  if (!json) throw new Error('Unexpected server response.');
  return json;
};

/* Add to cart -> cart_api.php (real DB / session cart). The old version wrote fake demo products into localStorage. */
SV.addToCart = async function (id, qty = 1, silent = false) {
  try {
    const j = await SV.json('cart_api.php?action=add', { product_id: Number(id), quantity: Number(qty) || 1 });
    if (!j.success) {
      if (!silent) SV.toast(SV.escHtml(j.message || 'Could not add to cart.'), 'danger');
      return false;
    }
    const count = j.cart && j.cart.summary ? j.cart.summary.item_count : undefined;
    document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count } }));
    if (!silent) SV.toast('Added to your cart');
    return true;
  } catch (e) {
    if (!silent) SV.toast(SV.escHtml(e.message), 'danger');
    return false;
  }
};

SV.updateCart = function (id, qty) {
  const cart = SV.getCart();
  const line = cart.find((l) => l.id === Number(id));
  if (!line) return;
  line.qty = Math.max(1, Math.min(Number(qty) || 1, 99));
  SV.saveCart(cart);
};

SV.removeFromCart = function (id) {
  SV.saveCart(SV.getCart().filter((l) => l.id !== Number(id)));
  SV.toast('Item removed from cart', 'warning');
};

SV.clearCart = () => SV.saveCart([]);

SV.cartTotals = function () {
  const cart = SV.getCart();
  const subtotal = cart.reduce((s, l) => s + l.price * l.qty, 0);
  const coupon = SV.store.get('coupon', null);
  const discount = coupon ? +(subtotal * coupon.rate).toFixed(2) : 0;
  const base = subtotal - discount;
  const shipping = subtotal === 0 || base >= 99 ? 0 : 9.99;
  const tax = +(base * 0.08).toFixed(2);
  return { subtotal, discount, shipping, tax, total: +(base + shipping + tax).toFixed(2), count: cart.reduce((s, l) => s + l.qty, 0), coupon };
};

/* Wishlist -> wishlist_api.php (saved per user in the database).
   The old version stored ids in the browser only, so the wishlist page never saw them. */
SV.wishIds = new Set();
SV.wishLoaded = false;

SV.markWishes = function (root) {
  (root || document).querySelectorAll('[data-wish]').forEach((btn) => {
    const on = SV.wishIds.has(Number(btn.dataset.wish));
    btn.classList.toggle('active', on);
    btn.title = on ? 'Remove from wishlist' : 'Add to wishlist';
    const ic = btn.querySelector('i');
    if (ic) ic.className = on ? 'bi bi-heart-fill text-danger' : 'bi bi-heart';
  });
};

SV.loadWishIds = async function () {
  if (!SV.getUser()) { SV.wishIds = new Set(); SV.wishLoaded = true; return; }
  try {
    const j = await SV.json('wishlist_api.php?action=ids');
    if (j.success) SV.wishIds = new Set(((j.data && j.data.ids) || []).map(Number));
  } catch (_) { /* leave empty */ }
  SV.wishLoaded = true;
  SV.markWishes();
};

SV.toggleWishlist = async function (id) {
  id = Number(id);
  if (!SV.getUser()) {
    SV.toast('Please log in to use your wishlist.', 'info');
    setTimeout(() => (location.href = (SV.BASE || '') + 'login.php'), 900);
    return null;
  }
  try {
    const j = await SV.json('wishlist_api.php?action=toggle', { product_id: id });
    if (!j.success) { SV.toast(SV.escHtml(j.message || 'Could not update your wishlist.'), 'danger'); return null; }
    const on = !!j.in_wishlist;
    if (on) SV.wishIds.add(id); else SV.wishIds.delete(id);
    SV.markWishes();
    SV.toast(on ? 'Added to your wishlist' : 'Removed from your wishlist', on ? 'success' : 'warning');
    document.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count: j.count } }));
    return on;
  } catch (e) {
    if (e.login) { location.href = (SV.BASE || '') + 'login.php'; return null; }
    SV.toast(SV.escHtml(e.message || 'Could not update your wishlist.'), 'danger');
    return null;
  }
};
SV.addToWishlist = (id) => { const l = SV.getWishlist(); if (!l.includes(Number(id))) { l.push(Number(id)); SV.saveWishlist(l); } };
SV.removeFromWishlist = (id) => { SV.saveWishlist(SV.getWishlist().filter((i) => i !== Number(id))); };

SV.addRecentlyViewed = function (id) {
  let list = SV.store.get('recent', []).filter((i) => i !== Number(id));
  list.unshift(Number(id));
  SV.store.set('recent', list.slice(0, 6));
};

/* Header badges: the real numbers live in the database (cart_items / wishlist), so ask the APIs.
   Pages announce changes with the 'cart:updated' / 'wishlist:updated' events. */
/* site root, worked out from this script's own URL (so it also works from /admin pages) */
SV.BASE = (document.currentScript && document.currentScript.src) ? document.currentScript.src.replace(/js\/app\.js.*$/, '') : '';
SV.setBadge = function (selector, n) {
  n = Number(n) || 0;
  document.querySelectorAll(selector).forEach((e) => {
    e.textContent = n;
    e.classList.remove('d-none');
    e.style.display = n ? 'flex' : 'none';
  });
};
SV.syncBadges = async function () {
  const get = async (url) => {
    try {
      const r = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!r.ok) return null;
      const j = await r.json();
      return j && j.success ? Number(j.data && j.data.count) || 0 : null;
    } catch (_) { return null; }
  };
  const cart = await get(SV.BASE + 'cart_api.php?action=count');
  if (cart !== null) SV.setBadge('[data-cart-count]', cart);
  if (SV.getUser()) {
    const wish = await get(SV.BASE + 'wishlist_api.php?action=count');
    if (wish !== null) SV.setBadge('[data-wish-count]', wish);
  } else {
    SV.setBadge('[data-wish-count]', 0);
  }
};
SV.updateBadges = function () { SV.syncBadges(); };
document.addEventListener('cart:updated', (e) => {
  const n = e.detail && e.detail.count;
  if (typeof n === 'number') SV.setBadge('[data-cart-count]', n); else SV.syncBadges();
});
document.addEventListener('wishlist:updated', (e) => {
  const n = e.detail && e.detail.count;
  if (typeof n === 'number') SV.setBadge('[data-wish-count]', n); else SV.syncBadges();
});

/* ------------------------------------------------------------
   6. AUTH SESSION HELPERS
   ------------------------------------------------------------ */
// The signed-in user now comes from the PHP session (js/session.js.php sets window.SV_USER).
SV.getUser = () => window.SV_USER || null;
SV.isAdmin = () => !!(window.SV_USER && window.SV_USER.role === 'admin');
SV.escHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
SV.logout = function () {
  SV.toast('You have been logged out', 'info');
  setTimeout(() => (location.href = 'logout.php'), 500);
};

/* ------------------------------------------------------------
   7. ORDERS (seeded dummy orders + placed orders)
   ------------------------------------------------------------ */
SV.seedOrders = function () {
  if (SV.store.get('orders', null)) return;
  const statuses = ['Delivered', 'Shipped', 'Processing', 'Pending', 'Cancelled'];
  const payments = ['Credit Card', 'Cash on Delivery', 'Bank Transfer'];
  const customers = [
    ['Amelia Carter', 'amelia@example.com', '+1 202 555 0141'],
    ['Daniel Okafor', 'daniel@example.com', '+1 202 555 0177'],
    ['Sofia Rossi', 'sofia@example.com', '+39 06 555 0123'],
    ['Liam Patel', 'liam@example.com', '+44 20 7946 0101'],
    ['Emma Novak', 'emma@example.com', '+49 30 5550 9912'],
    ['Noah Kim', 'noah@example.com', '+82 2 555 7781'],
    ['Demo Customer', 'user@shopverse.com', '+1 202 555 0199']
  ];
  const orders = [];
  for (let i = 0; i < 18; i++) {
    const c = customers[i % customers.length];
    const items = [];
    const n = 1 + (i % 3);
    for (let j = 0; j < n; j++) {
      const p = SV.PRODUCTS[(i * 3 + j) % SV.PRODUCTS.length];
      items.push({ id: p.id, name: p.name, price: p.price, qty: 1 + (j % 2), image: p.images[0] });
    }
    const subtotal = items.reduce((s, it) => s + it.price * it.qty, 0);
    const shipping = subtotal >= 99 ? 0 : 9.99;
    const tax = +(subtotal * 0.08).toFixed(2);
    orders.push({
      id: 'SV-' + (24580 - i),
      date: new Date(Date.now() - i * 86400000 * 2).toISOString().slice(0, 10),
      customer: c[0], email: c[1], phone: c[2],
      address: `${120 + i} Market Street, Springfield, CA 94016, United States`,
      items, subtotal, shipping, tax, discount: 0,
      total: +(subtotal + shipping + tax).toFixed(2),
      payment: payments[i % payments.length],
      status: statuses[i % statuses.length]
    });
  }
  SV.store.set('orders', orders);
};
SV.getOrders = () => { SV.seedOrders(); return SV.store.get('orders', []); };
SV.saveOrders = (o) => SV.store.set('orders', o);

/* ------------------------------------------------------------
   8. SHARED HEADER / FOOTER COMPONENTS
   ------------------------------------------------------------ */
SV.renderHeader = function () {
  const mount = document.getElementById('sv-header');
  if (!mount) return;
  const page = mount.dataset.page || '';
  const user = SV.getUser();
  const links = [
    ['index.php', 'Home', 'home'], ['shop.php', 'Shop', 'shop'],
    ['categories.php', 'Categories', 'categories'],
    ['index.php#about', 'About', 'about'], ['index.php#contact', 'Contact', 'contact']
  ];
  mount.innerHTML = `
  <div class="sv-topbar d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
      <span><i class="bi bi-truck me-2"></i>Free shipping on all orders over $99</span>
      <div class="d-flex gap-3">
        <a href="orders.php"><i class="bi bi-box-seam me-1"></i>Track Order</a>
        <a href="index.php#contact"><i class="bi bi-headset me-1"></i>Support</a>
        ${SV.isAdmin() ? `<a href="admin/index.php"><i class="bi bi-shield-lock me-1"></i>Admin Panel</a>` : ''}
      </div>
    </div>
  </div>
  <nav class="navbar navbar-expand-lg sv-navbar">
    <div class="container">
      <a class="sv-logo d-flex align-items-center gap-2" href="index.php"><i class="bi bi-bag-check-fill"></i>Shop<span style="color:var(--sv-primary)">Verse</span></a>
      <div class="d-flex align-items-center gap-2 order-lg-3">
        <a href="wishlist.php" class="sv-icon-btn" title="Wishlist"><i class="bi bi-heart"></i><span class="sv-count" data-wish-count>0</span></a>
        <a href="cart.php" class="sv-icon-btn" title="Cart"><i class="bi bi-cart3"></i><span class="sv-count" data-cart-count>0</span></a>
        ${user ? `<div class="dropdown">
            <button class="sv-icon-btn" data-bs-toggle="dropdown" title="Account"><i class="bi bi-person"></i></button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><h6 class="dropdown-header">Hi, ${SV.escHtml(user.name.split(' ')[0])}</h6></li>
              <li><a class="dropdown-item" href="account.php"><i class="bi bi-speedometer2 me-2"></i>My Account</a></li>
              <li><a class="dropdown-item" href="orders.php"><i class="bi bi-bag-check me-2"></i>My Orders</a></li>
              <li><a class="dropdown-item" href="wishlist.php"><i class="bi bi-heart me-2"></i>Wishlist</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><button class="dropdown-item text-danger" onclick="SV.logout()"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></li>
            </ul></div>`
          : `<a href="login.php" class="btn btn-primary btn-sm d-inline-flex align-items-center"><i class="bi bi-person me-1"></i>Login</a>`}
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#svMenu"><span class="navbar-toggler-icon"></span></button>
      </div>
      <div class="collapse navbar-collapse order-lg-2">
        <ul class="navbar-nav mx-lg-3">
          ${links.map((l) => `<li class="nav-item"><a class="nav-link ${page === l[2] ? 'active' : ''}" href="${l[0]}">${l[1]}</a></li>`).join('')}
        </ul>
        <form class="d-flex sv-search ms-auto me-3" style="max-width:320px" role="search" onsubmit="SV.headerSearch(event)">
          <input class="form-control" type="search" name="q" placeholder="Search products..." aria-label="Search">
          <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
        </form>
      </div>
    </div>
  </nav>
  <!-- Mobile offcanvas menu -->
  <div class="offcanvas offcanvas-end" id="svMenu" tabindex="-1">
    <div class="offcanvas-header border-bottom">
      <span class="sv-logo"><i class="bi bi-bag-check-fill"></i> ShopVerse</span>
      <button class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
      <form class="d-flex sv-search mb-3" onsubmit="SV.headerSearch(event)">
        <input class="form-control" type="search" name="q" placeholder="Search products...">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
      </form>
      <ul class="navbar-nav mb-3">
        ${links.map((l) => `<li class="nav-item"><a class="nav-link ${page === l[2] ? 'active' : ''}" href="${l[0]}">${l[1]}</a></li>`).join('')}
        <li class="nav-item"><a class="nav-link" href="account.php">My Account</a></li>
        <li class="nav-item"><a class="nav-link" href="orders.php">My Orders</a></li>
        ${SV.isAdmin() ? `<li class="nav-item"><a class="nav-link" href="admin/index.php">Admin Panel</a></li>` : ''}
      </ul>
      ${user ? `<button class="btn btn-outline-danger w-100" onclick="SV.logout()">Logout</button>`
             : `<a href="login.php" class="btn btn-primary w-100 mb-2">Login</a><a href="register.php" class="btn btn-outline-primary w-100">Create account</a>`}
    </div>
  </div>`;
};

SV.headerSearch = function (e) {
  e.preventDefault();
  const q = new FormData(e.target).get('q') || '';
  location.href = 'shop.php?q=' + encodeURIComponent(q.trim());
};

SV.renderFooter = function () {
  const mount = document.getElementById('sv-footer');
  if (!mount) return;
  mount.innerHTML = `
  <footer class="sv-footer">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <span class="sv-logo text-white d-inline-flex align-items-center gap-2"><i class="bi bi-bag-check-fill"></i>ShopVerse</span>
          <p class="mt-3 mb-3" style="max-width:340px">ShopVerse is a modern multi-category online store delivering curated products, fast shipping and a checkout experience your customers will love.</p>
          <div class="d-flex gap-2">
            <a class="social-ic" href="#"><i class="bi bi-facebook"></i></a>
            <a class="social-ic" href="#"><i class="bi bi-twitter-x"></i></a>
            <a class="social-ic" href="#"><i class="bi bi-instagram"></i></a>
            <a class="social-ic" href="#"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
        <div class="col-6 col-lg-2"><h6>Shop</h6>
          <a href="shop.php">All Products</a><br><a href="shop.php?sort=latest">New Arrivals</a><br>
          <a href="shop.php?sort=popular">Best Sellers</a><br><a href="categories.php">Categories</a>
        </div>
        <div class="col-6 col-lg-2"><h6>Account</h6>
          <a href="account.php">My Account</a><br><a href="orders.php">Orders</a><br>
          <a href="wishlist.php">Wishlist</a><br><a href="cart.php">Cart</a>
        </div>
        <div class="col-6 col-lg-2"><h6>Company</h6>
          <a href="index.php#about">About Us</a><br><a href="index.php#contact">Contact</a><br>
          <a href="index.php#faq">FAQ</a>${SV.isAdmin() ? '<br><a href="admin/index.php">Admin Panel</a>' : ''}
        </div>
        <div class="col-6 col-lg-2"><h6>Contact</h6>
          <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>128 Market St, CA</p>
          <p class="mb-1"><i class="bi bi-telephone me-2"></i>+1 202 555 0199</p>
          <p class="mb-0"><i class="bi bi-envelope me-2"></i>help@shopverse.com</p>
        </div>
      </div>
      <div class="foot-bottom d-flex flex-wrap justify-content-between gap-2">
        <span>&copy; ${new Date().getFullYear()} ShopVerse. All rights reserved.</span>
        <span><i class="bi bi-credit-card-2-front me-2"></i>Visa · Mastercard · PayPal · Apple Pay</span>
      </div>
    </div>
  </footer>`;
};

/* ------------------------------------------------------------
   9. GLOBAL EVENT DELEGATION (works on every page)
   ------------------------------------------------------------ */
document.addEventListener('click', async function (e) {
  const cartBtn = e.target.closest('[data-add-cart]');
  if (cartBtn) {
    e.preventDefault();
    if (cartBtn.dataset.busy) return;
    cartBtn.dataset.busy = '1';
    await SV.addToCart(cartBtn.dataset.addCart);
    delete cartBtn.dataset.busy;
    return;
  }

  const wishBtn = e.target.closest('[data-wish]');
  if (wishBtn) {
    e.preventDefault();
    if (wishBtn.dataset.busy) return;   // ignore double clicks while the request is running
    wishBtn.dataset.busy = '1';
    await SV.toggleWishlist(wishBtn.dataset.wish);
    delete wishBtn.dataset.busy;
    return;
  }

  const quick = e.target.closest('[data-quickview]');
  if (quick) { e.preventDefault(); SV.quickView(quick.dataset.quickview); }
});

/* Quick view modal (shared) */
SV.quickView = function (id) {
  const p = SV.PRODUCTS.find((x) => x.id === Number(id));
  if (!p) return;
  let modal = document.getElementById('svQuickView');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'svQuickView';
    modal.className = 'modal fade';
    modal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0"></div></div>';
    document.body.appendChild(modal);
  }
  modal.querySelector('.modal-content').innerHTML = `
    <div class="modal-header border-0"><h5 class="modal-title">Quick View</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-4">
        <div class="col-md-6"><div class="gallery-main"><img src="${p.images[0]}" alt="${p.name}"></div></div>
        <div class="col-md-6">
          <span class="product-cat">${p.category}</span>
          <h4 class="mt-1">${p.name}</h4>
          <div class="mb-2">${SV.stars(p.rating)} <span class="text-muted-2 small">${p.rating} (${p.reviews} reviews)</span></div>
          <p class="mb-2"><span class="price fs-4">${SV.money(p.price)}</span><span class="price-old">${SV.money(p.oldPrice)}</span>
            <span class="badge text-bg-danger ms-2">-${p.discount}%</span></p>
          <p class="text-muted-2 small">${p.description.slice(0, 190)}...</p>
          <p class="mb-3"><i class="bi bi-check-circle-fill text-success me-1"></i>In stock — ${p.stock} units available</p>
          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" data-add-cart="${p.id}"><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
            <a class="btn btn-outline-primary" href="product-details.php?id=${p.id}">View full details</a>
            <button class="btn btn-outline-secondary" data-wish="${p.id}"><i class="bi bi-heart"></i></button>
          </div>
        </div>
      </div>
    </div>`;
  bootstrap.Modal.getOrCreateInstance(modal).show();
};

/* ------------------------------------------------------------
   10. BOOT
   ------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', function () {
  SV.seedOrders();
  SV.renderHeader();
  SV.renderFooter();
  SV.updateBadges();
  SV.loadWishIds();

  /* pages render product cards after loading (AJAX) -> keep the hearts in sync */
  let markTimer;
  new MutationObserver(() => {
    if (!SV.wishLoaded) return;
    clearTimeout(markTimer);
    markTimer = setTimeout(() => SV.markWishes(), 40);
  }).observe(document.body, { childList: true, subtree: true });
});
