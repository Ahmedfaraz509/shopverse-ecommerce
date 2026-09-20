<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');   // must run before any HTML output
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Manage Orders – ShopVerse Admin</title>
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
      <div class="row g-3 mb-3" id="adminOrderStats"></div>

      <div class="admin-card">
        <div class="card-head">
          <h6 class="mb-0">All Orders</h6>
          <div class="d-flex gap-2 flex-wrap">
            <input class="form-control form-control-sm" id="orderSearchAdmin"
              placeholder="Search order, customer, email..." style="width:250px">
            <select class="form-select form-select-sm" id="orderFilterAdmin" style="width:170px">
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="shipped">Shipped</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-select form-select-sm" id="orderSortAdmin" style="width:180px">
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="total">Highest total</option>
              <option value="total_asc">Lowest total</option>
              <option value="status">Status</option>
            </select>
          </div>
        </div>

        <div class="card-body2 table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Payment</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="adminOrdersBody">
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Loading…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3">
          <small class="text-muted" id="adminOrdersPageInfo"></small>
          <nav>
            <ul class="pagination pagination-sm mb-0" id="adminOrdersPagination"></ul>
          </nav>
        </div>
      </div>
    </div>
  </div>

  <!-- Order details modal -->
  <div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Order Details</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="orderModalBody"></div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print invoice
          </button>
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
     |  Admin Orders page — talks to admin_orders_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'admin_orders_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
     </svg>`
      );

      const state = {
        page: 1,
        per_page: 20,
        search: '',
        status: 'all',
        sort: 'newest',
        total: 0,
        pages: 1
      };

      const $ = (s) => document.querySelector(s);
      const tbody = $('#adminOrdersBody');
      const pageInfo = $('#adminOrdersPageInfo');
      const pagination = $('#adminOrdersPagination');
      const modal = new bootstrap.Modal('#orderModal');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toFixed(2);
      const fmtDate = (s) => {
        if (!s) return '—';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
          + ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
      };

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

      /* ---------- Badges ---------- */
      function orderBadge(o) {
        const map = {
          'pending': 'bg-warning-subtle text-warning',
          'processing': 'bg-info-subtle text-info',
          'shipped': 'bg-primary-subtle text-primary',
          'delivered': 'bg-success-subtle text-success',
          'cancelled': 'bg-danger-subtle text-danger'
        };
        return `<span class="badge ${map[o.order_raw] || 'bg-light text-dark'}">${esc(o.order_status)}</span>`;
      }

      function paymentBadge(o) {
        const map = {
          'pending': 'bg-warning-subtle text-warning',
          'paid': 'bg-success-subtle text-success',
          'failed': 'bg-danger-subtle text-danger',
          'refunded': 'bg-secondary-subtle text-secondary',
          'partially_refunded': 'bg-secondary-subtle text-secondary'
        };
        return `<span class="badge ${map[o.payment_raw] || 'bg-light text-dark'}">${esc(o.payment_status)}</span>`;
      }

      /* ---------- Stats ---------- */
      function renderStats(s) {
        const cards = [
          { label: 'Total', value: s.total, color: 'primary' },
          { label: 'Pending', value: s.pending, color: 'warning' },
          { label: 'Processing', value: s.processing, color: 'info' },
          { label: 'Shipped', value: s.shipped, color: 'primary' },
          { label: 'Delivered', value: s.delivered, color: 'success' },
          { label: 'Cancelled', value: s.cancelled, color: 'danger' },
          { label: 'Today', value: s.orders_today, color: 'dark' },
          { label: 'Revenue', value: money(s.revenue), color: 'success' }
        ];

        $('#adminOrderStats').innerHTML = cards.map(c => `
      <div class="col-6 col-md-3 col-lg">
        <div class="admin-card p-3 h-100">
          <div class="text-muted small">${c.label}</div>
          <div class="fs-5 fw-bold text-${c.color}">${c.value}</div>
        </div>
      </div>
    `).join('');
      }

      /* ---------- Rows ---------- */
      function renderRows(items) {
        if (!items.length) {
          tbody.innerHTML = `
        <tr><td colspan="7" class="text-center py-5 text-muted">
          <i class="bi bi-inbox fs-3 d-block mb-2"></i>
          No orders found.
        </td></tr>`;
          return;
        }

        tbody.innerHTML = items.map(o => {
          const cust = o.customer_name || '—';
          const email = o.customer_email ? `<small class="text-muted d-block">${esc(o.customer_email)}</small>` : '';
          const preview = o.first_item_name
            ? esc(o.first_item_name) + (o.item_count > 1 ? ` <small class="text-muted">+${o.item_count - 1}</small>` : '')
            : `<small class="text-muted">${o.item_count} item(s)</small>`;

          return `
        <tr>
          <td>
            <div class="fw-semibold">#${esc(o.order_number)}</div>
            <small class="text-muted">${preview}</small>
          </td>
          <td>${esc(cust)}${email}</td>
          <td><small>${fmtDate(o.created_at)}</small></td>
          <td class="fw-semibold">${money(o.total)}</td>
          <td>${paymentBadge(o)}</td>
          <td>${orderBadge(o)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-light" data-act="view" data-id="${o.id}" title="View">
              <i class="bi bi-eye"></i>
            </button>
            <button class="btn btn-sm btn-light" data-act="manage" data-id="${o.id}" title="Manage">
              <i class="bi bi-gear"></i>
            </button>
          </td>
        </tr>
      `;
        }).join('');
      }

      /* ---------- Pagination ---------- */
      function renderPagination() {
        if (state.pages <= 1) { pagination.innerHTML = ''; return; }

        const p = state.page, total = state.pages;
        let html = `<li class="page-item ${p === 1 ? 'disabled' : ''}">
                  <a class="page-link" href="#" data-page="${p - 1}">‹</a>
                </li>`;

        const from = Math.max(1, p - 2), to = Math.min(total, p + 2);
        if (from > 1) {
          html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
          if (from > 2) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
        }
        for (let i = from; i <= to; i++) {
          html += `<li class="page-item ${i === p ? 'active' : ''}">
                 <a class="page-link" href="#" data-page="${i}">${i}</a>
               </li>`;
        }
        if (to < total) {
          if (to < total - 1) html += `<li class="page-item disabled"><span class="page-link">…</span></li>`;
          html += `<li class="page-item"><a class="page-link" href="#" data-page="${total}">${total}</a></li>`;
        }

        html += `<li class="page-item ${p === total ? 'disabled' : ''}">
               <a class="page-link" href="#" data-page="${p + 1}">›</a>
             </li>`;

        pagination.innerHTML = html;
      }

      /* ---------- Load ---------- */
      async function load() {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">Loading…</td></tr>`;

        try {
          const res = await api('list', {
            page: state.page,
            per_page: state.per_page,
            search: state.search,
            status: state.status,
            sort: state.sort
          });

          const { items, total, page, pages } = res.data;
          state.total = total;
          state.pages = pages || 1;

          renderRows(items);
          renderPagination();

          pageInfo.textContent = total
            ? `Showing page ${page} of ${state.pages} (${total} orders)`
            : 'No orders';
        } catch (e) {
          tbody.innerHTML = `
        <tr><td colspan="7" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      async function loadStats() {
        try {
          const res = await api('stats');
          renderStats(res.data);
        } catch (_) { }
      }

      /* ---------- View order ---------- */
      async function viewOrder(id) {
        $('#orderModalBody').innerHTML =
          `<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>`;
        modal.show();

        try {
          const res = await api('get', { id });
          renderOrderModal(res.data);
        } catch (e) {
          $('#orderModalBody').innerHTML =
            `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`;
        }
      }

      function renderOrderModal(o) {
        const itemsHtml = (o.items || []).map(it => `
      <div class="d-flex align-items-center gap-3 py-2 border-bottom">
        <img src="${esc(it.image) || FALLBACK}" width="52" height="52"
             style="object-fit:cover;border-radius:6px"
             onerror="this.src='${FALLBACK}'">
        <div class="flex-grow-1">
          <div class="fw-semibold small">${esc(it.product_name)}</div>
          <small class="text-muted">SKU: ${esc(it.sku)}</small>
        </div>
        <div class="text-end small">
          <div>${it.quantity} × ${money(it.unit_price)}</div>
          <div class="fw-semibold">${money(it.subtotal)}</div>
        </div>
      </div>
    `).join('');

        const addr = o.address || {};
        const addrLine = [
          [addr.first_name, addr.last_name].filter(Boolean).join(' '),
          addr.address_line, addr.city, addr.state, addr.postal_code, addr.country
        ].filter(Boolean).map(esc).join('<br>');

        /* ----- status transitions ----- */
        const currentOrder = o.order_raw;
        const allowed = o.allowed_transitions || [];
        const orderActions = allowed.length
          ? allowed.map(s => `
          <button class="btn btn-sm btn-outline-primary"
                  data-act="set-status" data-id="${o.id}" data-status="${s}">
            → ${s.charAt(0).toUpperCase() + s.slice(1)}
          </button>`).join(' ')
          : '<span class="text-muted small">No further transitions available.</span>';

        /* ----- payment selector ----- */
        const paymentOptions = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
        const paymentSelect = `
      <select class="form-select form-select-sm" id="adminPayStatus"
              data-id="${o.id}" style="max-width:220px">
        ${paymentOptions.map(p => `
          <option value="${p}" ${p === o.payment_raw ? 'selected' : ''}>
            ${p.replace('_', ' ').replace(/^\w/, c => c.toUpperCase())}
          </option>`).join('')}
      </select>`;

        const pay = o.payment;

        $('#orderModalBody').innerHTML = `
      <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <div>
          <div class="text-muted small">Order</div>
          <div class="fw-bold">#${esc(o.order_number)}</div>
        </div>
        <div>
          <div class="text-muted small">Placed</div>
          <div>${fmtDate(o.created_at)}</div>
        </div>
        <div>
          <div class="text-muted small">Customer</div>
          <div>${esc(o.customer_name || '—')}</div>
          ${o.customer_email ? `<small class="text-muted">${esc(o.customer_email)}</small>` : ''}
        </div>
        <div>${orderBadge(o)} ${paymentBadge(o)}</div>
      </div>

      <div class="alert alert-light border mb-3">
        <div class="row g-2 align-items-center">
          <div class="col-md-6">
            <div class="small text-muted mb-1">Change order status</div>
            <div class="d-flex flex-wrap gap-2">${orderActions}</div>
          </div>
          <div class="col-md-6">
            <div class="small text-muted mb-1">Payment status</div>
            ${paymentSelect}
          </div>
        </div>
      </div>

      <h6 class="mt-4 mb-2">Items</h6>
      ${itemsHtml || '<p class="text-muted">No items.</p>'}

      <div class="row g-4 mt-4">
        <div class="col-md-6">
          <h6>Shipping address</h6>
          <p class="text-muted mb-0">${addrLine || '—'}</p>
        </div>
        <div class="col-md-6">
          <h6>Payment</h6>
          ${pay ? `
            <p class="mb-1">Method: <strong>${esc(pay.payment_method)}</strong></p>
            <p class="mb-1">Status: <strong>${esc(pay.status)}</strong></p>
            ${pay.paid_at ? `<p class="mb-1 small text-muted">Paid at: ${fmtDate(pay.paid_at)}</p>` : ''}
            ${pay.transaction_id ? `<p class="mb-0 small text-muted">Txn: ${esc(pay.transaction_id)}</p>` : ''}
          ` : '<p class="text-muted mb-0">—</p>'}
        </div>
      </div>

      <hr class="my-4">

      <div class="ms-auto" style="max-width:280px">
        <div class="d-flex justify-content-between"><span>Subtotal</span><span>${money(o.subtotal)}</span></div>
        ${o.discount > 0 ? `<div class="d-flex justify-content-between text-success"><span>Discount</span><span>− ${money(o.discount)}</span></div>` : ''}
        <div class="d-flex justify-content-between"><span>Shipping</span><span>${money(o.shipping)}</span></div>
        <div class="d-flex justify-content-between"><span>Tax</span><span>${money(o.tax)}</span></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold fs-5">
          <span>Total</span><span>${money(o.total)}</span>
        </div>
      </div>

      ${o.notes ? `
        <div class="mt-4">
          <h6>Customer notes</h6>
          <p class="text-muted mb-0">${esc(o.notes)}</p>
        </div>` : ''}
    `;
      }

      /* ---------- Row actions ---------- */
      tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const act = btn.dataset.act;

        if (act === 'view' || act === 'manage') return viewOrder(id);
      });

      /* ---------- Status change from modal ---------- */
      $('#orderModalBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-act="set-status"]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const status = btn.dataset.status;

        if (status === 'cancelled' && !confirm('Cancel this order? Product stock will be restored.')) {
          return;
        }

        btn.disabled = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
          const res = await api('status', { id, status }, 'POST');
          renderOrderModal(res.data.order);
          await Promise.all([load(), loadStats()]);
        } catch (err) {
          alert(err.message);
          btn.disabled = false;
          btn.innerHTML = orig;
        }
      });

      /* ---------- Payment status change from modal ---------- */
      $('#orderModalBody').addEventListener('change', async (e) => {
        const sel = e.target.closest('#adminPayStatus');
        if (!sel) return;

        const id = Number(sel.dataset.id);
        const status = sel.value;

        sel.disabled = true;

        try {
          const res = await api('payment', { id, status }, 'POST');
          renderOrderModal(res.data.order);
          await Promise.all([load(), loadStats()]);
        } catch (err) {
          alert(err.message);
          sel.disabled = false;
        }
      });

      /* ---------- Filters ---------- */
      let searchTimer;
      $('#orderSearchAdmin').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          load();
        }, 300);
      });

      $('#orderFilterAdmin').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        load();
      });

      $('#orderSortAdmin').addEventListener('change', (e) => {
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

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderAdminShell === 'function') {
          SV.renderAdminShell('orders', 'Orders', 'Track, update and fulfil customer orders');
        }
        loadStats();
        load();
      });
    })();
  </script>
</body>

</html>