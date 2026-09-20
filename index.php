<?php
require_once __DIR__ . '/includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ShopVerse – Modern Online Store</title>
  <meta name="description"
    content="ShopVerse is a modern multi-category online store with fast shipping, secure checkout and thousands of curated products.">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <!-- Header (rendered by app.js) -->
  <header id="sv-header" class="sv-sticky" data-page="home"></header>

  <main>
    <!-- ===== Hero ===== -->
    <section class="sv-hero">
      <div class="container">
        <div class="row align-items-center g-5">
          <div class="col-lg-6">
            <span class="badge-pill d-inline-block mb-3">
              <i class="bi bi-lightning-charge-fill me-1"></i>Mid-season sale · up to 40% off
            </span>
            <h1 class="mb-3">
              Discover products you'll love at
              <span style="color:var(--sv-primary)">ShopVerse</span>
            </h1>
            <p class="lead text-muted-2 mb-4" style="max-width:520px">
              Curated products across electronics, fashion, home and beauty — with free delivery over $99,
              30-day returns and secure checkout.
            </p>
            <div class="d-flex flex-wrap gap-2 mb-4">
              <a href="shop.php" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-bag me-2"></i>Shop Now
              </a>
              <a href="categories.php" class="btn btn-outline-primary btn-lg px-4">Browse Categories</a>
            </div>

            <div class="row g-3 text-center" style="max-width:480px" id="heroStats">
              <div class="col-4">
                <div class="sv-stat" data-stat="products">—</div>
                <small class="text-muted-2">Products</small>
              </div>
              <div class="col-4">
                <div class="sv-stat" data-stat="customers">—</div>
                <small class="text-muted-2">Happy customers</small>
              </div>
              <div class="col-4">
                <div class="sv-stat" data-stat="rating">—</div>
                <small class="text-muted-2">Avg. rating</small>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <img class="sv-hero-img" style="max-height:470px"
              src="https://images.pexels.com/photos/5868130/pexels-photo-5868130.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200"
              alt="Happy customer holding ShopVerse shopping bags">
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Service features ===== -->
    <section class="section-sm">
      <div class="container">
        <div class="row g-3">
          <div class="col-6 col-lg-3">
            <div class="feature-item"><i class="bi bi-truck"></i>
              <div><strong>Free Shipping</strong><br><small class="text-muted-2">On orders over $99</small></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="feature-item"><i class="bi bi-arrow-counterclockwise"></i>
              <div><strong>30-Day Returns</strong><br><small class="text-muted-2">Hassle-free refunds</small></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="feature-item"><i class="bi bi-shield-check"></i>
              <div><strong>Secure Payment</strong><br><small class="text-muted-2">SSL protected</small></div>
            </div>
          </div>
          <div class="col-6 col-lg-3">
            <div class="feature-item"><i class="bi bi-headset"></i>
              <div><strong>24/7 Support</strong><br><small class="text-muted-2">We're here to help</small></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Featured categories ===== -->
    <section class="section bg-soft">
      <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
          <div>
            <h2 class="section-title">Featured Categories</h2>
            <p class="section-sub mb-0">Shop by the categories our customers love most</p>
          </div>
          <a href="categories.php" class="btn btn-outline-primary btn-sm">
            View all <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row g-3" id="homeCategories">
          <div class="col-12 text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Best sellers ===== -->
    <section class="section">
      <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
          <div>
            <h2 class="section-title">Best Selling Products</h2>
            <p class="section-sub mb-0">Loved and reordered by thousands of shoppers</p>
          </div>
          <a href="shop.php?sort=popular" class="btn btn-outline-primary btn-sm">
            View all <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row g-3 g-lg-4" id="bestSellers">
          <div class="col-12 text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Special offer ===== -->
    <section class="section-sm">
      <div class="container">
        <div class="offer-banner p-4 p-lg-5">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <span class="badge text-bg-light text-dark mb-2">Limited time offer</span>
              <h2 class="mb-2">Flash Sale — Save up to 40% on selected items</h2>
              <p class="mb-4 opacity-75">
                Use coupon code <strong>SHOPVERSE20</strong> at checkout for an extra 20% off your entire order.
              </p>
              <div class="countdown d-flex gap-2 mb-4" id="countdown"></div>
              <a href="shop.php?sort=price-asc" class="btn btn-light btn-lg text-primary fw-bold px-4">
                Grab the deal
              </a>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
              <img
                src="https://images.pexels.com/photos/5869605/pexels-photo-5869605.jpeg?auto=compress&cs=tinysrgb&fit=crop&h=627&w=1200"
                class="img-fluid rounded-4" alt="Special offer">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== New arrivals ===== -->
    <section class="section">
      <div class="container">
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
          <div>
            <h2 class="section-title">New Arrivals</h2>
            <p class="section-sub mb-0">Fresh drops added to the store this week</p>
          </div>
          <a href="shop.php?sort=latest" class="btn btn-outline-primary btn-sm">
            View all <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row g-3 g-lg-4" id="newArrivals">
          <div class="col-12 text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Trending + About ===== -->
    <section class="section bg-soft" id="about">
      <div class="container">
        <div class="row g-5 align-items-center">
          <div class="col-lg-5">
            <h2 class="section-title">Trending Right Now</h2>
            <p class="text-muted-2">
              ShopVerse was founded with one goal: make online shopping simple, honest and enjoyable. We work
              directly with trusted suppliers, keep margins fair and invest in a support team that actually replies.
            </p>
            <ul class="list-unstyled">
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Curated catalogue of thousands
                of products</li>
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Same-day dispatch before 3pm
              </li>
              <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Buyer protection on every order
              </li>
            </ul>
            <a href="shop.php" class="btn btn-primary">Explore the store</a>
          </div>
          <div class="col-lg-7">
            <div class="row g-3" id="trending">
              <div class="col-12 text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Reviews ===== -->
    <section class="section">
      <div class="container">
        <div class="text-center mb-4">
          <h2 class="section-title">What Our Customers Say</h2>
          <p class="section-sub" id="reviewSummary">Real reviews from verified buyers</p>
        </div>
        <div class="row g-4" id="homeReviews">
          <div class="col-12 text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== Newsletter ===== -->
    <section class="section-sm" id="contact">
      <div class="container">
        <div class="newsletter">
          <div class="row align-items-center g-4">
            <div class="col-lg-6">
              <h3 class="mb-2">Join the ShopVerse newsletter</h3>
              <p class="mb-0">
                Get early access to sales, new arrivals and a 10% welcome coupon.
                No spam, unsubscribe anytime.
              </p>
            </div>
            <div class="col-lg-6">
              <form class="d-flex gap-2 flex-column flex-sm-row" id="newsletterForm">
                <input type="email" class="form-control form-control-lg" placeholder="Enter your email address"
                  required>
                <button class="btn btn-primary btn-lg px-4" type="submit">Subscribe</button>
              </form>
              <small class="d-block mt-2">By subscribing you agree to our privacy policy.</small>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== FAQ ===== -->
    <section class="section" id="faq">
      <div class="container">
        <div class="row g-4">
          <div class="col-lg-5">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="text-muted-2">
              Can't find what you're looking for? Email
              <a href="mailto:help@shopverse.com">help@shopverse.com</a> or call +1 202 555 0199.
            </p>
          </div>
          <div class="col-lg-7">
            <div class="accordion" id="faqAcc">
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button" data-bs-toggle="collapse" data-bs-target="#f1">
                    How long does delivery take?
                  </button>
                </h2>
                <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#faqAcc">
                  <div class="accordion-body text-muted-2">
                    Standard delivery takes 2–4 business days. Express delivery arrives within 24 hours in most metro
                    areas.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f2">
                    What is your return policy?
                  </button>
                </h2>
                <div id="f2" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                  <div class="accordion-body text-muted-2">
                    You may return any unused item within 30 days for a full refund.
                    Return shipping is free within the United States.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f3">
                    Which payment methods do you accept?
                  </button>
                </h2>
                <div id="f3" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                  <div class="accordion-body text-muted-2">
                    We accept all major credit and debit cards, bank transfer and cash on delivery in selected regions.
                  </div>
                </div>
              </div>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#f4">
                    Do you ship internationally?
                  </button>
                </h2>
                <div id="f4" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                  <div class="accordion-body text-muted-2">
                    Yes — we ship to over 60 countries. Duties and taxes are calculated transparently at checkout.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  <div id="sv-footer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>
  <script src="js/auth.js"></script>

  <script>
    /* =========================================================
     |  Homepage — talks to home_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'home_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
         <rect width="100%" height="100%" fill="#f1f3f5"/>
         <text x="50%" y="50%" font-family="sans-serif" font-size="18"
               fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
       </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);
      const num = (n) => Number(n || 0).toLocaleString();
      const fmtDate = (s) => {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
      };

      function stars(rating) {
        const r = Math.round(rating);
        let html = '';
        for (let i = 1; i <= 5; i++) {
          html += `<i class="bi bi-star${i <= r ? '-fill' : ''} text-warning"></i>`;
        }
        return html;
      }

      async function api(action = 'init', params = {}) {
        const q = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`${API}?${q}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      /* ---------- Hero stats ---------- */
      function renderStats(s) {
        const map = {
          products: s.products_label,
          customers: s.customers >= 1000
            ? (s.customers / 1000).toFixed(1).replace(/\.0$/, '') + 'k+'
            : num(s.customers) + '+',
          rating: s.avg_rating.toFixed(1) + '★'
        };
        Object.entries(map).forEach(([key, val]) => {
          const el = document.querySelector(`[data-stat="${key}"]`);
          if (el) el.textContent = val;
        });
      }

      /* ---------- Category card ---------- */
      function categoryCard(c) {
        const img = c.image || FALLBACK;
        return `
        <div class="col-6 col-md-4 col-lg-2">
          <a href="shop.php?category=${encodeURIComponent(c.slug)}"
             class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm h-100 text-center p-3 category-tile">
              <div class="mx-auto mb-2 rounded-circle overflow-hidden"
                   style="width:76px;height:76px">
                <img src="${esc(img)}" alt="${esc(c.name)}" loading="lazy"
                     class="w-100 h-100" style="object-fit:cover"
                     onerror="this.onerror=null;this.src='${FALLBACK}'">
              </div>
              <div class="fw-semibold small mb-1">${esc(c.name)}</div>
              <small class="text-muted">${num(c.product_count)} items</small>
            </div>
          </a>
        </div>
      `;
      }

      /* ---------- Product card ---------- */
      function productCard(p, compact = false) {
        const img = p.image || FALLBACK;

        const flags = [];
        if (p.discount_percent >= 5) flags.push(`<span class="badge bg-danger">-${p.discount_percent}%</span>`);
        if (p.best_seller && !compact) flags.push(`<span class="badge bg-warning text-dark">Best</span>`);
        if (p.new_arrival && !compact) flags.push(`<span class="badge bg-info text-dark">New</span>`);

        const outOfStock = !p.in_stock
          ? `<div class="badge bg-dark position-absolute" style="top:48px;right:8px">Out of stock</div>`
          : '';

        const priceHtml = p.old_price
          ? `<span class="fw-bold text-primary">${money(p.price)}</span>
           <small class="text-muted text-decoration-line-through ms-1">${money(p.old_price)}</small>`
          : `<span class="fw-bold text-primary">${money(p.price)}</span>`;

        const colClass = compact ? 'col-6' : 'col-6 col-md-4 col-lg-3';

        return `
        <div class="${colClass}">
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
                        ${p.in_stock ? '' : 'disabled title="Out of stock"'}>
                  <i class="bi bi-cart-plus"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
      }

      /* ---------- Testimonial ---------- */
      function testimonialCard(t) {
        return `
        <div class="col-md-4">
          <div class="card border-0 shadow-sm h-100 p-4 review-card">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="d-flex align-items-center justify-content-center rounded-circle fw-bold text-white bg-primary"
                   style="width:48px;height:48px">${esc(t.initials)}</div>
              <div>
                <div class="fw-semibold">${esc(t.user_name)}</div>
                <small class="text-muted">${fmtDate(t.created_at)}</small>
              </div>
            </div>
            <div class="mb-2">${stars(t.rating)}</div>
            ${t.title ? `<div class="fw-semibold mb-1">${esc(t.title)}</div>` : ''}
            <p class="text-muted-2 mb-3 line-clamp-3">"${esc(t.comment || '')}"</p>
            <a href="product-details.php?slug=${encodeURIComponent(t.product_slug)}"
               class="small text-decoration-none mt-auto">
              <i class="bi bi-box-seam me-1"></i>${esc(t.product_name)}
            </a>
          </div>
        </div>
      `;
      }

      /* ---------- Render sections ---------- */
      function renderCategories(items) {
        const el = $('#homeCategories');
        if (!items.length) {
          el.innerHTML = `<div class="col-12 text-center py-4 text-muted">No categories yet.</div>`;
          return;
        }
        el.innerHTML = items.map(categoryCard).join('');
      }

      function renderGrid(id, items, compact = false) {
        const el = $(id);
        if (!items.length) {
          el.innerHTML = `<div class="col-12 text-center py-4 text-muted">No products yet.</div>`;
          return;
        }
        el.innerHTML = items.map(p => productCard(p, compact)).join('');
      }

      function renderTestimonials(items) {
        const el = $('#homeReviews');
        const sum = $('#reviewSummary');

        if (!items.length) {
          el.innerHTML = `<div class="col-12 text-center py-4 text-muted">No reviews yet.</div>`;
          sum.textContent = 'Be the first to review a product!';
          return;
        }

        el.innerHTML = items.map(testimonialCard).join('');
        sum.textContent = 'Real reviews from verified buyers';
      }

      /* ---------- Countdown (2 days rolling) ---------- */
      function startCountdown() {
        const el = $('#countdown');
        if (!el) return;

        /* set an end time in localStorage so it doesn't reset on reload */
        let end = Number(localStorage.getItem('sv_flash_end') || 0);
        if (!end || end < Date.now()) {
          end = Date.now() + (2 * 24 * 60 * 60 * 1000); // 2 days from now
          localStorage.setItem('sv_flash_end', String(end));
        }

        const labels = ['Days', 'Hours', 'Mins', 'Secs'];

        function tick() {
          const diff = Math.max(0, end - Date.now());
          const days = Math.floor(diff / 86400000);
          const hours = Math.floor((diff % 86400000) / 3600000);
          const mins = Math.floor((diff % 3600000) / 60000);
          const secs = Math.floor((diff % 60000) / 1000);
          const values = [days, hours, mins, secs];

          el.innerHTML = values.map((v, i) => `
          <div class="text-center">
            <div class="countdown-num">${String(v).padStart(2, '0')}</div>
            <small class="d-block opacity-75">${labels[i]}</small>
          </div>
        `).join('');
        }

        tick();
        setInterval(tick, 1000);
      }

      /* ---------- Add to cart (delegated) ---------- */
      document.addEventListener('click', async (e) => {
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

      /* ---------- Load everything ---------- */
      async function load() {
        try {
          const data = await api('init');

          renderStats(data.stats);
          renderCategories(data.categories);
          renderGrid('#bestSellers', data.best);
          renderGrid('#newArrivals', data.new_arrivals);
          renderGrid('#trending', data.trending, true);
          renderTestimonials(data.testimonials);
        } catch (e) {
          console.error('[home]', e);
          ['#homeCategories', '#bestSellers', '#newArrivals', '#trending', '#homeReviews']
            .forEach(sel => {
              const el = $(sel);
              if (el) el.innerHTML =
                `<div class="col-12 text-center py-4 text-danger">
                 <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                 ${esc(e.message)}
               </div>`;
            });
        }
      }

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderShell === 'function') {
          SV.renderShell();
        }
        startCountdown();
        load();

        $('#newsletterForm')?.addEventListener('submit', (e) => {
          e.preventDefault();
          const msg = 'Thanks for subscribing! Check your inbox for a 10% coupon.';
          if (window.SV && typeof SV.toast === 'function') SV.toast(msg);
          else alert(msg);
          e.target.reset();
        });
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

    .line-clamp-3 {
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .category-tile {
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .category-tile:hover {
      transform: translateY(-3px);
      box-shadow: 0 .75rem 1.25rem rgba(0, 0, 0, .08) !important;
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

    .review-card {
      transition: transform .2s ease;
    }

    .review-card:hover {
      transform: translateY(-2px);
    }

    .countdown-num {
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-weight: 800;
      font-size: 1.6rem;
      line-height: 1;
      background: rgba(255, 255, 255, .15);
      padding: .5rem .75rem;
      border-radius: .5rem;
      min-width: 56px;
    }
  </style>
</body>

</html>