<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');   // must run before any HTML output

require_once __DIR__ . '/../database/connect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Products – ShopVerse Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>

<body class="admin-body">

  <aside class="admin-sidebar" id="adminSidebar"></aside>

  <div class="admin-main">
    <div class="admin-topbar" id="adminTopbar"></div>

    <div class="admin-content">

      <!-- Stats row -->
      <div class="row g-3 mb-3" id="statsRow"></div>

      <div class="admin-card">
        <div class="card-head">
          <h6 class="mb-0">All Products</h6>
          <div class="d-flex gap-2 flex-wrap">
            <input class="form-control form-control-sm" id="prodSearch" placeholder="Search name or SKU..."
              style="width:210px">
            <select class="form-select form-select-sm" id="prodCatFilter" style="width:180px">
              <option value="">All Categories</option>
            </select>
            <select class="form-select form-select-sm" id="prodStatusFilter" style="width:150px">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="draft">Draft</option>
              <option value="low">Low Stock</option>
              <option value="out">Out of Stock</option>
            </select>
            <button class="btn btn-sm btn-primary" id="addProductBtn">
              <i class="bi bi-plus-lg me-1"></i>Add Product
            </button>
          </div>
        </div>

        <div class="card-body2 table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="productsBody">
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">Loading…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3">
          <small class="text-muted" id="pageInfo"></small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="prevPage">
              <i class="bi bi-chevron-left"></i>
            </button>
            <button class="btn btn-outline-secondary" id="nextPage">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add / Edit modal -->
  <div class="modal fade" id="productModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <form id="productForm" class="needs-validation" novalidate>
          <input type="hidden" name="id" id="productId">

          <div class="modal-body row g-3">
            <div class="col-md-8">
              <label class="form-label">Product name *</label>
              <input class="form-control" name="name" required>
              <div class="invalid-feedback">Product name is required.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Category *</label>
              <input class="form-control" name="category" id="prodCategoryInput" list="categoryList" required
                placeholder="Type or pick…">
              <datalist id="categoryList"></datalist>
              <div class="invalid-feedback">Category is required.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Price ($) *</label>
              <input type="number" step="0.01" min="0" class="form-control" name="price" required>
              <div class="invalid-feedback">Enter a valid price.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Compare-at price ($)</label>
              <input type="number" step="0.01" min="0" class="form-control" name="oldPrice">
            </div>

            <div class="col-md-4">
              <label class="form-label">Stock quantity *</label>
              <input type="number" min="0" class="form-control" name="stock" required>
              <div class="invalid-feedback">Enter stock quantity.</div>
            </div>

            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
                <option value="Draft">Draft</option>
              </select>
            </div>

            <div class="col-md-8">
              <label class="form-label">Image URL</label>
              <input class="form-control" name="image" placeholder="https://...">
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="saveProductBtn">Save Product</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View modal -->
  <div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Product Details</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/session.js.php"></script>
  <script src="../js/app.js"></script>
  <script src="../js/auth.js"></script>
  <script src="../js/admin.js"></script>

  <script>
    /* =========================================================
     |  Products admin — talks to products_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'products_api.php';
      const state = {
        page: 1,
        per_page: 10,
        search: '',
        category: '',
        status: '',
        total: 0,
        pages: 1
      };

      const $ = (sel) => document.querySelector(sel);
      const productsBody = $('#productsBody');
      const pageInfo = $('#pageInfo');

      const productModal = new bootstrap.Modal('#productModal');
      const viewModal = new bootstrap.Modal('#viewModal');
      const form = $('#productForm');

      /* ---------- API helper ---------- */
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

      /* ---------- Badge helper ---------- */
      function statusBadge(p) {
        if (p.is_out_of_stock) return '<span class="badge bg-danger-subtle text-danger">Out of stock</span>';
        if (p.is_low_stock) return '<span class="badge bg-warning-subtle text-warning">Low stock</span>';
        const map = {
          'Active': 'bg-success-subtle text-success',
          'Inactive': 'bg-secondary-subtle text-secondary',
          'Draft': 'bg-info-subtle text-info'
        };
        return `<span class="badge ${map[p.status] || 'bg-light text-dark'}">${p.status}</span>`;
      }

      function money(n) {
        return '$' + Number(n || 0).toFixed(2);
      }

      function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
      }

      /* ---------- Render ---------- */
      function renderRows(items) {
        if (!items.length) {
          productsBody.innerHTML =
            `<tr><td colspan="6" class="text-center py-4 text-muted">No products found.</td></tr>`;
          return;
        }

        productsBody.innerHTML = items.map(p => `
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <img src="${esc(p.image) || 'https://via.placeholder.com/40'}"
                   style="width:40px;height:40px;object-fit:cover;border-radius:6px"
                   onerror="this.src='https://via.placeholder.com/40'">
              <div>
                <div class="fw-semibold">${esc(p.name)}</div>
                <small class="text-muted">${esc(p.sku)}</small>
              </div>
            </div>
          </td>
          <td>${esc(p.category ?? '—')}</td>
          <td>${money(p.price)}</td>
          <td>${p.stock}</td>
          <td>${statusBadge(p)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-light" data-act="view"   data-id="${p.id}"><i class="bi bi-eye"></i></button>
            <button class="btn btn-sm btn-light" data-act="edit"   data-id="${p.id}"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-sm btn-light text-danger" data-act="delete" data-id="${p.id}"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      `).join('');
      }

      function renderStats(s) {
        const cards = [
          { label: 'Total', value: s.total, color: 'primary' },
          { label: 'Active', value: s.active, color: 'success' },
          { label: 'Inactive', value: s.inactive, color: 'secondary' },
          { label: 'Low Stock', value: s.low_stock, color: 'warning' },
          { label: 'Out of Stock', value: s.out_of_stock, color: 'danger' },
          { label: 'Inventory $', value: money(s.inventory_value), color: 'info' }
        ];
        $('#statsRow').innerHTML = cards.map(c => `
        <div class="col-6 col-md-2">
          <div class="admin-card p-3 h-100">
            <div class="text-muted small">${c.label}</div>
            <div class="fs-5 fw-bold text-${c.color}">${c.value}</div>
          </div>
        </div>
      `).join('');
      }

      /* ---------- Load ---------- */
      async function load() {
        productsBody.innerHTML =
          `<tr><td colspan="6" class="text-center py-4 text-muted">Loading…</td></tr>`;

        try {
          const res = await api('list', {
            page: state.page,
            per_page: state.per_page,
            search: state.search,
            category: state.category,
            status: state.status
          });

          const { items, total, page, pages } = res.data;
          state.total = total;
          state.pages = pages || 1;

          renderRows(items);

          pageInfo.textContent = total
            ? `Showing page ${page} of ${state.pages} (${total} items)`
            : 'No items';
        } catch (e) {
          productsBody.innerHTML =
            `<tr><td colspan="6" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      async function loadStats() {
        try {
          const res = await api('stats');
          renderStats(res.data);
        } catch (_) { }
      }

      async function loadCategories() {
        try {
          const res = await api('categories');
          const sel = $('#prodCatFilter');
          const dl = $('#categoryList');

          res.data.forEach(c => {
            sel.insertAdjacentHTML('beforeend',
              `<option value="${esc(c.slug)}">${esc(c.name)}</option>`);
            dl.insertAdjacentHTML('beforeend',
              `<option value="${esc(c.name)}">`);
          });
        } catch (_) { }
      }

      /* ---------- Form: open create ---------- */
      function openCreate() {
        form.reset();
        form.classList.remove('was-validated');
        $('#productId').value = '';
        $('#productModalTitle').textContent = 'Add New Product';
        productModal.show();
      }

      /* ---------- Form: open edit ---------- */
      async function openEdit(id) {
        try {
          const res = await api('get', { id });
          const p = res.data;

          form.reset();
          form.classList.remove('was-validated');
          $('#productId').value = p.id;

          form.name.value = p.name ?? '';
          form.category.value = p.category ?? '';
          form.price.value = p.price ?? '';
          form.oldPrice.value = p.oldPrice ?? '';
          form.stock.value = p.stock ?? '';
          form.status.value = p.status ?? 'Active';
          form.image.value = p.image ?? '';
          form.description.value = p.description ?? '';

          $('#productModalTitle').textContent = 'Edit Product';
          productModal.show();
        } catch (e) {
          alert(e.message);
        }
      }

      /* ---------- Form: save ---------- */
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          return;
        }

        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());
        const id = data.id ? Number(data.id) : null;
        delete data.id;

        const btn = $('#saveProductBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';

        try {
          const res = await api(id ? 'update' : 'create', id ? { ...data, id } : data, 'POST');

          productModal.hide();
          await Promise.all([load(), loadStats(), loadCategories()]);
        } catch (err) {
          alert(err.message);
        } finally {
          btn.disabled = false;
          btn.textContent = 'Save Product';
        }
      });

      /* ---------- Row actions ---------- */
      productsBody.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const act = btn.dataset.act;

        if (act === 'edit') return openEdit(id);
        if (act === 'view') return openView(id);
        if (act === 'delete') {
          if (!confirm('Delete this product?')) return;
          try {
            const res = await api('delete', { id }, 'POST');
            alert(res.message);
            await Promise.all([load(), loadStats()]);
          } catch (err) { alert(err.message); }
        }
      });

      /* ---------- View ---------- */
      async function openView(id) {
        try {
          const res = await api('get', { id });
          const p = res.data;

          $('#viewBody').innerHTML = `
          <div class="row g-3">
            <div class="col-md-4">
              <img src="${esc(p.image) || 'https://via.placeholder.com/300'}"
                   class="img-fluid rounded"
                   onerror="this.src='https://via.placeholder.com/300'">
            </div>
            <div class="col-md-8">
              <h5>${esc(p.name)}</h5>
              <p class="text-muted mb-2">SKU: ${esc(p.sku)} · ${esc(p.category ?? '—')}</p>
              <p class="mb-1"><strong>Price:</strong> ${money(p.price)}
                 ${p.oldPrice ? `<s class="text-muted ms-2">${money(p.oldPrice)}</s>` : ''}
              </p>
              <p class="mb-1"><strong>Stock:</strong> ${p.stock}</p>
              <p class="mb-1"><strong>Status:</strong> ${esc(p.status)}</p>
              <p class="mb-3"><strong>Rating:</strong> ${p.rating} (${p.review_count} reviews)</p>
              <p>${esc(p.description ?? '')}</p>
            </div>
          </div>
        `;
          viewModal.show();
        } catch (e) { alert(e.message); }
      }

      /* ---------- Filters / Pagination ---------- */
      let searchTimer;
      $('#prodSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          load();
        }, 300);
      });

      $('#prodCatFilter').addEventListener('change', (e) => {
        state.category = e.target.value;
        state.page = 1;
        load();
      });

      $('#prodStatusFilter').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        load();
      });

      $('#prevPage').addEventListener('click', () => {
        if (state.page > 1) { state.page--; load(); }
      });
      $('#nextPage').addEventListener('click', () => {
        if (state.page < state.pages) { state.page++; load(); }
      });

      $('#addProductBtn').addEventListener('click', openCreate);

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderAdminShell === 'function') {
          SV.renderAdminShell('products', 'Products', 'Create, edit and manage your catalogue');
        }
        loadCategories();
        loadStats();
        load();
      });
    })();
  </script>
</body>

</html>