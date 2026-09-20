<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout – ShopVerse</title>
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
      <h1 class="h3 mb-2">Checkout</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="cart.php">Cart</a></li>
          <li class="breadcrumb-item active">Checkout</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section" id="checkoutMain">

    <!-- Loading -->
    <div class="container" id="checkoutLoading">
      <div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-3 mb-0">Preparing your checkout…</p>
      </div>
    </div>

    <!-- Empty-cart / error gate -->
    <div class="container d-none" id="checkoutGate">
      <div class="text-center py-5">
        <i class="bi bi-exclamation-circle fs-1 text-warning"></i>
        <h4 class="mt-3" id="gateTitle">Your cart is empty</h4>
        <p class="text-muted-2" id="gateMsg">Add something to your cart before checking out.</p>
        <a href="shop.php" class="btn btn-primary btn-lg">Browse products</a>
      </div>
    </div>

    <!-- Main form -->
    <div class="container d-none" id="checkoutBody">
      <form id="checkoutForm" class="row g-4 needs-validation" novalidate>

        <div class="col-lg-8">

          <!-- Customer information -->
          <div class="card p-3 p-md-4 mb-4">
            <h5 class="mb-3"><span class="badge text-bg-primary me-2">1</span>Customer Information</h5>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">First Name *</label>
                <input class="form-control" name="firstName" required minlength="2">
                <div class="invalid-feedback" data-err="firstName">Please enter your first name.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name *</label>
                <input class="form-control" name="lastName" required minlength="2">
                <div class="invalid-feedback" data-err="lastName">Please enter your last name.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" class="form-control" name="email" required>
                <div class="invalid-feedback" data-err="email">Please enter a valid email.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" class="form-control" name="phone" required pattern="[0-9+\-\s\(\)]{7,20}">
                <div class="invalid-feedback" data-err="phone">Please enter a valid phone number.</div>
              </div>
            </div>
          </div>

          <!-- Shipping address -->
          <div class="card p-3 p-md-4 mb-4">
            <h5 class="mb-3"><span class="badge text-bg-primary me-2">2</span>Shipping Address</h5>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Street Address *</label>
                <input class="form-control" name="address" required>
                <div class="invalid-feedback" data-err="address">Please enter your address.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">City *</label>
                <input class="form-control" name="city" required>
                <div class="invalid-feedback" data-err="city">Please enter your city.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">State / Province *</label>
                <input class="form-control" name="state" required>
                <div class="invalid-feedback" data-err="state">Please enter your state.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Postal Code *</label>
                <input class="form-control" name="postal" required pattern="[A-Za-z0-9\s\-]{3,10}">
                <div class="invalid-feedback" data-err="postal">Please enter a valid postal code.</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Country *</label>
                <select class="form-select" name="country" required>
                  <option value="">Choose country...</option>
                  <option>Pakistan</option>
                  <option>United States</option>
                  <option>United Kingdom</option>
                  <option>Canada</option>
                  <option>Australia</option>
                  <option>Germany</option>
                  <option>France</option>
                  <option>India</option>
                  <option>United Arab Emirates</option>
                </select>
                <div class="invalid-feedback" data-err="country">Please select your country.</div>
              </div>
              <div class="col-12">
                <label class="form-label">Order notes (optional)</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Delivery instructions..."></textarea>
              </div>
            </div>
          </div>

          <!-- Payment -->
          <div class="card p-3 p-md-4">
            <h5 class="mb-3"><span class="badge text-bg-primary me-2">3</span>Payment Method</h5>

            <div class="list-group mb-3">
              <label class="list-group-item d-flex gap-2 align-items-center">
                <input class="form-check-input flex-shrink-0 m-0" type="radio" name="payment" value="Cash on Delivery"
                  checked required>
                <span><i class="bi bi-cash-coin me-2 text-success"></i>
                  <strong>Cash on Delivery</strong><br>
                  <small class="text-muted-2">Pay in cash when your order arrives.</small></span>
              </label>
              <label class="list-group-item d-flex gap-2 align-items-center">
                <input class="form-check-input flex-shrink-0 m-0" type="radio" name="payment" value="Credit Card">
                <span><i class="bi bi-credit-card-2-front me-2 text-primary"></i>
                  <strong>Credit / Debit Card</strong><br>
                  <small class="text-muted-2">Visa, Mastercard, Amex — secured by SSL.</small></span>
              </label>
              <label class="list-group-item d-flex gap-2 align-items-center">
                <input class="form-check-input flex-shrink-0 m-0" type="radio" name="payment" value="Bank Transfer">
                <span><i class="bi bi-bank me-2 text-warning"></i>
                  <strong>Bank Transfer</strong><br>
                  <small class="text-muted-2">Transfer directly to our business account.</small></span>
              </label>
            </div>

            <div class="row g-3 d-none" id="cardFields">
              <div class="col-md-6"><label class="form-label">Card Number</label>
                <input class="form-control" placeholder="4242 4242 4242 4242" maxlength="19">
              </div>
              <div class="col-md-3"><label class="form-label">Expiry</label>
                <input class="form-control" placeholder="MM/YY" maxlength="5">
              </div>
              <div class="col-md-3"><label class="form-label">CVC</label>
                <input class="form-control" placeholder="123" maxlength="4">
              </div>
            </div>

            <div class="alert alert-info d-none mb-0" id="bankFields">
              <strong>ShopVerse Ltd.</strong><br>
              IBAN: GB29 SHOP 6016 1331 9268 19<br>
              Reference your order number when transferring.
            </div>

            <div class="form-check mt-3">
              <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
              <label class="form-check-label" for="terms">
                I agree to the <a href="#">terms &amp; conditions</a> and privacy policy *
              </label>
              <div class="invalid-feedback" data-err="terms">
                You must accept the terms to continue.
              </div>
            </div>

            <button class="btn btn-primary btn-lg w-100 mt-3" type="submit" id="placeOrderBtn">
              <i class="bi bi-lock-fill me-1"></i>Place Order
            </button>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="summary-box" id="checkoutSummary"></div>
        </div>
      </form>
    </div>
  </main>

  <!-- Success modal -->
  <div class="modal fade" id="successModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 text-center">
        <div class="modal-body p-4 p-md-5">
          <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
            style="width:82px;height:82px;background:#dcfce7">
            <i class="bi bi-check-lg text-success" style="font-size:2.6rem"></i>
          </div>
          <h3 class="mb-2">Order placed successfully!</h3>
          <p class="text-muted-2">
            Thank you for shopping with ShopVerse. A confirmation email has been sent to
            <strong id="successEmail"></strong>.
          </p>
          <div class="filter-box text-start my-4">
            <div class="summary-row"><span>Order number</span><strong id="successOrderId"></strong></div>
            <div class="summary-row"><span>Payment method</span><strong id="successPayment"></strong></div>
            <div class="summary-row"><span>Amount paid</span><strong id="successTotal"></strong></div>
          </div>
          <div class="d-grid gap-2">
            <a href="orders.php" class="btn btn-primary">View my orders</a>
            <a href="shop.php" class="btn btn-outline-primary">Continue shopping</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="sv-footer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>
  <script src="js/auth.js"></script>

  <script>
    /* =========================================================
     |  Checkout page — talks to checkout_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'checkout_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const loading = $('#checkoutLoading');
      const gate = $('#checkoutGate');
      const body = $('#checkoutBody');
      const form = $('#checkoutForm');
      const summaryEl = $('#checkoutSummary');
      const btn = $('#placeOrderBtn');

      const successModal = new bootstrap.Modal('#successModal');

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

        // we still want the payload even on success:false (for field errors)
        return { ok: res.ok, data: json };
      }

      /* ---------- Render summary ---------- */
      function renderSummary(d) {
        const itemsHtml = d.items.map(it => {
          const variantBits = [];
          if (it.color) variantBits.push(esc(it.color));
          if (it.size) variantBits.push(esc(it.size));
          const variant = variantBits.length
            ? `<small class="text-muted d-block">${variantBits.join(' · ')}</small>`
            : '';

          return `
        <div class="d-flex align-items-center gap-2 py-2 border-bottom">
          <img src="${esc(it.image) || FALLBACK}"
               width="48" height="48" style="object-fit:cover;border-radius:6px"
               onerror="this.onerror=null;this.src='${FALLBACK}'">
          <div class="flex-grow-1">
            <div class="fw-semibold small">${esc(it.name)}</div>
            ${variant}
            <small class="text-muted">× ${it.quantity}</small>
          </div>
          <div class="text-end small fw-semibold">${money(it.line_total)}</div>
        </div>
      `;
        }).join('');

        const couponRow = d.coupon
          ? `<div class="d-flex justify-content-between text-success">
           <span>Discount <small class="text-muted">(${esc(d.coupon.code)})</small></span>
           <span>− ${money(d.summary.discount)}</span>
         </div>`
          : '';

        summaryEl.innerHTML = `
      <h5 class="mb-3">Order Summary</h5>

      <div class="mb-3" style="max-height:280px;overflow-y:auto">
        ${itemsHtml}
      </div>

      <div class="d-flex justify-content-between mb-2">
        <span>Subtotal (${d.summary.item_count} item${d.summary.item_count === 1 ? '' : 's'})</span>
        <span>${money(d.summary.subtotal)}</span>
      </div>
      ${couponRow}
      <div class="d-flex justify-content-between mb-2">
        <span>Shipping</span>
        <span>${d.summary.shipping > 0 ? money(d.summary.shipping)
            : '<span class="text-success">Free</span>'}</span>
      </div>
      ${d.summary.tax > 0
            ? `<div class="d-flex justify-content-between mb-2"><span>Tax</span><span>${money(d.summary.tax)}</span></div>`
            : ''}
      <hr>
      <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
        <span>Total</span><span>${money(d.summary.total)}</span>
      </div>
    `;
      }

      /* ---------- Prefill user ---------- */
      function prefillUser(user) {
        if (!user) return;
        if (user.first_name) form.firstName.value = user.first_name;
        if (user.last_name) form.lastName.value = user.last_name;
        if (user.email) form.email.value = user.email;
        if (user.phone) form.phone.value = user.phone;
      }

      /* ---------- Load ---------- */
      async function load() {
        loading.classList.remove('d-none');
        body.classList.add('d-none');
        gate.classList.add('d-none');

        const { data } = await api('data');

        loading.classList.add('d-none');

        if (!data.success) {
          $('#gateTitle').textContent = 'Checkout unavailable';
          $('#gateMsg').textContent = data.message || 'Something went wrong.';
          gate.classList.remove('d-none');
          return;
        }

        prefillUser(data.data.user);
        renderSummary(data.data);
        body.classList.remove('d-none');
      }

      /* ---------- Payment toggle ---------- */
      document.querySelectorAll('input[name="payment"]').forEach(r => {
        r.addEventListener('change', () => {
          const v = r.value;
          $('#cardFields').classList.toggle('d-none', v !== 'Credit Card');
          $('#bankFields').classList.toggle('d-none', v !== 'Bank Transfer');
        });
      });

      /* ---------- Submit ---------- */
      form.addEventListener('submit', async (e) => {
        e.preventDefault();

        /* clear previous custom errors */
        form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          const bad = form.querySelector(':invalid');
          if (bad) bad.focus();
          return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing order…';

        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());
        data.terms = data.terms ? 1 : 0;

        try {
          const { data: res } = await api('place', data, 'POST');

          if (!res.success) {
            /* field-level errors */
            if (res.errors) {
              form.classList.add('was-validated');
              Object.entries(res.errors).forEach(([field, msg]) => {
                const input = form.querySelector(`[name="${field}"]`);
                const fb = form.querySelector(`[data-err="${field}"]`);
                if (input) input.classList.add('is-invalid');
                if (fb) fb.textContent = msg;
              });
              const firstBad = form.querySelector('.is-invalid');
              if (firstBad) firstBad.focus();
            } else {
              alert(res.message || 'Could not place the order.');
            }
            return;
          }

          /* success */
          const o = res.order;
          $('#successEmail').textContent = o.email;
          $('#successOrderId').textContent = '#' + o.order_number;
          $('#successPayment').textContent = o.payment_label;
          $('#successTotal').textContent = money(o.total);

          successModal.show();

          /* notify the rest of the app (header badge etc.) */
          document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count: 0 } }));
        } catch (err) {
          alert('Unexpected error. Please try again.');
          console.error(err);
        } finally {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-lock-fill me-1"></i>Place Order';
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
</body>

</html>