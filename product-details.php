<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Product Details – ShopVerse</title>
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
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
          <li class="breadcrumb-item active" id="bcName">Product</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section">
    <div class="container">

      <!-- Loading -->
      <div id="pdLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-3 mb-0">Loading product…</p>
      </div>

      <!-- Not found -->
      <div id="pdNotFound" class="text-center py-5 d-none">
        <i class="bi bi-box-seam fs-1 text-muted"></i>
        <h4 class="mt-3">Product not found</h4>
        <p class="text-muted-2">The item you are looking for may have been removed or is out of stock.</p>
        <a href="shop.php" class="btn btn-primary">Back to Shop</a>
      </div>

      <!-- Product detail -->
      <div class="row g-4 g-lg-5 d-none" id="productDetail"></div>

      <!-- Tabs -->
      <div class="mt-5 d-none" id="pdTabs">
        <ul class="nav nav-tabs" role="tablist">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDescription"
              type="button">Description</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSpecs"
              type="button">Specifications</button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabReviews" type="button">
              Reviews <span class="badge bg-secondary ms-1" id="tabReviewCount">0</span>
            </button>
          </li>
        </ul>
        <div class="tab-content pt-4">
          <div class="tab-pane fade show active" id="tabDescription"></div>
          <div class="tab-pane fade" id="tabSpecs"></div>
          <div class="tab-pane fade" id="tabReviews"></div>
        </div>
      </div>

      <!-- Related -->
      <div class="mt-5 d-none" id="pdRelatedWrap">
        <h2 class="section-title">Related Products</h2>
        <p class="section-sub">Customers who viewed this item also liked</p>
        <div class="row g-3 g-lg-4" id="relatedProducts"></div>
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
     |  Product detail page — talks to product_public_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'product_public_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
       <text x="50%" y="50%" font-family="sans-serif" font-size="24"
             fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const loading = $('#pdLoading');
      const notFound = $('#pdNotFound');
      const detailEl = $('#productDetail');
      const tabsWrap = $('#pdTabs');
      const relatedWrap = $('#pdRelatedWrap');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);
      const fmtDate = (s) => {
        if (!s) return '';
        const d = new Date(s.replace(' ', 'T'));
        return isNaN(d) ? s : d.toLocaleDateString(undefined, {
          year: 'numeric', month: 'short', day: 'numeric'
        });
      };

      function getSlugFromUrl() {
        const p = new URLSearchParams(location.search);
        return p.get('slug') || p.get('id') || '';
      }

      async function api(action, params = {}) {
        const q = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`${API}?${q}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      /* ---------- Stars ---------- */
      function stars(rating, size = 'sm') {
        const r = Math.round(rating);
        let html = '';
        for (let i = 1; i <= 5; i++) {
          html += `<i class="bi bi-star${i <= r ? '-fill' : ''} text-warning${size === 'lg' ? ' fs-5' : ''}"></i>`;
        }
        return html;
      }

      /* ---------- Gallery ---------- */
      function buildGallery(p) {
        const imgs = [];
        if (p.image) imgs.push({ path: p.image, alt: p.name });
        (p.images || []).forEach(im => {
          if (!imgs.find(x => x.path === im.path)) {
            imgs.push({ path: im.path, alt: im.alt || p.name });
          }
        });
        if (!imgs.length) imgs.push({ path: FALLBACK, alt: p.name });

        const main = imgs[0].path;
        const thumbs = imgs.length > 1 ? `
      <div class="d-flex gap-2 flex-wrap mt-3" id="pdThumbs">
        ${imgs.map((im, i) => `
          <button type="button" class="pd-thumb ${i === 0 ? 'active' : ''}"
                  data-src="${esc(im.path)}" title="View image">
            <img src="${esc(im.path)}" width="70" height="70"
                 style="object-fit:cover;border-radius:8px"
                 onerror="this.onerror=null;this.src='${FALLBACK}'">
          </button>
        `).join('')}
      </div>` : '';

        return `
      <div class="pd-gallery">
        <div class="ratio ratio-1x1 rounded-3 overflow-hidden border bg-white">
          <img id="pdMainImg" src="${esc(main)}"
               alt="${esc(p.name)}"
               class="w-100 h-100" style="object-fit:cover"
               onerror="this.onerror=null;this.src='${FALLBACK}'">
        </div>
        ${thumbs}
      </div>
    `;
      }

      /* ---------- Variants ---------- */
      function buildVariantUI(p) {
        if (!p.variants || !p.variants.length) return '';

        const colors = [...new Set(p.variants.map(v => v.color).filter(Boolean))];
        const sizes = [...new Set(p.variants.map(v => v.size).filter(Boolean))];

        const colorHtml = colors.length ? `
      <div class="mt-3">
        <label class="form-label mb-1">Color:</label>
        <div class="d-flex flex-wrap gap-2" id="pdColors">
          ${colors.map((c, i) => `
            <button type="button" class="btn btn-sm btn-outline-secondary pd-variant-btn"
                    data-type="color" data-value="${esc(c)}">
              ${esc(c)}
            </button>`).join('')}
        </div>
      </div>` : '';

        const sizeHtml = sizes.length ? `
      <div class="mt-3">
        <label class="form-label mb-1">Size:</label>
        <div class="d-flex flex-wrap gap-2" id="pdSizes">
          ${sizes.map(s => `
            <button type="button" class="btn btn-sm btn-outline-secondary pd-variant-btn"
                    data-type="size" data-value="${esc(s)}">
              ${esc(s)}
            </button>`).join('')}
        </div>
      </div>` : '';

        return colorHtml + sizeHtml;
      }

      /* ---------- Main product block ---------- */
      function renderProduct(p) {
        const cat = p.category_name
          ? `<a href="shop.php?category=${encodeURIComponent(p.category_slug)}"
              class="text-decoration-none">${esc(p.category_name)}</a>`
          : '';

        const priceRow = p.old_price
          ? `<span class="h4 fw-bold text-primary me-2">${money(p.price)}</span>
         <span class="text-muted text-decoration-line-through">${money(p.old_price)}</span>
         <span class="badge bg-danger ms-2">-${p.discount_percent}%</span>`
          : `<span class="h4 fw-bold text-primary">${money(p.price)}</span>`;

        const stockBadge = p.is_out_of_stock
          ? `<span class="badge bg-danger">Out of stock</span>`
          : p.is_low_stock
            ? `<span class="badge bg-warning text-dark">Only ${p.stock} left</span>`
            : `<span class="badge bg-success">In stock</span>`;

        const flags = [
          p.featured ? '<span class="badge bg-primary">Featured</span>' : '',
          p.best_seller ? '<span class="badge bg-warning text-dark">Best seller</span>' : '',
          p.new_arrival ? '<span class="badge bg-info text-dark">New arrival</span>' : ''
        ].filter(Boolean).join(' ');

        detailEl.innerHTML = `
      <div class="col-lg-6">
        ${buildGallery(p)}
      </div>

      <div class="col-lg-6">
        <div class="d-flex flex-wrap gap-2 mb-2">
          ${flags}
        </div>

        <h1 class="h3 mb-2" id="pdName">${esc(p.name)}</h1>

        <div class="d-flex align-items-center gap-2 mb-3">
          ${stars(p.rating, 'lg')}
          <span class="text-muted small">
            ${p.rating.toFixed(1)} · ${p.review_count} review${p.review_count === 1 ? '' : 's'}
          </span>
          ${cat ? `<span class="text-muted small">· in ${cat}</span>` : ''}
        </div>

        <div class="mb-3">${priceRow}</div>

        <div class="d-flex flex-wrap gap-2 mb-3">
          ${stockBadge}
          <span class="badge bg-light text-dark">SKU: ${esc(p.sku)}</span>
        </div>

        ${p.short_description
            ? `<p class="text-muted-2">${esc(p.short_description)}</p>`
            : ''}

        ${buildVariantUI(p)}

        <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
          <div class="input-group" style="max-width:150px">
            <button class="btn btn-outline-secondary" type="button" id="pdQtyDown">−</button>
            <input type="number" class="form-control text-center" id="pdQty"
                   value="1" min="1" max="${p.stock || 1}" ${p.is_out_of_stock ? 'disabled' : ''}>
            <button class="btn btn-outline-secondary" type="button" id="pdQtyUp">+</button>
          </div>

          <button class="btn btn-primary btn-lg" id="pdAddToCart"
                  ${p.is_out_of_stock ? 'disabled' : ''}>
            <i class="bi bi-cart-plus me-1"></i>
            ${p.is_out_of_stock ? 'Out of Stock' : 'Add to Cart'}
          </button>

          <button class="btn btn-outline-primary btn-lg" id="pdBuyNow"
                  ${p.is_out_of_stock ? 'disabled' : ''}>
            Buy Now
          </button>

          <button class="btn btn-outline-secondary btn-lg" id="pdWishlist" data-wish="${p.id}" title="Add to wishlist" aria-label="Add to wishlist">
            <i class="bi bi-heart"></i>
          </button>
        </div>

        <div class="mt-4 small text-muted">
          <div><i class="bi bi-truck me-2"></i>Free shipping on orders over $100</div>
          <div><i class="bi bi-arrow-repeat me-2"></i>30-day returns</div>
          <div><i class="bi bi-shield-check me-2"></i>Secure checkout</div>
        </div>

        <div id="pdToast" class="alert d-none mt-3 mb-0 py-2 small"></div>
      </div>
    `;

        /* Update breadcrumb */
        $('#bcName').textContent = p.name;

        /* Bind gallery thumbnails */
        detailEl.querySelectorAll('.pd-thumb').forEach(btn => {
          btn.addEventListener('click', () => {
            const src = btn.dataset.src;
            const img = $('#pdMainImg');
            if (img) img.src = src;
            detailEl.querySelectorAll('.pd-thumb').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
          });
        });

        /* Bind variant buttons */
        detailEl.querySelectorAll('.pd-variant-btn').forEach(btn => {
          btn.addEventListener('click', () => {
            const type = btn.dataset.type;
            detailEl.querySelectorAll(`.pd-variant-btn[data-type="${type}"]`)
              .forEach(b => b.classList.remove('active', 'btn-secondary'));
            btn.classList.add('active', 'btn-secondary');
            applyVariantSelection(p);
          });
        });

        /* Quantity +/- */
        const qtyInput = $('#pdQty');
        if (qtyInput) {
          $('#pdQtyUp')?.addEventListener('click', () => {
            const max = Number(qtyInput.max) || 1;
            qtyInput.value = Math.min(max, (Number(qtyInput.value) || 1) + 1);
          });
          $('#pdQtyDown')?.addEventListener('click', () => {
            qtyInput.value = Math.max(1, (Number(qtyInput.value) || 1) - 1);
          });
        }

        /* Add to cart */
        $('#pdAddToCart')?.addEventListener('click', () => addToCart(p, false));
        $('#pdBuyNow')?.addEventListener('click', () => addToCart(p, true));

        /* Wishlist: the heart carries data-wish, so js/app.js toggles it through wishlist_api.php
           (guests are sent to the login page) and keeps the filled/empty state in sync. */
        if (window.SV && SV.markWishes) SV.markWishes(detailEl);
      }

      /* ---------- Variant application ---------- */
      function selectedVariant(p) {
        const colorBtn = detailEl.querySelector('.pd-variant-btn[data-type="color"].active');
        const sizeBtn = detailEl.querySelector('.pd-variant-btn[data-type="size"].active');
        const color = colorBtn ? colorBtn.dataset.value : null;
        const size = sizeBtn ? sizeBtn.dataset.value : null;

        if (!color && !size) return null;

        return (p.variants || []).find(v =>
          (color === null || v.color === color) &&
          (size === null || v.size === size)
        ) || null;
      }

      function applyVariantSelection(p) {
        const v = selectedVariant(p);
        if (!v) return;

        /* override displayed price if variant has one */
        if (v.price !== null && v.price !== undefined) {
          const priceEl = detailEl.querySelector('.h4.fw-bold.text-primary, .h4.fw-bold');
          if (priceEl) priceEl.textContent = money(v.price);
        }

        /* update stock badge + qty max */
        const qtyInput = $('#pdQty');
        if (qtyInput) {
          qtyInput.max = v.stock || 1;
          if (Number(qtyInput.value) > v.stock) qtyInput.value = Math.max(1, v.stock);
        }

        /* warn if that combo is out of stock */
        if (v.stock === 0) {
          showToast('That combination is out of stock.', 'warning');
        } else {
          hideToast();
        }
      }

      function showToast(msg, type = 'info') {
        const el = $('#pdToast');
        if (!el) return;
        el.className = `alert alert-${type} mt-3 mb-0 py-2 small`;
        el.textContent = msg;
        el.classList.remove('d-none');
      }
      function hideToast() {
        const el = $('#pdToast');
        if (el) el.classList.add('d-none');
      }

      /* ---------- Add to cart ---------- */
      async function addToCart(p, goToCheckout) {
        const qtyInput = $('#pdQty');
        const qty = Math.max(1, Number(qtyInput?.value || 1));
        const variant = selectedVariant(p);

        const btn = goToCheckout ? $('#pdBuyNow') : $('#pdAddToCart');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding…';

        try {
          const res = await fetch('cart_api.php?action=add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              product_id: p.id,
              variant_id: variant ? variant.id : null,
              quantity: qty
            })
          }).then(r => r.json());

          if (!res.success) {
            throw new Error(res.message || 'Could not add to cart.');
          }

          /* Notify the header / cart page */
          const count = res.cart?.summary?.item_count ?? 0;
          document.dispatchEvent(new CustomEvent('cart:updated', { detail: { count } }));

          if (goToCheckout) {
            window.location.href = 'checkout.php';
            return;
          }

          showToast(res.message || 'Added to cart.', 'success');
          btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Added';
          setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = original;
          }, 1200);
        } catch (e) {
          showToast(e.message, 'danger');
          btn.disabled = false;
          btn.innerHTML = original;
        }
      }

      /* ---------- Tabs ---------- */
      function renderTabs(p, reviews, breakdown) {
        /* Description */
        $('#tabDescription').innerHTML = p.description
          ? `<div class="text-muted-2" style="white-space:pre-line">${esc(p.description)}</div>`
          : '<p class="text-muted">No description available.</p>';

        /* Specifications */
        const specs = [
          ['SKU', p.sku],
          ['Category', p.category_name || '—'],
          ['Brand', p.brand_name || '—'],
          ['Stock', p.stock],
          ['Rating', p.rating.toFixed(1) + ' / 5'],
          ['Reviews', p.review_count],
        ];

        $('#tabSpecs').innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm">
          <tbody>
            ${specs.map(([k, v]) => `
              <tr><th style="width:180px" class="text-muted fw-normal">${esc(k)}</th>
                  <td>${esc(v)}</td></tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;

        /* Reviews */
        const totalReviews = reviews.length;
        $('#tabReviewCount').textContent = totalReviews;

        const breakdownHtml = (() => {
          if (!totalReviews) return '';
          const out = [];
          for (let star = 5; star >= 1; star--) {
            const n = breakdown[star] || 0;
            const pct = totalReviews ? Math.round((n / totalReviews) * 100) : 0;
            out.push(`
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="text-muted small" style="width:50px">${star} ★</span>
            <div class="progress flex-grow-1" style="height:6px">
              <div class="progress-bar bg-warning" style="width:${pct}%"></div>
            </div>
            <span class="text-muted small" style="width:36px;text-align:right">${n}</span>
          </div>
        `);
          }
          return `<div class="mb-4">${out.join('')}</div>`;
        })();

        const reviewsHtml = reviews.length ? reviews.map(r => `
      <div class="border-bottom py-3">
        <div class="d-flex align-items-start gap-3">
          <div class="d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fw-bold"
               style="width:42px;height:42px;flex-shrink:0">
            ${esc(r.initials)}
          </div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between flex-wrap gap-2">
              <strong>${esc(r.user_name)}</strong>
              <small class="text-muted">${fmtDate(r.created_at)}</small>
            </div>
            <div class="mb-1">${stars(r.rating)}</div>
            ${r.title ? `<div class="fw-semibold">${esc(r.title)}</div>` : ''}
            ${r.comment ? `<div class="text-muted-2">${esc(r.comment)}</div>` : ''}
          </div>
        </div>
      </div>
    `).join('') : `
      <div class="text-center py-4 text-muted">
        <i class="bi bi-chat-square-text fs-3"></i>
        <p class="mt-2 mb-0">No reviews yet. Be the first to review this product!</p>
      </div>
    `;

        $('#tabReviews').innerHTML = `
      <div class="row g-4">
        <div class="col-md-4">
          <div class="card p-3 border-0 shadow-sm">
            <div class="text-center">
              <div class="display-6 fw-bold">${p.rating.toFixed(1)}</div>
              <div>${stars(p.rating, 'lg')}</div>
              <div class="text-muted small mt-1">${p.review_count} review${p.review_count === 1 ? '' : 's'}</div>
            </div>
            <hr>
            ${breakdownHtml || '<p class="text-muted small mb-0">No ratings yet.</p>'}
          </div>
        </div>
        <div class="col-md-8">
          ${reviewsHtml}
        </div>
      </div>
    `;

        tabsWrap.classList.remove('d-none');
      }

      /* ---------- Related ---------- */
      function renderRelated(items) {
        if (!items.length) {
          relatedWrap.classList.add('d-none');
          return;
        }

        relatedWrap.classList.remove('d-none');
        const grid = $('#relatedProducts');

        grid.innerHTML = items.map(r => {
          const img = r.image || FALLBACK;
          const priceHtml = r.old_price
            ? `<span class="fw-bold text-primary">${money(r.price)}</span>
           <small class="text-muted text-decoration-line-through ms-1">${money(r.old_price)}</small>`
            : `<span class="fw-bold text-primary">${money(r.price)}</span>`;

          const flag = r.best_seller
            ? '<span class="badge bg-warning text-dark position-absolute" style="top:8px;left:8px">Best seller</span>'
            : r.new_arrival
              ? '<span class="badge bg-info text-dark position-absolute" style="top:8px;left:8px">New</span>'
              : '';

          return `
        <div class="col-6 col-md-4 col-lg-3">
          <a href="product-details.php?slug=${encodeURIComponent(r.slug)}"
             class="text-decoration-none text-reset">
            <div class="card border-0 shadow-sm h-100 product-card position-relative">
              ${flag}
              <div class="ratio ratio-1x1 overflow-hidden rounded-top">
                <img src="${esc(img)}" alt="${esc(r.name)}" loading="lazy"
                     class="w-100 h-100" style="object-fit:cover"
                     onerror="this.onerror=null;this.src='${FALLBACK}'">
              </div>
              <div class="card-body">
                <h6 class="card-title mb-1 small">${esc(r.name)}</h6>
                <div class="d-flex align-items-center gap-1 mb-1">
                  ${stars(r.rating)}
                  <small class="text-muted">(${r.review_count})</small>
                </div>
                <div>${priceHtml}</div>
              </div>
            </div>
          </a>
        </div>
      `;
        }).join('');
      }

      /* ---------- Load ---------- */
      async function load() {
        const key = getSlugFromUrl();
        if (!key) { showNotFound(); return; }

        try {
          const data = await api('full', { slug: key, related_limit: 4 });
          const { product, related, reviews, breakdown } = data;

          loading.classList.add('d-none');
          notFound.classList.add('d-none');
          detailEl.classList.remove('d-none');   // was never un-hidden -> blank product page
          renderProduct(product);
          renderTabs(product, reviews || [], breakdown || {});
          renderRelated(related || []);

          /* also set the document title */
          document.title = product.name + ' – ShopVerse';
        } catch (e) {
          console.error('[product-details]', e);
          showNotFound();
        }
      }

      function showNotFound() {
        loading.classList.add('d-none');
        detailEl.classList.add('d-none');
        tabsWrap.classList.add('d-none');
        relatedWrap.classList.add('d-none');
        notFound.classList.remove('d-none');
      }

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
    .pd-thumb {
      border: 2px solid transparent;
      padding: 0;
      background: none;
      cursor: pointer;
      border-radius: 10px;
      overflow: hidden;
      transition: border-color .15s ease;
    }

    .pd-thumb.active,
    .pd-thumb:hover {
      border-color: var(--bs-primary);
    }

    .product-card {
      transition: transform .25s ease, box-shadow .25s ease;
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .08) !important;
    }

    .pd-variant-btn.active {
      font-weight: 600;
    }
  </style>
</body>

</html>