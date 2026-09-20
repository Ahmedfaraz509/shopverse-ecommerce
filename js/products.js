/* ============================================================
   ShopVerse – products.js
   Product card template, home page sections, shop page
   (search / filter / sort / pagination) and product details.
   ============================================================ */

/* ---------- Reusable product card ---------- */
SV.productCard = function (p, col = 'col-6 col-md-4 col-xl-3') {
  const wished = SV.getWishlist().includes(p.id);
  const badge = p.badge
    ? `<span class="badge text-bg-${p.badge === 'Sale' ? 'danger' : p.badge === 'New' ? 'success' : p.badge === 'Premium' ? 'dark' : 'primary'}">${p.badge}</span>` : '';
  return `
  <div class="${col}">
    <article class="product-card">
      <div class="product-thumb">
        <a href="product-details.php?id=${p.id}"><img src="${p.images[0]}" alt="${p.name}" loading="lazy"></a>
        <div class="product-badges">
          ${p.discount > 0 ? `<span class="badge text-bg-danger">-${p.discount}%</span>` : ''}${badge}
        </div>
        <div class="product-actions">
          <button class="btn ${wished ? 'active' : ''}" data-wish="${p.id}" title="Wishlist"><i class="bi ${wished ? 'bi-heart-fill' : 'bi-heart'}"></i></button>
          <button class="btn" data-quickview="${p.id}" title="Quick view"><i class="bi bi-eye"></i></button>
          <a class="btn" href="product-details.php?id=${p.id}" title="Details"><i class="bi bi-arrow-right"></i></a>
        </div>
      </div>
      <div class="product-body">
        <span class="product-cat">${p.category}</span>
        <a class="product-name" href="product-details.php?id=${p.id}">${p.name}</a>
        <div class="d-flex align-items-center gap-1 mb-2">${SV.stars(p.rating)}<span class="text-muted-2" style="font-size:.78rem">(${p.reviews})</span></div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div><span class="price">${SV.money(p.price)}</span><span class="price-old">${SV.money(p.oldPrice)}</span></div>
        </div>
        <div class="product-actions-bottom d-grid mt-3">
          <button class="btn btn-sm btn-primary" data-add-cart="${p.id}"><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
        </div>
      </div>
    </article>
  </div>`;
};

SV.renderProducts = function (list, selector, col) {
  const box = document.querySelector(selector);
  if (!box) return;
  box.innerHTML = list.length
    ? list.map((p) => SV.productCard(p, col)).join('')
    : `<div class="col-12 text-center py-5"><i class="bi bi-search fs-1 text-muted-2"></i>
        <h5 class="mt-3">No products found</h5><p class="text-muted-2">Try adjusting your filters or search keywords.</p></div>`;
};

/* ============================================================
   HOME PAGE
   ============================================================ */
SV.initHome = async function () {
  ['#bestSellers', '#newArrivals', '#trending'].forEach((s) => SV.loader(document.querySelector(s), 4));
  const products = await SV.loadProducts();
  SV.PRODUCTS = products;

  const best = [...products].sort((a, b) => b.sold - a.sold).slice(0, 8);
  const latest = [...products].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 8);
  const trending = [...products].sort((a, b) => b.rating - a.rating).slice(0, 4);

  SV.renderProducts(best, '#bestSellers');
  SV.renderProducts(latest, '#newArrivals');
  SV.renderProducts(trending, '#trending');

  const cats = await SV.loadCategories();
  const catBox = document.querySelector('#homeCategories');
  if (catBox) {
    catBox.innerHTML = cats.map((c) => `
      <div class="col-6 col-md-4 col-lg-3">
        <a class="category-card" href="shop.php?category=${encodeURIComponent(c.name)}">
          <img src="${c.image}" alt="${c.name}" loading="lazy">
          <div class="cat-body"><h6>${c.name}</h6>
            <small class="text-muted-2">${products.filter((p) => p.category === c.name).length} products</small></div>
        </a>
      </div>`).join('');
  }

  const revBox = document.querySelector('#homeReviews');
  if (revBox) {
    revBox.innerHTML = SV.REVIEWS.map((r) => `
      <div class="col-md-6 col-lg-3">
        <div class="review-card">
          <div class="mb-2">${SV.stars(r.rating)}</div>
          <p class="mb-3">"${r.text}"</p>
          <div class="d-flex align-items-center gap-2">
            <img src="${r.avatar}" class="rounded-circle" width="42" height="42" style="object-fit:cover" alt="${r.name}">
            <div><strong class="d-block">${r.name}</strong><small class="text-muted-2">${r.role}</small></div>
          </div>
        </div>
      </div>`).join('');
  }

  SV.startCountdown();
};

SV.startCountdown = function () {
  const el = document.getElementById('countdown');
  if (!el) return;
  const end = Date.now() + 1000 * 60 * 60 * 47;
  const tick = () => {
    const d = Math.max(0, end - Date.now());
    const h = Math.floor(d / 3.6e6), m = Math.floor((d % 3.6e6) / 6e4), s = Math.floor((d % 6e4) / 1000);
    el.innerHTML = `<span>${String(h).padStart(2, '0')}<small class="d-block">Hrs</small></span>
      <span>${String(m).padStart(2, '0')}<small class="d-block">Min</small></span>
      <span>${String(s).padStart(2, '0')}<small class="d-block">Sec</small></span>`;
  };
  tick(); setInterval(tick, 1000);
};

/* ============================================================
   SHOP PAGE
   ============================================================ */
SV.shop = {
  all: [], filtered: [], page: 1, perPage: 9,
  state: { q: '', categories: [], min: 0, max: 1500, rating: 0, sort: 'latest' }
};

SV.initShop = async function () {
  const s = SV.shop;
  SV.loader(document.querySelector('#shopGrid'), 9);
  s.all = await SV.loadProducts();
  SV.PRODUCTS = s.all;

  // Prefill from URL
  s.state.q = SV.qs('q') || '';
  const cat = SV.qs('category');
  if (cat) s.state.categories = [cat];
  if (SV.qs('sort')) s.state.sort = SV.qs('sort');
  const searchInput = document.getElementById('shopSearch');
  if (searchInput) searchInput.value = s.state.q;
  document.getElementById('sortSelect').value = s.state.sort;

  // Category checkbox list
  const cats = await SV.loadCategories();
  document.getElementById('catFilters').innerHTML = cats.map((c) => `
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="${c.name}" id="cat${c.id}" ${s.state.categories.includes(c.name) ? 'checked' : ''}>
      <label class="form-check-label d-flex justify-content-between" for="cat${c.id}">
        <span>${c.name}</span><span class="text-muted-2">${s.all.filter((p) => p.category === c.name).length}</span>
      </label>
    </div>`).join('');

  // Events
  document.getElementById('catFilters').addEventListener('change', () => {
    s.state.categories = [...document.querySelectorAll('#catFilters input:checked')].map((i) => i.value);
    s.page = 1; SV.filterProducts();
  });
  const priceRange = document.getElementById('priceRange');
  priceRange.addEventListener('input', () => {
    s.state.max = +priceRange.value;
    document.getElementById('priceLabel').textContent = `$0 – ${SV.money(s.state.max)}`;
    s.page = 1; SV.filterProducts();
  });
  document.getElementById('ratingFilters').addEventListener('change', (e) => {
    s.state.rating = +e.target.value; s.page = 1; SV.filterProducts();
  });
  document.getElementById('sortSelect').addEventListener('change', (e) => { s.state.sort = e.target.value; SV.filterProducts(); });
  document.getElementById('shopSearchForm').addEventListener('submit', (e) => {
    e.preventDefault(); s.state.q = searchInput.value.trim(); s.page = 1; SV.searchProducts(s.state.q);
  });
  document.getElementById('resetFilters').addEventListener('click', () => {
    s.state = { q: '', categories: [], min: 0, max: 1500, rating: 0, sort: 'latest' };
    document.querySelectorAll('#catFilters input').forEach((i) => (i.checked = false));
    document.querySelectorAll('#ratingFilters input').forEach((i) => (i.checked = false));
    priceRange.value = 1500; document.getElementById('priceLabel').textContent = '$0 – $1,500.00';
    searchInput.value = ''; document.getElementById('sortSelect').value = 'latest';
    s.page = 1; SV.filterProducts(); SV.toast('Filters reset', 'info');
  });

  SV.filterProducts();
};

SV.searchProducts = function (q) {
  SV.shop.state.q = q;
  SV.filterProducts();
  SV.toast(`Showing results for "<strong>${q || 'all products'}</strong>"`, 'info');
};

SV.filterProducts = async function () {
  const s = SV.shop;
  const grid = document.querySelector('#shopGrid');
  SV.loader(grid, 6);
  await SV.api.delay(250); // simulate AJAX round-trip

  let list = s.all.filter((p) => {
    const q = s.state.q.toLowerCase();
    const matchQ = !q || p.name.toLowerCase().includes(q) || p.category.toLowerCase().includes(q) || p.brand.toLowerCase().includes(q);
    const matchC = !s.state.categories.length || s.state.categories.includes(p.category);
    const matchP = p.price >= s.state.min && p.price <= s.state.max;
    const matchR = !s.state.rating || p.rating >= s.state.rating;
    return matchQ && matchC && matchP && matchR;
  });

  const sorters = {
    latest: (a, b) => new Date(b.createdAt) - new Date(a.createdAt),
    'price-asc': (a, b) => a.price - b.price,
    'price-desc': (a, b) => b.price - a.price,
    popular: (a, b) => b.sold - a.sold,
    rating: (a, b) => b.rating - a.rating
  };
  list.sort(sorters[s.state.sort] || sorters.latest);
  s.filtered = list;
  SV.renderShopPage();
};

SV.renderShopPage = function () {
  const s = SV.shop;
  const total = s.filtered.length;
  const pages = Math.max(1, Math.ceil(total / s.perPage));
  s.page = Math.min(s.page, pages);
  const slice = s.filtered.slice((s.page - 1) * s.perPage, s.page * s.perPage);

  SV.renderProducts(slice, '#shopGrid', 'col-6 col-lg-4');
  document.getElementById('resultCount').innerHTML =
    total ? `Showing <strong>${(s.page - 1) * s.perPage + 1}–${Math.min(s.page * s.perPage, total)}</strong> of <strong>${total}</strong> products` : 'No products found';

  // pagination
  const pag = document.getElementById('shopPagination');
  let html = `<li class="page-item ${s.page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${s.page - 1}"><i class="bi bi-chevron-left"></i></a></li>`;
  for (let i = 1; i <= pages; i++) html += `<li class="page-item ${i === s.page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
  html += `<li class="page-item ${s.page === pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${s.page + 1}"><i class="bi bi-chevron-right"></i></a></li>`;
  pag.innerHTML = html;
  pag.onclick = (e) => {
    const a = e.target.closest('[data-page]');
    if (!a) return;
    e.preventDefault();
    const p = +a.dataset.page;
    if (p >= 1 && p <= pages) { s.page = p; SV.renderShopPage(); window.scrollTo({ top: 200, behavior: 'smooth' }); }
  };
};

/* ============================================================
   CATEGORIES PAGE
   ============================================================ */
SV.initCategories = async function () {
  const box = document.getElementById('categoryGrid');
  box.innerHTML = '<div class="col-12 sv-loader"><div class="spinner-border text-primary"></div></div>';
  const [cats, products] = await Promise.all([SV.loadCategories(), SV.loadProducts()]);
  SV.PRODUCTS = products;
  box.innerHTML = cats.map((c) => {
    const items = products.filter((p) => p.category === c.name);
    return `
    <div class="col-md-6 col-lg-4">
      <div class="card h-100">
        <img src="${c.image}" class="card-img-top" style="aspect-ratio:16/9;object-fit:cover" alt="${c.name}">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="ic d-inline-flex align-items-center justify-content-center rounded-3" style="width:40px;height:40px;background:var(--sv-primary-soft);color:var(--sv-primary)"><i class="bi ${c.icon}"></i></span>
            <h5 class="mb-0">${c.name}</h5>
          </div>
          <p class="text-muted-2 mb-3">${c.description}</p>
          <div class="d-flex justify-content-between align-items-center">
            <span class="badge text-bg-light border">${items.length} products</span>
            <a href="shop.php?category=${encodeURIComponent(c.name)}" class="btn btn-sm btn-outline-primary">Browse <i class="bi bi-arrow-right ms-1"></i></a>
          </div>
        </div>
      </div>
    </div>`;
  }).join('');
};

/* ============================================================
   PRODUCT DETAILS PAGE
   ============================================================ */
SV.initProductDetails = async function () {
  const id = Number(SV.qs('id')) || 1;
  const products = await SV.loadProducts();
  SV.PRODUCTS = products;
  const p = products.find((x) => x.id === id) || products[0];
  SV.addRecentlyViewed(p.id);
  document.title = `${p.name} – ShopVerse`;
  document.getElementById('bcName').textContent = p.name;

  const wished = SV.getWishlist().includes(p.id);
  document.getElementById('productDetail').innerHTML = `
    <div class="col-lg-6">
      <div class="gallery-main mb-3"><img id="mainImage" src="${p.images[0]}" alt="${p.name}"></div>
      <div class="thumb-list d-flex gap-2">
        ${p.images.map((im, i) => `<img src="${im}" class="${i === 0 ? 'active' : ''}" alt="thumb ${i + 1}">`).join('')}
      </div>
    </div>
    <div class="col-lg-6">
      <span class="product-cat">${p.category}</span>
      <h1 class="h3 mt-1 mb-2">${p.name}</h1>
      <div class="d-flex align-items-center gap-2 mb-3">
        ${SV.stars(p.rating)} <span class="text-muted-2 small">${p.rating} · ${p.reviews} reviews</span>
        <span class="text-muted-2 small">| SKU: ${p.sku}</span>
      </div>
      <div class="mb-3">
        <span class="price fs-2">${SV.money(p.price)}</span>
        <span class="price-old fs-6">${SV.money(p.oldPrice)}</span>
        <span class="badge text-bg-danger ms-2">Save ${p.discount}%</span>
      </div>
      <p class="text-muted-2">${p.description}</p>
      <p class="mb-3">
        ${p.stock > 0
          ? `<span class="badge text-bg-success-subtle text-success border border-success-subtle status-badge"><i class="bi bi-check-circle me-1"></i>In Stock (${p.stock})</span>`
          : `<span class="badge text-bg-danger status-badge">Out of stock</span>`}
        <span class="ms-2 text-muted-2 small"><i class="bi bi-truck me-1"></i>Free delivery in 2–4 days</span>
      </p>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="qty-box">
          <button type="button" id="qtyMinus">−</button>
          <input type="text" id="qtyInput" value="1" inputmode="numeric">
          <button type="button" id="qtyPlus">+</button>
        </div>
        <button class="btn btn-primary" id="detailAddCart"><i class="bi bi-cart-plus me-1"></i>Add to Cart</button>
        <button class="btn btn-accent" id="buyNow"><i class="bi bi-lightning-charge me-1"></i>Buy Now</button>
        <button class="btn btn-outline-secondary ${wished ? 'active' : ''}" data-wish="${p.id}"><i class="bi ${wished ? 'bi-heart-fill' : 'bi-heart'}"></i></button>
      </div>
      <ul class="list-unstyled small text-muted-2 mb-0">
        <li><i class="bi bi-shield-check me-2 text-success"></i>2-year warranty included</li>
        <li><i class="bi bi-arrow-counterclockwise me-2 text-success"></i>30-day free returns</li>
        <li><i class="bi bi-lock me-2 text-success"></i>Secure encrypted payment</li>
      </ul>
    </div>`;

  // Gallery
  document.querySelector('.thumb-list').addEventListener('click', (e) => {
    if (e.target.tagName !== 'IMG') return;
    document.getElementById('mainImage').src = e.target.src;
    document.querySelectorAll('.thumb-list img').forEach((i) => i.classList.remove('active'));
    e.target.classList.add('active');
  });
  // Quantity
  const qty = document.getElementById('qtyInput');
  document.getElementById('qtyMinus').onclick = () => (qty.value = Math.max(1, +qty.value - 1));
  document.getElementById('qtyPlus').onclick = () => (qty.value = Math.min(p.stock || 99, +qty.value + 1));
  document.getElementById('detailAddCart').onclick = () => SV.addToCart(p.id, +qty.value);
  document.getElementById('buyNow').onclick = () => { SV.addToCart(p.id, +qty.value, true); location.href = 'checkout.php'; };

  // Tabs content
  document.getElementById('tabDescription').innerHTML = `
    <p>${p.description}</p>
    <p class="mb-0">Designed for everyday use, the ${p.name} combines durable construction with a modern aesthetic.
    Each item is quality checked before dispatch from our warehouse and comes with ShopVerse buyer protection.</p>
    <ul class="mt-3"><li>Premium, responsibly sourced materials</li><li>Tested for long-term daily use</li>
    <li>Includes accessories and printed quick-start guide</li><li>Eligible for express 24-hour delivery</li></ul>`;

  document.getElementById('tabSpecs').innerHTML = `
    <table class="table table-striped mb-0"><tbody>
      ${Object.entries(p.specs).map(([k, v]) => `<tr><th style="width:220px">${k}</th><td>${v}</td></tr>`).join('')}
      <tr><th>Stock</th><td>${p.stock} units</td></tr>
      <tr><th>Rating</th><td>${p.rating} / 5 based on ${p.reviews} reviews</td></tr>
    </tbody></table>`;

  document.getElementById('tabReviews').innerHTML = `
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="filter-box text-center">
          <h2 class="mb-0">${p.rating}</h2>${SV.stars(p.rating)}
          <p class="text-muted-2 mt-2 mb-0">${p.reviews} verified reviews</p>
        </div>
      </div>
      <div class="col-lg-8">
        ${SV.REVIEWS.map((r) => `
          <div class="d-flex gap-3 border-bottom pb-3 mb-3">
            <img src="${r.avatar}" class="rounded-circle" width="48" height="48" style="object-fit:cover" alt="${r.name}">
            <div><strong>${r.name}</strong> <span class="badge text-bg-light border ms-1">${r.role}</span>
              <div>${SV.stars(r.rating)}</div><p class="mb-0 text-muted-2">${r.text}</p></div>
          </div>`).join('')}
        <form class="mt-3" onsubmit="event.preventDefault(); SV.toast('Thanks! Your review has been submitted for moderation.'); this.reset();">
          <h6>Write a review</h6>
          <div class="row g-2">
            <div class="col-md-6"><input class="form-control" placeholder="Your name" required></div>
            <div class="col-md-6"><select class="form-select"><option>5 stars</option><option>4 stars</option><option>3 stars</option><option>2 stars</option><option>1 star</option></select></div>
            <div class="col-12"><textarea class="form-control" rows="3" placeholder="Share your experience..." required></textarea></div>
            <div class="col-12"><button class="btn btn-primary">Submit Review</button></div>
          </div>
        </form>
      </div>
    </div>`;

  // Related products
  const related = products.filter((x) => x.category === p.category && x.id !== p.id).slice(0, 4);
  SV.renderProducts(related.length ? related : products.slice(0, 4), '#relatedProducts');
};
