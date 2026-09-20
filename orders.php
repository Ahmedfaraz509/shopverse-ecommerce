<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Orders – ShopVerse</title>
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
      <h1 class="h3 mb-2">Order History</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item"><a href="account.php">Account</a></li>
          <li class="breadcrumb-item active">Orders</li>
        </ol>
      </nav>
    </div>
  </section>

  <main class="section">
    <div class="container">

      <!-- Stats row -->
      <div class="row g-3 mb-4" id="orderStats"></div>

      <div class="card p-3 p-md-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h5 class="mb-0">All Orders</h5>
          <div class="d-flex gap-2 flex-wrap">
            <input class="form-control form-control-sm" id="orderSearch" placeholder="Search order ID..."
              style="width:200px">
            <select class="form-select form-select-sm" id="orderStatusFilter" style="width:170px">
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="shipped">Shipped</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-select form-select-sm" id="orderSort" style="width:170px">
              <option value="newest">Newest first</option>
              <option value="oldest">Oldest first</option>
              <option value="total">Highest total</option>
              <option value="total_asc">Lowest total</option>
            </select>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Products</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody id="ordersBody">
              <tr>
                <td colspan="7" class="text-center py-4 text-muted">Loading…</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
          <small class="text-muted" id="ordersPageInfo"></small>
          <nav>
            <ul class="pagination pagination-sm mb-0" id="ordersPagination"></ul>
          </nav>
        </div>
      </div>
    </div>
  </main>

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

  <div id="sv-footer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>
  <script src="js/auth.js"></script>

  <script>
    /* =========================================================
     |  Customer Orders page — talks to orders_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'orders_api.php';

      const state = {
        page: 1,
        per_page: 10,
        search: '',
        status: 'all',
        sort: 'newest',
        total: 0,
        pages: 1
      };

      const $ = (s) => document.querySelector(s);
      const ordersBody = $('#ordersBody');
      const pageInfo = $('#ordersPageInfo');
      const pagination = $('#ordersPagination');
      const modal = new bootstrap.Modal('#orderModal');

      /* ---------- helpers ---------- */
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));

      const money = (n) => '$' + Number(n || 0).toFixed(2);

      const fmtDate = (s) => {
        if (!s) return '—';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString(undefined, {
          year: 'numeric', month: 'short', day: 'numeric'
        });
      };

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

      /* ---------- rendering ---------- */
      function renderRows(items) {
        if (!items.length) {
          ordersBody.innerHTML =
            `<tr><td colspan="7" class="text-center py-5 text-muted">
           <i class="bi bi-inbox fs-3 d-block mb-2"></i>
           You haven't placed any orders yet.
         </td></tr>`;
          return;
        }

        ordersBody.innerHTML = items.map(o => {
          const preview = o.first_item_name
            ? esc(o.first_item_name) + (o.item_count > 1 ? ` <small class="text-muted">+${o.item_count - 1} more</small>` : '')
            : `<small class="text-muted">${o.item_count} item(s)</small>`;

          return `
        <tr>
          <td class="fw-semibold">#${esc(o.order_number)}</td>
          <td>${fmtDate(o.created_at)}</td>
          <td>${preview}</td>
          <td class="fw-semibold">${money(o.total)}</td>
          <td>${paymentBadge(o)}</td>
          <td>${orderBadge(o)}</td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-secondary" data-act="view" data-id="${o.id}">
              <i class="bi bi-eye me-1"></i>View
            </button>
            ${o.can_cancel
              ? `<button class="btn btn-sm btn-outline-danger" data-act="cancel" data-id="${o.id}">
                   <i class="bi bi-x-lg me-1"></i>Cancel
                 </button>`
              : ''}
          </td>
        </tr>
      `;
        }).join('');
      }

      function renderStats(s) {
        const cards = [
          { label: 'Total orders', value: s.total, color: 'primary' },
          { label: 'Pending', value: s.pending, color: 'warning' },
          { label: 'Shipped', value: s.shipped, color: 'info' },
          { label: 'Delivered', value: s.delivered, color: 'success' },
          { label: 'Total spent', value: money(s.spent), color: 'dark' }
        ];
        $('#orderStats').innerHTML = cards.map(c => `
      <div class="col-6 col-md">
        <div class="card border-0 shadow-sm p-3 h-100">
          <div class="text-muted small">${c.label}</div>
          <div class="fs-5 fw-bold text-${c.color}">${c.value}</div>
        </div>
      </div>
    `).join('');
      }

      function renderPagination() {
        if (state.pages <= 1) { pagination.innerHTML = ''; return; }

        const p = state.page, total = state.pages;
        let html = '';

        html += `<li class="page-item ${p === 1 ? 'disabled' : ''}">
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

      /* ---------- load ---------- */
      async function load() {
        ordersBody.innerHTML =
          `<tr><td colspan="7" class="text-center py-4 text-muted">Loading…</td></tr>`;

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
          ordersBody.innerHTML =
            `<tr><td colspan="7" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      async function loadStats() {
        try {
          const res = await api('stats');
          renderStats(res.data);
        } catch (_) { }
      }

      /* ---------- order view ---------- */
      async function viewOrder(id) {
        $('#orderModalBody').innerHTML =
          `<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>`;
        modal.show();

        try {
          const res = await api('get', { id });
          const o = res.data;

          const itemsHtml = (o.items || []).map(it => `
        <div class="d-flex align-items-center gap-3 py-2 border-bottom">
          <img src="${esc(it.image) || 'https://via.placeholder.com/60'}"
               width="60" height="60" style="object-fit:cover;border-radius:6px"
               onerror="this.src='https://via.placeholder.com/60'">
          <div class="flex-grow-1">
            <div class="fw-semibold">${esc(it.product_name)}</div>
            <small class="text-muted">SKU: ${esc(it.sku)}</small>
          </div>
          <div class="text-end">
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

          const pay = o.payment;

          $('#orderModalBody').innerHTML = `
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
          <div>
            <div class="text-muted small">Order</div>
            <div class="fw-bold">#${esc(o.order_number)}</div>
          </div>
          <div>
            <div class="text-muted small">Placed</div>
            <div>${fmtDate(o.created_at)}</div>
          </div>
          <div>${orderBadge(o)}</div>
          <div>${paymentBadge(o)}</div>
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
              ${pay.transaction_id ? `<p class="mb-0 text-muted small">Txn: ${esc(pay.transaction_id)}</p>` : ''}
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
          <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span>${money(o.total)}</span></div>
        </div>

        ${o.can_cancel ? `
          <div class="alert alert-warning mt-4 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            You can still cancel this order.
            <button class="btn btn-sm btn-outline-danger ms-2"
                    data-act="cancel" data-id="${o.id}">Cancel order</button>
          </div>
        ` : ''}
      `;
        } catch (e) {
          $('#orderModalBody').innerHTML =
            `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`;
        }
      }

      /* ---------- cancel ---------- */
      async function cancelOrder(id) {
        if (!confirm('Are you sure you want to cancel this order?')) return;

        try {
          const res = await api('cancel', { id }, 'POST');
          alert(res.message);
          modal.hide();
          await Promise.all([load(), loadStats()]);
        } catch (e) {
          alert(e.message);
        }
      }

      /* ---------- events ---------- */
      ordersBody.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const act = btn.dataset.act;

        if (act === 'view') return viewOrder(id);
        if (act === 'cancel') return cancelOrder(id);
      });

      $('#orderModalBody').addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-act="cancel"]');
        if (btn) cancelOrder(Number(btn.dataset.id));
      });

      let searchTimer;
      $('#orderSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.search = e.target.value.trim();
          state.page = 1;
          load();
        }, 300);
      });

      $('#orderStatusFilter').addEventListener('change', (e) => {
        state.status = e.target.value;
        state.page = 1;
        load();
      });

      $('#orderSort').addEventListener('change', (e) => {
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
      document.addEventListener('DOMContentLoaded', () => {
        loadStats();
        load();
      });
    })();
  </script>
</body>

</html>