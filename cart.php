<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shopping Cart – ShopVerse</title>
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
    <div class="container">
      <h1 class="h3 mb-2">Shopping Cart</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Cart</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section">
    <div class="container">

      <!-- Loading -->
      <div id="cartLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-3 mb-0">Loading your cart…</p>
      </div>

      <!-- Empty state -->
      <div id="cartEmpty" class="text-center py-5 d-none">
        <i class="bi bi-cart-x" style="font-size:3rem;color:var(--sv-muted)"></i>
        <h4 class="mt-3">Your cart is empty</h4>
        <p class="text-muted-2">Looks like you haven't added anything yet.</p>
        <a href="shop.php" class="btn btn-primary btn-lg">Start Shopping</a>
      </div>

      <!-- Cart panel -->
      <div class="row g-4 d-none" id="cartPanel">
        <div class="col-lg-8">
          <div class="card p-3 p-md-4">
            <div id="cartItems"></div>
            <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
              <a href="shop.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left me-1"></i>Continue shopping
              </a>
              <button class="btn btn-light border text-danger" id="clearCart">
                <i class="bi bi-trash me-1"></i>Clear cart
              </button>
            </div>
          </div>

          <div class="card p-3 p-md-4 mt-4">
            <h6 class="mb-3">Have a coupon code?</h6>
            <form class="row g-2" id="couponForm">
              <div class="col-sm-8">
                <input class="form-control" name="code" id="couponCode"
                  placeholder="Enter coupon (try SAVE10 or SHOPVERSE20)">
              </div>
              <div class="col-sm-4 d-grid">
                <button class="btn btn-accent" type="submit">Apply Coupon</button>
              </div>
            </form>
            <div id="couponMsg" class="mt-2 small"></div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="summary-box" id="cartSummary"></div>
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
     |  Cart page — talks to cart_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'cart_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
       <text x="50%" y="50%" font-family="sans-serif" font-size="14"
             fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const loading = $('#cartLoading');
      const empty = $('#cartEmpty');
      const panel = $('#cartPanel');
      const itemsEl = $('#cartItems');
      const sumEl = $('#cartSummary');
      const couponMsg = $('#couponMsg');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);

      /* ---------- API ---------- */
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

      /* ---------- Render ---------- */
      function renderItem(it) {
        const variantBits = [];
        if (it.color) variantBits.push(`<span class="badge bg-light text-dark me-1">${esc(it.color)}</span>`);
        if (it.size) variantBits.push(`<span class="badge bg-light text-dark me-1">${esc(it.size)}</span>`);

        const stock = it.in_stock
          ? ''
          : `<div class="small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i>Only ${it.available_stock} left</div>`;

        return `
      <div class="d-flex flex-wrap align-items-center gap-3 py-3 border-bottom cart-row"
           data-pid="${it.product_id}" data-vid="${it.variant_id ?? ''}">
        <a href="product-details.php?slug=${encodeURIComponent(it.slug)}">
          <img src="${esc(it.image) || FALLBACK}" width="80" height="80"
               style="object-fit:cover;border-radius:8px"
               onerror="this.onerror=null;this.src='${FALLBACK}'">
        </a>
        <div class="flex-grow-1">
          <a href="product-details.php?slug=${encodeURIComponent(it.slug)}"
             class="text-decoration-none text-reset">
            <div class="fw-semibold">${esc(it.name)}</div>
          </a>
          <div class="small text-muted">SKU: ${esc(it.sku)}</div>
          ${variantBits.length ? `<div class="mt-1">${variantBits.join('')}</div>` : ''}
          ${stock}
        </div>
        <div class="text-end" style="min-width:120px">
          <div class="fw-semibold">${money(it.unit_price)}</div>
          <div class="small text-muted">each</div>
        </div>
        <div class="d-flex align-items-center" style="max-width:130px">
          <button class="btn btn-sm btn-outline-secondary" data-qty="-1" title="Decrease">
            <i class="bi bi-dash"></i>
          </button>
          <input type="number" class="form-control form-control-sm text-center mx-1 qty-input"
                 value="${it.quantity}" min="1" max="${it.available_stock || 1}"
                 style="width:60px">
          <button class="btn btn-sm btn-outline-secondary" data-qty="1" title="Increase">
            <i class="bi bi-plus"></i>
          </button>
        </div>
        <div class="text-end fw-bold" style="min-width:100px">${money(it.line_total)}</div>
        <button class="btn btn-sm btn-link text-danger" data-act="remove" title="Remove">
          <i class="bi bi-trash"></i>
        </button>
      </div>
    `;
      }

      function renderSummary(s, coupon) {
        const couponRow = coupon
          ? `<div class="d-flex justify-content-between text-success">
           <span>
             Discount <small class="text-muted">(${esc(coupon.code)})</small>
             <button class="btn btn-sm btn-link text-danger p-0 ms-1 align-baseline" id="removeCouponBtn"
                     title="Remove coupon"><i class="bi bi-x-circle"></i></button>
           </span>
           <span>− ${money(s.discount)}</span>
         </div>`
          : '';

        const couponWarn = coupon && s.coupon_warning
          ? `<div class="alert alert-warning py-2 small mb-2">${esc(s.coupon_warning)}</div>`
          : '';

        const freeNote = s.amount_to_free > 0
          ? `<div class="alert alert-info py-2 small mt-3 mb-0">
           Add ${money(s.amount_to_free)} more for <strong>free shipping</strong>.
         </div>`
          : '';

        sumEl.innerHTML = `
      <h5 class="mb-3">Order Summary</h5>
      <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>${money(s.subtotal)}</span></div>
      ${couponRow}
      ${couponWarn}
      <div class="d-flex justify-content-between mb-2"><span>Shipping</span>
        <span>${s.shipping > 0 ? money(s.shipping) : '<span class="text-success">Free</span>'}</span>
      </div>
      ${s.tax > 0 ? `<div class="d-flex justify-content-between mb-2"><span>Tax</span><span>${money(s.tax)}</span></div>` : ''}
      <hr>
      <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
        <span>Total</span><span>${money(s.total)}</span>
      </div>
      <a href="checkout.php" class="btn btn-primary w-100 btn-lg">
        Proceed to Checkout <i class="bi bi-arrow-right ms-1"></i>
      </a>
      ${freeNote}
    `;

        const rm = document.getElementById('removeCouponBtn');
        if (rm) {
          rm.addEventListener('click', async () => {
            try {
              const res = await api('coupon_remove', {}, 'POST');
              applyCartToUI(res.data);
            } catch (e) { alert(e.message); }
          });
        }
      }

      function applyCartToUI(cart) {
        loading.classList.add('d-none');

        if (!cart.items.length) {
          panel.classList.add('d-none');
          empty.classList.remove('d-none');
          updateHeaderCount(0);
          return;
        }

        empty.classList.add('d-none');
        panel.classList.remove('d-none');

        itemsEl.innerHTML = cart.items.map(renderItem).join('');
        renderSummary(cart.summary, cart.coupon);
        updateHeaderCount(cart.summary.item_count);

        /* prefill coupon input if applied */
        if (cart.coupon) {
          $('#couponCode').value = cart.coupon.code;
          couponMsg.innerHTML = `<span class="text-success">
        <i class="bi bi-check-circle me-1"></i>Coupon <strong>${esc(cart.coupon.code)}</strong> applied.
      </span>`;
        }
      }

      function updateHeaderCount(n) {
        /* tell the rest of the app (if it listens) */
        document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count: n } }));

        /* also patch any header badge if it exists */
        const badges = document.querySelectorAll('[data-cart-count]');
        badges.forEach(b => {
          b.textContent = n;
          b.classList.toggle('d-none', n === 0);
        });
      }

      /* ---------- Load ---------- */
      async function load() {
        loading.classList.remove('d-none');
        empty.classList.add('d-none');
        panel.classList.add('d-none');

        try {
          const res = await api('get');
          applyCartToUI(res.data);
        } catch (e) {
          loading.innerHTML =
            `<div class="alert alert-danger">${esc(e.message)}</div>`;
        }
      }

      /* ---------- Actions ---------- */
      async function changeQty(pid, vid, newQty) {
        try {
          const res = await api('set', {
            product_id: pid,
            variant_id: vid || null,
            quantity: newQty
          }, 'POST');
          applyCartToUI(res.data);
        } catch (e) {
          alert(e.message);
          load();
        }
      }

      async function removeLine(pid, vid) {
        if (!confirm('Remove this item from your cart?')) return;
        try {
          const res = await api('remove', {
            product_id: pid,
            variant_id: vid || null
          }, 'POST');
          applyCartToUI(res.data);
        } catch (e) {
          alert(e.message);
        }
      }

      /* ---------- Event delegation ---------- */
      itemsEl.addEventListener('click', (e) => {
        const row = e.target.closest('.cart-row');
        if (!row) return;

        const pid = Number(row.dataset.pid);
        const vid = row.dataset.vid ? Number(row.dataset.vid) : null;

        if (e.target.closest('[data-act="remove"]')) {
          return removeLine(pid, vid);
        }

        const qtyBtn = e.target.closest('[data-qty]');
        if (qtyBtn) {
          const input = row.querySelector('.qty-input');
          const step = Number(qtyBtn.dataset.qty);
          const next = Math.max(1, Number(input.value) + step);
          input.value = next;
          changeQty(pid, vid, next);
        }
      });

      itemsEl.addEventListener('change', (e) => {
        const input = e.target.closest('.qty-input');
        if (!input) return;

        const row = input.closest('.cart-row');
        const pid = Number(row.dataset.pid);
        const vid = row.dataset.vid ? Number(row.dataset.vid) : null;
        const qty = Math.max(1, Number(input.value) || 1);
        input.value = qty;
        changeQty(pid, vid, qty);
      });

      /* ---------- Coupon ---------- */
      $('#couponForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = $('#couponCode').value.trim();
        if (!code) return;

        couponMsg.innerHTML = '<span class="text-muted">Applying…</span>';

        try {
          const res = await api('coupon', { code }, 'POST');
          applyCartToUI(res.data);
          couponMsg.innerHTML =
            `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${esc(res.message)}</span>`;
        } catch (err) {
          couponMsg.innerHTML =
            `<span class="text-danger"><i class="bi bi-x-circle me-1"></i>${esc(err.message)}</span>`;
        }
      });

      /* ---------- Clear cart ---------- */
      $('#clearCart').addEventListener('click', async () => {
        if (!confirm('Remove all items from your cart?')) return;
        try {
          const res = await api('clear', {}, 'POST');
          couponMsg.innerHTML = '';
          $('#couponCode').value = '';
          applyCartToUI(res.data);
        } catch (e) { alert(e.message); }
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
</body>

</html>