<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shop – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <header id="sv-header" class="sv-sticky" data-page="shop"></header>

  <section class="page-head">
    <div class="container">
      <h1 class="h3 mb-2">Shop All Products</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Shop</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section">
    <div class="container">
      <div class="row g-4">

        <!-- ===== Filters ===== -->
        <aside class="col-lg-3">
          <button class="btn btn-outline-primary w-100 mb-3 d-lg-none" type="button" data-bs-toggle="collapse"
            data-bs-target="#filterPanel">
            <i class="bi bi-funnel me-1"></i>Filters &amp; Sorting
          </button>

          <div class="collapse d-lg-block" id="filterPanel">

            <div class="filter-box mb-3">
              <h6>Search</h6>
              <form id="shopSearchForm" class="d-flex sv-search">
                <input class="form-control" id="shopSearch" placeholder="Search products...">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
              </form>
            </div>

            <div class="filter-box mb-3">
              <h6>Categories</h6>
              <div id="catFilters">
                <div class="text-muted small">Loading…</div>
              </div>
            </div>

            <div class="filter-box mb-3">
              <h6>Price range</h6>
              <input type="range" class="form-range" min="0" max="1000" step="10" value="1000" id="priceRange">
              <div class="d-flex justify-content-between">
                <small class="text-muted-2" id="priceLabel">$0 – $1,000</small>
              </div>
            </div>

            <div class="filter-box mb-3">
              <h6>Customer rating</h6>
              <div id="ratingFilters">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="rating" value="4.5" id="r45">
                  <label class="form-check-label" for="r45">
                    <span class="stars"><i class="bi bi-star-fill"></i></span> 4.5 &amp; up
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="rating" value="4" id="r4">
                  <label class="form-check-label" for="r4">
                    <span class="stars"><i class="bi bi-star-fill"></i></span> 4.0 &amp; up
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="rating" value="3.5" id="r35">
                  <label class="form-check-label" for="r35">
                    <span class="stars"><i class="bi bi-star-fill"></i></span> 3.5 &amp; up
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="rating" value="0" id="r0" checked>
                  <label class="form-check-label" for="r0">All ratings</label>
                </div>
              </div>
            </div>

            <div class="filter-box mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="onSaleFilter">
                <label class="form-check-label" for="onSaleFilter">On sale only</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="inStockFilter">
                <label class="form-check-label" for="inStockFilter">In stock only</label>
              </div>
            </div>

            <button class="btn btn-light border w-100" id="resetFilters">
              <i class="bi bi-arrow-counterclockwise me-1"></i>Reset filters
            </button>
          </div>
        </aside>

        <!-- ===== Product grid ===== -->
        <div class="col-lg-9">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <span class="text-muted-2" id="resultCount">Loading...</span>
            <div class="d-flex align-items-center gap-2">
              <label class="text-muted-2 small mb-0 text-nowrap">Sort by</label>
              <select class="form-select form-select-sm" id="sortSelect" style="width:200px">
                <option value="latest">Latest</option>
                <option value="price-asc">Price: Low to High</option>
                <option value="price-desc">Price: High to Low</option>
                <option value="popular">Popular</option>
                <option value="rating">Top Rated</option>
                <option value="discount">Biggest discount</option>
              </select>
            </div>
          </div>

          <!-- Active filter chips -->
          <div class="d-flex flex-wrap gap-2 mb-3" id="activeChips"></div>

          <!-- Product grid -->
          <div class="row g-3 g-lg-4" id="shopGrid">
            <div class="col-12 text-center py-5">
              <div class="spinner-border text-primary"></div>
              <p class="text-muted mt-3 mb-0">Loading products…</p>
            </div>
          </div>

          <!-- Empty -->
          <div id="shopEmpty" class="text-center py-5 d-none">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h5 class="mt-3">No products found</h5>
            <p class="text-muted-2">Try adjusting your filters or search terms.</p>
            <button class="btn btn-primary" id="emptyReset">Clear all filters</button>
          </div>

          <nav class="mt-5 d-flex justify-content-center">
            <ul class="pagination" id="shopPagination"></ul>
          </nav>
        </div>
      </div>
    </div>
  </main>

  <div id="sv-footer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>
  <script src="js/auth.js"></script>

  <script>
    /* =========================================================
     |  Shop page — talks to shop_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'shop_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
       <text x="50%" y="50%" font-family="sans-serif" font-size="18"
             fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);

      const state = {
        search: '',
        categories: new Set(),
        price_max: null,
        price_min: null,
        rating_min: 0,
        on_sale: false,
        in_stock: false,
        sort: 'latest',
        page: 1,
        per_page: 12,
        bounds: { min: 0, max: 1000 },
        total: 0,
        pages: 1
      };

      const grid = $('#shopGrid');
      const empty = $('#shopEmpty');
      const pagination = $('#shopPagination');
      const resultCount = $('#resultCount');
      const catFilters = $('#catFilters');
      const chipsWrap = $('#activeChips');
      const priceRange = $('#priceRange');
      const priceLabel = $('#priceLabel');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);
      const moneyInt = (n) => '$' + Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 0 });

      /* ---------- URL <-> state sync (deep links) ---------- */
      function readURL() {
        const q = new URLSearchParams(location.search);
        if (q.get('search')) state.search = q.get('search');
        if (q.get('category')) {
          q.get('category').split(',').filter(Boolean).forEach(c => state.categories.add(c));
        }
        if (q.get('price_max')) state.price_max = Number(q.get('price_max'));
        if (q.get('price_min')) state.price_min = Number(q.get('price_min'));
        if (q.get('rating_min')) state.rating_min = Number(q.get('rating_min'));
        if (q.get('on_sale') === '1') state.on_sale = true;
        if (q.get('in_stock') === '1') state.in_stock = true;
        if (q.get('sort')) state.sort = q.get('sort');
        if (q.get('page')) state.page = Math.max(1, Number(q.get('page')) || 1);
      }

      function writeURL() {
        const q = new URLSearchParams();
        if (state.search) q.set('search', state.search);
        if (state.categories.size) q.set('category', [...state.categories].join(','));
        if (state.price_max !== null && state.price_max < state.bounds.max) q.set('price_max', state.price_max);
        if (state.price_min) q.set('price_min', state.price_min);
        if (state.rating_min) q.set('rating_min', state.rating_min);
        if (state.on_sale) q.set('on_sale', '1');
        if (state.in_stock) q.set('in_stock', '1');
        if (state.sort && state.sort !== 'latest') q.set('sort', state.sort);
        if (state.page > 1) q.set('page', state.page);

        const qs = q.toString();
        history.replaceState(null, '', qs ? `?${qs}` : location.pathname);
      }

      /* ---------- API ---------- */
      async function api(action, params = {}) {
        const q = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`${API}?${q}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      function buildQuery() {
        const p = {};
        if (state.search) p.search = state.search;
        if (state.categories.size) p.category = [...state.categories].join(',');
        if (state.price_max !== null) p.price_max = state.price_max;
        if (state.price_min) p.price_min = state.price_min;
        if (state.rating_min) p.rating_min = state.rating_min;
        if (state.on_sale) p.on_sale = 1;
        if (state.in_stock) p.in_stock = 1;
        p.sort = state.sort;
        p.page = state.page;
        p.per_page = state.per_page;
        return p;
      }

      /* ---------- Render: sidebar facets ---------- */
      function renderFacets(list) {
        if (!list.length) {
          catFilters.innerHTML = '<div class="text-muted small">No categories.</div>';
          return;
        }

        catFilters.innerHTML = list.map(c => `
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input cat-filter" type="checkbox"
               value="${esc(c.slug)}" id="cat-${c.id}"
               ${state.categories.has(c.slug) ? 'checked' : ''}
               ${c.product_count === 0 && !state.categories.has(c.slug) ? 'disabled' : ''}>
        <label class="form-check-label flex-grow-1" for="cat-${c.id}">
          ${esc(c.name)}
        </label>
        <span class="badge bg-light text-dark">${c.product_count}</span>
      </div>
    `).join('');

        catFilters.querySelectorAll('.cat-filter').forEach(cb => {
          cb.addEventListener('change', () => {
            if (cb.checked) state.categories.add(cb.value);
            else state.categories.delete(cb.value);
            state.page = 1;
            refresh();
          });
        });
      }

      /* ---------- Render: chips ---------- */
      function renderChips() {
        const chips = [];

        if (state.search) chips.push(['search', `Search: "${state.search}"`]);
        state.categories.forEach(slug => chips.push(['category:' + slug, slug]));
        if (state.price_max !== null && state.price_max < state.bounds.max) {
          chips.push(['price', `Under ${moneyInt(state.price_max)}`]);
        }
        if (state.rating_min > 0) chips.push(['rating', `${state.rating_min}★ & up`]);
        if (state.on_sale) chips.push(['on_sale', 'On sale']);
        if (state.in_stock) chips.push(['in_stock', 'In stock']);

        chipsWrap.innerHTML = chips.map(([key, label]) => `
      <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-flex align-items-center gap-1 py-2 px-3">
        ${esc(label)}
        <button type="button" class="btn-close btn-close-sm ms-1" data-chip="${esc(key)}"
                style="font-size:.55rem"></button>
      </span>
    `).join('');

        chipsWrap.querySelectorAll('[data-chip]').forEach(btn => {
          btn.addEventListener('click', () => {
            const k = btn.dataset.chip;
            if (k === 'search') state.search = '';
            else if (k === 'price') state.price_max = state.bounds.max;
            else if (k === 'rating') state.rating_min = 0;
            else if (k === 'on_sale') state.on_sale = false;
            else if (k === 'in_stock') state.in_stock = false;
            else if (k.startsWith('category:')) state.categories.delete(k.slice(9));

            syncInputsFromState();
            state.page = 1;
            refresh();
          });
        });
      }

      /* ---------- Render: grid ---------- */
      function stars(rating) {
        const r = Math.round(rating);
        let html = '';
        for (let i = 1; i <= 5; i++) {
          html += `<i class="bi bi-star${i <= r ? '-fill' : ''} text-warning"></i>`;
        }
        return html;
      }

      function productCard(p) {
        const img = p.image || FALLBACK;

        const flags = [];
        if (p.discount_percent >= 5) flags.push(`<span class="badge bg-danger">-${p.discount_percent}%</span>`);
        if (p.best_seller) flags.push(`<span class="badge bg-warning text-dark">Best</span>`);
        if (p.new_arrival) flags.push(`<span class="badge bg-info text-dark">New</span>`);

        const outOfStock = !p.in_stock
          ? `<div class="badge bg-dark position-absolute" style="top:48px;right:8px">Out of stock</div>`
          : '';

        const priceHtml = p.old_price
          ? `<span class="fw-bold text-primary">${money(p.price)}</span>
         <small class="text-muted text-decoration-line-through ms-1">${money(p.old_price)}</small>`
          : `<span class="fw-bold text-primary">${money(p.price)}</span>`;

        return `
      <div class="col-6 col-md-4">
        <div class="card border-0 shadow-sm h-100 product-card position-relative">
          <div class="position-absolute d-flex gap-1" style="top:8px;left:8px;z-index:2">
            ${flags.join(' ')}
          </div>
          <button type="button" class="btn btn-sm btn-light border rounded-circle position-absolute p-0"
                  style="top:8px;right:8px;z-index:3;width:34px;height:34px"
                  data-wish="${p.id}" title="Add to wishlist" aria-label="Add to wishlist">
            <i class="bi bi-heart"></i>
          </button>
          ${outOfStock}
          <a href="product-details.php?slug=${encodeURIComponent(p.slug)}"
             class="text-decoration-none text-reset">
            <div class="ratio ratio-1x1 overflow-hidden rounded-top">
              <img src="${esc(img)}" alt="${esc(p.name)}" loading="lazy"
                   class="w-100 h-100 product-image" style="object-fit:cover"
                   onerror="this.onerror=null;this.src='${FALLBACK}'">
            </div>
          </a>
          <div class="card-body d-flex flex-column">
            ${p.category_name
            ? `<a href="shop.php?category=${encodeURIComponent(p.category_slug)}"
                    class="small text-muted text-decoration-none mb-1">${esc(p.category_name)}</a>`
            : ''}

            <a href="product-details.php?slug=${encodeURIComponent(p.slug)}"
               class="text-decoration-none text-reset">
              <h6 class="card-title small mb-1 line-clamp-2">${esc(p.name)}</h6>
            </a>

            <div class="d-flex align-items-center gap-1 mb-2">
              ${stars(p.rating)}
              <small class="text-muted">(${p.review_count})</small>
            </div>

            <div class="mt-auto d-flex align-items-center justify-content-between">
              <div>${priceHtml}</div>
              <button class="btn btn-sm btn-primary add-to-cart-btn"
                      data-id="${p.id}"
                      data-name="${esc(p.name)}"
                      ${p.in_stock ? '' : 'disabled title="Out of stock"'}>
                <i class="bi bi-cart-plus"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
      }

      function renderGrid(items) {
        if (!items.length) {
          grid.innerHTML = '';
          empty.classList.remove('d-none');
          return;
        }
        empty.classList.add('d-none');
        grid.innerHTML = items.map(productCard).join('');
      }

      /* ---------- Render: pagination ---------- */
      function renderPagination() {
        if (state.pages <= 1) { pagination.innerHTML = ''; return; }

        const p = state.page, total = state.pages;
        const range = [];
        const from = Math.max(1, p - 2);
        const to = Math.min(total, p + 2);

        if (from > 1) range.push(1);
        if (from > 2) range.push('…');
        for (let i = from; i <= to; i++) range.push(i);
        if (to < total - 1) range.push('…');
        if (to < total) range.push(total);

        pagination.innerHTML = `
      <li class="page-item ${p === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${p - 1}">«</a>
      </li>
      ${range.map(n =>
          n === '…'
            ? `<li class="page-item disabled"><span class="page-link">…</span></li>`
            : `<li class="page-item ${n === p ? 'active' : ''}">
               <a class="page-link" href="#" data-page="${n}">${n}</a>
             </li>`
        ).join('')}
      <li class="page-item ${p === total ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${p + 1}">»</a>
      </li>
    `;
      }

      /* ---------- Full refresh ---------- */
      async function refresh() {
        writeURL();

        /* spinner */
        grid.innerHTML = `
      <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-3 mb-0">Loading products…</p>
      </div>`;

        empty.classList.add('d-none');

        try {
          const [listing, facets] = await Promise.all([
            api('list', buildQuery()),
            api('facets', buildQuery())
          ]);

          state.total = listing.total;
          state.pages = listing.pages || 1;

          renderFacets(facets);
          renderGrid(listing.items);
          renderPagination();
          renderChips();

          resultCount.textContent = listing.total
            ? `Showing ${listing.items.length} of ${listing.total} product${listing.total === 1 ? '' : 's'}`
            : 'No products';
        } catch (err) {
          grid.innerHTML = `
        <div class="col-12 text-center py-5 text-danger">
          <i class="bi bi-exclamation-triangle fs-1"></i>
          <p class="mt-3 mb-0">${esc(err.message)}</p>
        </div>`;
        }
      }

      /* ---------- Load init (facets + bounds + stats) ---------- */
      async function init() {
        try {
          const data = await api('init', buildQuery());
          state.bounds = data.bounds;
          state.total = data.listing.total;
          state.pages = data.listing.pages || 1;

          /* set slider bounds */
          priceRange.min = state.bounds.min;
          priceRange.max = state.bounds.max;
          priceRange.value = state.price_max ?? state.bounds.max;

          if (state.price_max === null) state.price_max = state.bounds.max;

          syncInputsFromState();

          renderFacets(data.facets);
          renderGrid(data.listing.items);
          renderPagination();
          renderChips();

          resultCount.textContent = data.listing.total
            ? `Showing ${data.listing.items.length} of ${data.listing.total} product${data.listing.total === 1 ? '' : 's'}`
            : 'No products';
        } catch (err) {
          grid.innerHTML = `
        <div class="col-12 text-center py-5 text-danger">
          <i class="bi bi-exclamation-triangle fs-1"></i>
          <p class="mt-3 mb-0">${esc(err.message)}</p>
        </div>`;
        }
      }

      function syncInputsFromState() {
        $('#shopSearch').value = state.search;
        $('#sortSelect').value = state.sort;
        $('#onSaleFilter').checked = state.on_sale;
        $('#inStockFilter').checked = state.in_stock;

        /* rating radios */
        const rSel = state.rating_min || 0;
        const rEl = document.querySelector(`input[name="rating"][value="${rSel}"]`);
        if (rEl) rEl.checked = true;

        priceRange.value = state.price_max ?? state.bounds.max;
        updatePriceLabel();
      }

      function updatePriceLabel() {
        const max = Number(priceRange.value);
        priceLabel.textContent = `${moneyInt(state.bounds.min)} – ${moneyInt(max)}`;
      }

      /* ---------- Events ---------- */
      $('#shopSearchForm').addEventListener('submit', (e) => {
        e.preventDefault();
        state.search = $('#shopSearch').value.trim();
        state.page = 1;
        refresh();
      });

      let searchTimer;
      $('#shopSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          refresh();
        }, 350);
      });

      $('#sortSelect').addEventListener('change', (e) => {
        state.sort = e.target.value;
        state.page = 1;
        refresh();
      });

      priceRange.addEventListener('input', () => {
        updatePriceLabel();
      });
      priceRange.addEventListener('change', () => {
        state.price_max = Number(priceRange.value);
        state.page = 1;
        refresh();
      });

      document.querySelectorAll('input[name="rating"]').forEach(r => {
        r.addEventListener('change', () => {
          state.rating_min = Number(r.value);
          state.page = 1;
          refresh();
        });
      });

      $('#onSaleFilter').addEventListener('change', (e) => {
        state.on_sale = e.target.checked;
        state.page = 1;
        refresh();
      });
      $('#inStockFilter').addEventListener('change', (e) => {
        state.in_stock = e.target.checked;
        state.page = 1;
        refresh();
      });

      pagination.addEventListener('click', (e) => {
        const a = e.target.closest('a[data-page]');
        if (!a) return;
        e.preventDefault();
        const n = parseInt(a.dataset.page, 10);
        if (!n || n === state.page || n < 1 || n > state.pages) return;
        state.page = n;
        refresh();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      $('#resetFilters').addEventListener('click', doReset);
      $('#emptyReset').addEventListener('click', doReset);

      function doReset() {
        state.search = '';
        state.categories.clear();
        state.price_max = state.bounds.max;
        state.price_min = null;
        state.rating_min = 0;
        state.on_sale = false;
        state.in_stock = false;
        state.sort = 'latest';
        state.page = 1;

        syncInputsFromState();
        refresh();
      }

      /* ---------- Add to cart (delegated) ---------- */
      grid.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-to-cart-btn');
        if (!btn || btn.disabled) return;

        const pid = Number(btn.dataset.id);
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
          const res = await fetch('cart_api.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: pid, quantity: 1 })
          }).then(r => r.json());

          if (!res.success) throw new Error(res.message || 'Could not add to cart.');

          const count = res.cart?.summary?.item_count ?? 0;
          document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count } }));

          btn.innerHTML = '<i class="bi bi-check-lg"></i>';
          setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = orig;
          }, 900);
        } catch (err) {
          alert(err.message);
          btn.disabled = false;
          btn.innerHTML = orig;
        }
      });

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderShell === 'function') {
          SV.renderShell();
        }
        readURL();
        init();
      });
    })();
  </script>

  <style>
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .product-card {
      transition: transform .25s ease, box-shadow .25s ease;
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .08) !important;
    }

    .product-image {
      transition: transform .35s ease;
    }

    .product-card:hover .product-image {
      transform: scale(1.06);
    }

    .btn-close-sm {
      padding: .15rem;
    }
  </style>
</body>

</html>