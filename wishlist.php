<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Wishlist – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <header id="sv-header" class="sv-sticky"></header>

  <section class="page-head">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h1 class="h3 mb-2">My Wishlist</h1>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active">Wishlist</li>
          </ol>
        </nav>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" id="wishAddAll" disabled>
          <i class="bi bi-cart-plus me-1"></i>Add all to cart
        </button>
        <button class="btn btn-light border text-danger" id="wishClear" disabled>
          <i class="bi bi-trash me-1"></i>Clear
        </button>
      </div>
    </div>
  </section>

  <main class="section">
    <div class="container">

      <!-- Loading -->
      <div id="wishLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-3 mb-0">Loading your wishlist…</p>
      </div>

      <!-- Empty -->
      <div id="wishEmpty" class="text-center py-5 d-none">
        <i class="bi bi-heart fs-1 text-muted"></i>
        <h4 class="mt-3">Your wishlist is empty</h4>
        <p class="text-muted-2">Save items you love so you can find them easily later.</p>
        <a href="shop.php" class="btn btn-primary btn-lg">Browse products</a>
      </div>

      <!-- Grid -->
      <div id="wishWrap" class="d-none">
        <p class="text-muted-2" id="wishCountLabel">0 item(s) saved</p>
        <div class="row g-3 g-lg-4" id="wishGrid"></div>
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
     |  Wishlist page — talks to wishlist_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'wishlist_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
       <text x="50%" y="50%" font-family="sans-serif" font-size="18"
             fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const loading = $('#wishLoading');
      const empty = $('#wishEmpty');
      const wrap = $('#wishWrap');
      const grid = $('#wishGrid');
      const countLbl = $('#wishCountLabel');
      const addAllBtn = $('#wishAddAll');
      const clearBtn = $('#wishClear');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);

      async function api(action, payload = {}, method = 'GET') {
        let url = `${API}?action=${encodeURIComponent(action)}`;
        const opts = { method };

        if (method === 'GET') {
          const q = new URLSearchParams(payload).toString();
          if (q) url += '&' + q;
        } else {
          opts.headers = { 'Content-Type': 'application/json' };
          opts.body = JSON.stringify(payload);
        }

        const res = await fetch(url, opts);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json;
      }

      /* ---------- Stars ---------- */
      function stars(rating) {
        const r = Math.round(rating);
        let html = '';
        for (let i = 1; i <= 5; i++) {
          html += `<i class="bi bi-star${i <= r ? '-fill' : ''} text-warning"></i>`;
        }
        return html;
      }

      /* ---------- Card ---------- */
      function card(p) {
        const img = p.image || FALLBACK;

        const flags = [];
        if (p.discount_percent >= 5) flags.push(`<span class="badge bg-danger">-${p.discount_percent}%</span>`);
        if (p.best_seller) flags.push(`<span class="badge bg-warning text-dark">Best</span>`);
        if (p.new_arrival) flags.push(`<span class="badge bg-info text-dark">New</span>`);

        const outOfStock = p.is_out_of_stock
          ? `<div class="badge bg-dark position-absolute" style="top:8px;right:8px">Out of stock</div>`
          : '';

        const priceHtml = p.old_price
          ? `<span class="fw-bold text-primary">${money(p.price)}</span>
         <small class="text-muted text-decoration-line-through ms-1">${money(p.old_price)}</small>`
          : `<span class="fw-bold text-primary">${money(p.price)}</span>`;

        return `
      <div class="col-6 col-md-4 col-lg-3 wish-card" data-pid="${p.id}">
        <div class="card border-0 shadow-sm h-100 product-card position-relative">

          <button class="btn btn-sm btn-light border rounded-circle position-absolute"
                  style="top:8px;right:8px;z-index:3;width:34px;height:34px"
                  data-act="remove"
                  title="Remove from wishlist">
            <i class="bi bi-heart-fill text-danger"></i>
          </button>

          <div class="position-absolute d-flex gap-1" style="top:8px;left:8px;z-index:2">
            ${flags.join(' ')}
          </div>
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
                      data-act="add-cart"
                      data-id="${p.id}"
                      ${p.is_out_of_stock ? 'disabled title="Out of stock"' : ''}>
                <i class="bi bi-cart-plus"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
      }

      /* ---------- Render ---------- */
      function render(items, count) {
        loading.classList.add('d-none');

        if (!items.length) {
          wrap.classList.add('d-none');
          empty.classList.remove('d-none');
          addAllBtn.disabled = true;
          clearBtn.disabled = true;
          document.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count: 0 } }));
          return;
        }

        empty.classList.add('d-none');
        wrap.classList.remove('d-none');

        grid.innerHTML = items.map(card).join('');
        countLbl.textContent =
          `${count} item${count === 1 ? '' : 's'} saved`;

        addAllBtn.disabled = false;
        clearBtn.disabled = false;

        document.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count } }));
      }

      /* ---------- Load ---------- */
      async function load() {
        loading.classList.remove('d-none');
        empty.classList.add('d-none');
        wrap.classList.add('d-none');

        try {
          const res = await api('list');
          const data = res.data || {};
          render(data.items || [], data.count || 0);
        } catch (err) {
          loading.innerHTML = `
        <div class="alert alert-danger">${esc(err.message)}</div>`;
        }
      }

      /* ---------- Actions ---------- */
      async function removeOne(pid) {
        try {
          const res = await api('remove', { product_id: pid }, 'POST');
          const row = grid.querySelector(`.wish-card[data-pid="${pid}"]`);
          if (row) {
            row.style.transition = 'opacity .25s ease';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 220);
          }
          /* update the count immediately */
          const left = grid.querySelectorAll('.wish-card').length - 1;
          setTimeout(async () => {
            if (left <= 0) return load();
            countLbl.textContent = `${left} item${left === 1 ? '' : 's'} saved`;
            document.dispatchEvent(new CustomEvent('wishlist:updated', { detail: { count: left } }));
          }, 260);
        } catch (e) {
          alert(e.message);
        }
      }

      async function addOneToCart(pid, btn) {
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
        } catch (e) {
          alert(e.message);
          btn.disabled = false;
          btn.innerHTML = orig;
        }
      }

      /* ---------- Toolbar buttons ---------- */
      addAllBtn.addEventListener('click', async () => {
        addAllBtn.disabled = true;
        const orig = addAllBtn.innerHTML;
        addAllBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Working…';

        try {
          const res = await api('add_all_to_cart', {}, 'POST');

          /* refresh cart badge */
          document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count: null } }));

          alert(res.message);
          await load();
        } catch (e) {
          alert(e.message);
        } finally {
          addAllBtn.disabled = false;
          addAllBtn.innerHTML = orig;
        }
      });

      clearBtn.addEventListener('click', async () => {
        if (!confirm('Remove all items from your wishlist?')) return;

        clearBtn.disabled = true;
        const orig = clearBtn.innerHTML;
        clearBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Clearing…';

        try {
          await api('clear', {}, 'POST');
          await load();
        } catch (e) {
          alert(e.message);
        } finally {
          clearBtn.disabled = false;
          clearBtn.innerHTML = orig;
        }
      });

      /* ---------- Delegated grid actions ---------- */
      grid.addEventListener('click', (e) => {
        const card = e.target.closest('.wish-card');
        if (!card) return;
        const pid = Number(card.dataset.pid);

        if (e.target.closest('[data-act="remove"]')) {
          return removeOne(pid);
        }
        const cartBtn = e.target.closest('[data-act="add-cart"]');
        if (cartBtn && !cartBtn.disabled) {
          return addOneToCart(pid, cartBtn);
        }
      });

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderShell === 'function') {
          SV.renderShell();
        }
        load();
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
  </style>
</body>

</html>