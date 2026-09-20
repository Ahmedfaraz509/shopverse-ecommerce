<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Categories – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <header id="sv-header" class="sv-sticky" data-page="categories"></header>

  <section class="page-head">
    <div class="container">
      <h1 class="h3 mb-2">Shop by Category</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Categories</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section">
    <div class="container">

      <!-- Toolbar -->
      <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div class="text-muted small" id="catCount">Loading categories…</div>
        <div class="d-flex gap-2">
          <input type="search" class="form-control form-control-sm" id="catSearch" placeholder="Search categories..."
            style="width:220px">
          <select class="form-select form-select-sm" id="catSort" style="width:180px">
            <option value="name">Sort: A → Z</option>
            <option value="name_desc">Sort: Z → A</option>
            <option value="newest">Sort: Newest</option>
            <option value="products">Sort: Most products</option>
          </select>
        </div>
      </div>

      <!-- Grid -->
      <div class="row g-4" id="categoryGrid"></div>

      <!-- Empty state -->
      <div id="catEmpty" class="text-center py-5 d-none">
        <i class="bi bi-tag fs-1 text-muted"></i>
        <p class="text-muted mt-3 mb-0">No categories found.</p>
      </div>

      <!-- Pagination -->
      <nav class="mt-5 d-flex justify-content-center" id="catPagination"></nav>

      <div class="row mt-5 g-4 align-items-center bg-soft rounded-4 p-4 p-lg-5">
        <div class="col-lg-8">
          <h3 class="mb-2">Can't decide where to start?</h3>
          <p class="text-muted-2 mb-0">Browse the full catalogue and use our filters to narrow results by price, rating
            and category.</p>
        </div>
        <div class="col-lg-4 text-lg-end">
          <a href="shop.php" class="btn btn-primary btn-lg">Browse all products</a>
        </div>
      </div>
    </div>
  </main>

  <div id="sv-footer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>

  <script>
    /* =========================================================
     |  Public categories page — talks to categories_public_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'categories_public_api.php';

      const state = {
        page: 1,
        per_page: 12,
        search: '',
        sort: 'name',
        total: 0,
        pages: 1
      };

      const grid = document.getElementById('categoryGrid');
      const empty = document.getElementById('catEmpty');
      const pagination = document.getElementById('catPagination');
      const countEl = document.getElementById('catCount');

      /* ---------- helpers ---------- */
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));

      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="400" height="300">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
       <text x="50%" y="50%" font-family="sans-serif" font-size="20"
             fill="#adb5bd" text-anchor="middle" dominant-baseline="middle">No image</text>
     </svg>`
      );

      async function api(action, params = {}) {
        const q = new URLSearchParams({ action, ...params }).toString();
        const res = await fetch(`${API}?${q}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      /* ---------- render ---------- */
      function categoryCard(c) {
        const img = c.image ? esc(c.image) : FALLBACK;
        const count = c.product_count || 0;
        const subs = c.child_count || 0;

        return `
      <div class="col-6 col-md-4 col-lg-3">
        <a href="shop.php?category=${encodeURIComponent(c.slug)}"
           class="text-decoration-none text-reset">
          <div class="card border-0 shadow-sm h-100 category-card">
            <div class="ratio ratio-4x3 overflow-hidden rounded-top">
              <img src="${img}"
                   alt="${esc(c.name)}"
                   loading="lazy"
                   onerror="this.onerror=null;this.src='${FALLBACK}'"
                   class="object-fit-cover w-100 h-100"
                   style="transition:transform .4s ease">
            </div>
            <div class="card-body">
              <h6 class="card-title mb-1">${esc(c.name)}</h6>
              ${c.description
            ? `<p class="text-muted small mb-2 line-clamp-2">${esc(c.description)}</p>`
            : ''}
              <div class="d-flex justify-content-between align-items-center small text-muted">
                <span><i class="bi bi-box-seam me-1"></i>${count} item${count === 1 ? '' : 's'}</span>
                ${subs ? `<span><i class="bi bi-diagram-3 me-1"></i>${subs} sub</span>` : ''}
              </div>
            </div>
          </div>
        </a>
      </div>
    `;
      }

      function renderGrid(items) {
        if (!items.length) {
          grid.innerHTML = '';
          empty.classList.remove('d-none');
          countEl.textContent = 'No categories found';
          return;
        }

        empty.classList.add('d-none');
        grid.innerHTML = items.map(categoryCard).join('');
        countEl.textContent =
          `${state.total} categor${state.total === 1 ? 'y' : 'ies'} · page ${state.page} of ${state.pages}`;

        /* subtle hover zoom */
        grid.querySelectorAll('.category-card img').forEach(img => {
          img.addEventListener('mouseenter', () => img.style.transform = 'scale(1.06)');
          img.addEventListener('mouseleave', () => img.style.transform = 'scale(1)');
        });
      }

      function renderPagination() {
        if (state.pages <= 1) {
          pagination.innerHTML = '';
          return;
        }

        const p = state.page;
        const total = state.pages;
        const range = [];
        const from = Math.max(1, p - 2);
        const to = Math.min(total, p + 2);

        if (from > 1) range.push(1);
        if (from > 2) range.push('…');
        for (let i = from; i <= to; i++) range.push(i);
        if (to < total - 1) range.push('…');
        if (to < total) range.push(total);

        pagination.innerHTML = `
      <ul class="pagination">
        <li class="page-item ${p === 1 ? 'disabled' : ''}">
          <a class="page-link" href="#" data-page="${p - 1}">Previous</a>
        </li>
        ${range.map(n =>
          n === '…'
            ? `<li class="page-item disabled"><span class="page-link">…</span></li>`
            : `<li class="page-item ${n === p ? 'active' : ''}">
                 <a class="page-link" href="#" data-page="${n}">${n}</a>
               </li>`
        ).join('')}
        <li class="page-item ${p === total ? 'disabled' : ''}">
          <a class="page-link" href="#" data-page="${p + 1}">Next</a>
        </li>
      </ul>
    `;
      }

      /* ---------- load ---------- */
      async function load() {
        grid.innerHTML = `
      <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted mt-3 mb-0">Loading categories…</p>
      </div>`;
        empty.classList.add('d-none');
        pagination.innerHTML = '';

        try {
          const data = await api('list', {
            page: state.page,
            per_page: state.per_page,
            search: state.search,
            sort: state.sort,
            root_only: 1
          });

          state.total = data.total;
          state.pages = data.pages || 1;

          renderGrid(data.items);
          renderPagination();
        } catch (err) {
          grid.innerHTML = `
        <div class="col-12 text-center py-5 text-danger">
          <i class="bi bi-exclamation-triangle fs-1"></i>
          <p class="mt-3 mb-0">${esc(err.message)}</p>
        </div>`;
          countEl.textContent = 'Failed to load';
        }
      }

      /* ---------- events ---------- */
      let searchTimer;
      document.getElementById('catSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          load();
        }, 300);
      });

      document.getElementById('catSort').addEventListener('change', (e) => {
        state.sort = e.target.value;
        state.page = 1;
        load();
      });

      pagination.addEventListener('click', (e) => {
        const a = e.target.closest('a[data-page]');
        if (!a) return;
        e.preventDefault();
        const n = parseInt(a.dataset.page, 10);
        if (!n || n === state.page || n < 1 || n > state.pages) return;
        state.page = n;
        load();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      /* ---------- init ---------- */
      function init() {
        if (window.SV && typeof SV.renderShell === 'function') {
          SV.renderShell(); // your existing header/footer renderer
        }
        load();
      }

      document.addEventListener('DOMContentLoaded', init);
    })();
  </script>

  <style>
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .category-card {
      transition: transform .25s ease, box-shadow .25s ease;
    }

    .category-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .08) !important;
    }
  </style>
</body>

</html>