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
  <title>Manage Categories – ShopVerse Admin</title>
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
      <div class="row g-3 mb-3" id="catStatsRow"></div>

      <div class="admin-card">
        <div class="card-head">
          <h6 class="mb-0">All Categories</h6>
          <div class="d-flex gap-2 flex-wrap">
            <input class="form-control form-control-sm" id="catSearch" placeholder="Search name or slug..."
              style="width:210px">
            <select class="form-select form-select-sm" id="catStatusFilter" style="width:150px">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
            <select class="form-select form-select-sm" id="catParentFilter" style="width:170px">
              <option value="">All Levels</option>
              <option value="root">Root only</option>
            </select>
            <button class="btn btn-sm btn-primary" id="addCatBtn">
              <i class="bi bi-plus-lg me-1"></i>Add Category
            </button>
          </div>
        </div>

        <div class="card-body2 table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Category</th>
                <th>Parent</th>
                <th>Products</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="catBody">
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">Loading…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3">
          <small class="text-muted" id="catPageInfo"></small>
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-secondary" id="catPrevPage"><i class="bi bi-chevron-left"></i></button>
            <button class="btn btn-outline-secondary" id="catNextPage"><i class="bi bi-chevron-right"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add / Edit modal -->
  <div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="catModalTitle">Add Category</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <form id="catForm" class="needs-validation" novalidate>
          <input type="hidden" name="id" id="catId">

          <div class="modal-body row g-3">
            <div class="col-12">
              <label class="form-label">Category name *</label>
              <input class="form-control" name="name" required maxlength="100">
              <div class="invalid-feedback">Category name is required.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Parent category</label>
              <select class="form-select" name="parent_id" id="parentSelect">
                <option value="">— None (root) —</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select class="form-select" name="status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label">Image URL</label>
              <input class="form-control" name="image" placeholder="https://...">
            </div>

            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3" maxlength="5000"></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="saveCatBtn">Save Category</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../js/session.js.php"></script>
  <script src="../js/app.js"></script>
  <script src="../js/auth.js"></script>
  <script src="../js/admin.js"></script>

  <script>
    (function () {
      'use strict';

      const API = 'categories_api.php';
      const state = {
        page: 1,
        per_page: 100,
        search: '',
        status: '',
        parent: '',
        total: 0,
        pages: 1
      };

      const $ = (s) => document.querySelector(s);
      const catBody = $('#catBody');
      const pageInfo = $('#catPageInfo');
      const form = $('#catForm');
      const modal = new bootstrap.Modal('#catModal');

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

      /* ---------- helpers ---------- */
      function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
      }

      function statusBadge(c) {
        const map = {
          'Active': 'bg-success-subtle text-success',
          'Inactive': 'bg-secondary-subtle text-secondary'
        };
        return `<span class="badge ${map[c.status] || 'bg-light text-dark'}">${esc(c.status)}</span>`;
      }

      /* ---------- render ---------- */
      function renderRows(items) {
        if (!items.length) {
          catBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">No categories found.</td></tr>`;
          return;
        }

        catBody.innerHTML = items.map(c => `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            ${c.image
            ? `<img src="${esc(c.image)}" style="width:38px;height:38px;object-fit:cover;border-radius:6px">`
            : `<div class="d-flex align-items-center justify-content-center bg-light"
                      style="width:38px;height:38px;border-radius:6px"><i class="bi bi-tag text-muted"></i></div>`
          }
            <div>
              <div class="fw-semibold">${esc(c.name)}</div>
              <small class="text-muted">/${esc(c.slug)}</small>
            </div>
          </div>
        </td>
        <td>${c.parent_name ? esc(c.parent_name) : '<span class="text-muted">— Root —</span>'}</td>
        <td>
          <span class="badge bg-light text-dark">${c.product_count}</span>
          ${c.child_count ? `<small class="text-muted ms-1">(${c.child_count} sub)</small>` : ''}
        </td>
        <td>${statusBadge(c)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-light" data-act="edit"   data-id="${c.id}"><i class="bi bi-pencil"></i></button>
          <button class="btn btn-sm btn-light text-danger" data-act="delete" data-id="${c.id}"><i class="bi bi-trash"></i></button>
        </td>
      </tr>
    `).join('');
      }

      function renderStats(s) {
        const cards = [
          { label: 'Total', value: s.total, color: 'primary' },
          { label: 'Active', value: s.active, color: 'success' },
          { label: 'Inactive', value: s.inactive, color: 'secondary' },
          { label: 'Root', value: s.root, color: 'info' },
          { label: 'Products', value: s.products, color: 'warning' }
        ];
        $('#catStatsRow').innerHTML = cards.map(c => `
      <div class="col-6 col-md">
        <div class="admin-card p-3 h-100">
          <div class="text-muted small">${c.label}</div>
          <div class="fs-5 fw-bold text-${c.color}">${c.value}</div>
        </div>
      </div>
    `).join('');
      }

      /* ---------- load ---------- */
      async function load() {
        catBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted">Loading…</td></tr>`;

        try {
          const res = await api('list', {
            page: state.page,
            per_page: state.per_page,
            search: state.search,
            status: state.status,
            parent: state.parent
          });

          const { items, total, page, pages } = res.data;
          state.total = total;
          state.pages = pages || 1;

          renderRows(items);
          pageInfo.textContent = total
            ? `Showing page ${page} of ${state.pages} (${total} items)`
            : 'No items';
        } catch (e) {
          catBody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      async function loadStats() {
        try {
          const res = await api('stats');
          renderStats(res.data);
        } catch (_) { }
      }

      async function loadParentOptions(excludeId = null) {
        const sel = $('#parentSelect');
        sel.innerHTML = '<option value="">— None (root) —</option>';

        try {
          const res = await api('parents', excludeId ? { exclude_id: excludeId } : {});
          res.data.forEach(c => {
            sel.insertAdjacentHTML('beforeend',
              `<option value="${c.id}">${esc(c.name)}</option>`);
          });
        } catch (_) { }

        /* Also fill filter dropdown with root categories */
        try {
          const res2 = await api('list', { per_page: 500 });
          const pf = $('#catParentFilter');
          const current = pf.value;
          pf.innerHTML = `<option value="">All Levels</option><option value="root">Root only</option>`;
          res2.data.items.forEach(c => {
            pf.insertAdjacentHTML('beforeend',
              `<option value="${c.id}">Under: ${esc(c.name)}</option>`);
          });
          pf.value = current;
        } catch (_) { }
      }

      /* ---------- create / edit ---------- */
      async function openCreate() {
        form.reset();
        form.classList.remove('was-validated');
        $('#catId').value = '';
        $('#catModalTitle').textContent = 'Add Category';
        await loadParentOptions();
        modal.show();
      }

      async function openEdit(id) {
        try {
          const res = await api('get', { id });
          const c = res.data;

          form.reset();
          form.classList.remove('was-validated');
          $('#catId').value = c.id;

          await loadParentOptions(c.id);

          form.name.value = c.name ?? '';
          form.parent_id.value = c.parent_id ?? '';
          form.status.value = c.status ?? 'Active';
          form.image.value = c.image ?? '';
          form.description.value = c.description ?? '';

          $('#catModalTitle').textContent = 'Edit Category';
          modal.show();
        } catch (e) {
          alert(e.message);
        }
      }

      /* ---------- save ---------- */
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

        if (data.parent_id === '') data.parent_id = null;

        const btn = $('#saveCatBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';

        try {
          const res = await api(id ? 'update' : 'create', id ? { ...data, id } : data, 'POST');
          modal.hide();
          await Promise.all([load(), loadStats(), loadParentOptions()]);
        } catch (err) {
          alert(err.message);
        } finally {
          btn.disabled = false;
          btn.textContent = 'Save Category';
        }
      });

      /* ---------- row actions ---------- */
      catBody.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const act = btn.dataset.act;

        if (act === 'edit') return openEdit(id);

        if (act === 'delete') {
          if (!confirm('Delete this category? Sub-categories will move to root.')) return;
          try {
            const res = await api('delete', { id, force: 1 }, 'POST');
            alert(res.message);
            await Promise.all([load(), loadStats(), loadParentOptions()]);
          } catch (err) {
            alert(err.message);
          }
        }
      });

      /* ---------- filters ---------- */
      let searchTimer;
      $('#catSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          load();
        }, 300);
      });

      $('#catStatusFilter').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        load();
      });

      $('#catParentFilter').addEventListener('change', (e) => {
        state.parent = e.target.value;
        state.page = 1;
        load();
      });

      $('#catPrevPage').addEventListener('click', () => {
        if (state.page > 1) { state.page--; load(); }
      });
      $('#catNextPage').addEventListener('click', () => {
        if (state.page < state.pages) { state.page++; load(); }
      });

      $('#addCatBtn').addEventListener('click', openCreate);

      /* ---------- init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderAdminShell === 'function') {
          SV.renderAdminShell('categories', 'Categories', 'Organise your catalogue into categories');
        }
        loadParentOptions();
        loadStats();
        load();
      });
    })();
  </script>
</body>

</html>