<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');   // must run before any HTML output
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard – ShopVerse</title>
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

      <!-- Stats cards -->
      <div class="row g-3 mb-4" id="adminStats">
        <div class="col-12 text-center py-4 text-muted">Loading overview…</div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-8">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Sales Overview</h6>
              <span class="badge text-bg-light border">Last 12 months</span>
            </div>
            <div class="card-body2">
              <div class="chart-wrap"><canvas id="salesChart"></canvas></div>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Sales by Category</h6>
            </div>
            <div class="card-body2">
              <div class="chart-wrap"><canvas id="categoryChart"></canvas></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-7">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Recent Orders</h6>
              <a href="orders.php" class="btn btn-sm btn-outline-primary">View all</a>
            </div>
            <div class="card-body2 table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="recentOrdersBody">
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">Loading…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="admin-card mb-3">
            <div class="card-head">
              <h6 class="mb-0">Top Products</h6>
              <a href="products.php" class="btn btn-sm btn-outline-primary">Manage</a>
            </div>
            <div class="card-body2" id="topProducts">
              <div class="text-center py-4 text-muted">Loading…</div>
            </div>
          </div>

          <div class="admin-card">
            <div class="card-head">
              <h6 class="mb-0">Low Stock Alert</h6>
              <span class="badge text-bg-danger" id="lowStockBadge">—</span>
            </div>
            <div class="card-body2 table-responsive">
              <table class="table table-sm align-middle mb-0">
                <tbody id="lowStock">
                  <tr>
                    <td class="text-center py-4 text-muted">Loading…</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="../js/session.js.php"></script>
  <script src="../js/app.js"></script>
  <script src="../js/auth.js"></script>
  <script src="../js/admin.js"></script>

  <script>
    /* =========================================================
     |  Admin Dashboard — talks to admin_dashboard_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'admin_dashboard_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const statsWrap = $('#adminStats');
      const recentBody = $('#recentOrdersBody');
      const topWrap = $('#topProducts');
      const lowWrap = $('#lowStock');
      const lowBadge = $('#lowStockBadge');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2, maximumFractionDigits: 2
      });
      const moneyInt = (n) => '$' + Math.round(Number(n || 0)).toLocaleString();
      const num = (n) => Number(n || 0).toLocaleString();

      const fmtDate = (s) => {
        if (!s) return '—';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d)) return s;
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
          + ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
      };

      const PALETTE = [
        '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
        '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#84cc16'
      ];

      const STATUS_COLORS = {
        'pending': 'bg-warning-subtle text-warning',
        'processing': 'bg-info-subtle text-info',
        'shipped': 'bg-primary-subtle text-primary',
        'delivered': 'bg-success-subtle text-success',
        'cancelled': 'bg-danger-subtle text-danger'
      };

      let salesChart = null;
      let catChart = null;

      /* ---------- API ---------- */
      async function api(action = 'init') {
        const res = await fetch(`${API}?action=${action}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      /* ---------- Stats cards ---------- */
      function renderStats(s) {
        const cards = [
          { label: 'Revenue (all-time)', value: money(s.revenue_total), color: 'success', icon: 'bi-currency-dollar', sub: `Today: ${money(s.revenue_today)}` },
          { label: 'Orders', value: num(s.orders_total), color: 'primary', icon: 'bi-receipt', sub: `${num(s.orders_pending)} pending` },
          { label: 'Customers', value: num(s.customers_total), color: 'info', icon: 'bi-people', sub: `+${num(s.customers_today)} today` },
          { label: 'Products', value: num(s.products_total), color: 'warning', icon: 'bi-box-seam', sub: `${num(s.products_active)} active` },
          { label: 'Units Sold', value: num(s.units_sold), color: 'dark', icon: 'bi-graph-up-arrow', sub: 'All-time' },
          { label: 'Inventory Value', value: money(s.inventory_value), color: 'secondary', icon: 'bi-safe', sub: 'Current stock × price' },
          { label: 'Low Stock', value: num(s.products_low), color: 'warning', icon: 'bi-exclamation-triangle', sub: 'Below threshold' },
          { label: 'Out of Stock', value: num(s.products_out), color: 'danger', icon: 'bi-x-octagon', sub: 'Needs restock' },
        ];

        statsWrap.innerHTML = cards.map(c => `
      <div class="col-6 col-md-3">
        <div class="admin-card p-3 h-100">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="text-muted small">${c.label}</div>
            <div class="d-flex align-items-center justify-content-center rounded-circle
                        bg-${c.color}-subtle text-${c.color}"
                 style="width:34px;height:34px">
              <i class="bi ${c.icon}"></i>
            </div>
          </div>
          <div class="fs-4 fw-bold text-${c.color} mb-1">${c.value}</div>
          <div class="text-muted small">${c.sub}</div>
        </div>
      </div>
    `).join('');
      }

      /* ---------- Sales chart (line, dual axis) ---------- */
      function renderSalesChart(ts) {
        const ctx = $('#salesChart');
        if (salesChart) salesChart.destroy();

        salesChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: ts.labels,
            datasets: [
              {
                label: 'Revenue',
                data: ts.revenue,
                borderColor: PALETTE[0],
                backgroundColor: 'rgba(79, 70, 229, 0.08)',
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 5,
                yAxisID: 'y',
              },
              {
                label: 'Orders',
                data: ts.orders,
                borderColor: PALETTE[2],
                backgroundColor: 'rgba(16, 185, 129, 0.06)',
                borderWidth: 2,
                borderDash: [4, 4],
                tension: 0.35,
                pointRadius: 0,
                pointHoverRadius: 5,
                yAxisID: 'y1',
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: {
                position: 'top', align: 'end',
                labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => ctx.dataset.label === 'Revenue'
                    ? ' Revenue: ' + money(ctx.parsed.y)
                    : ' Orders: ' + num(ctx.parsed.y)
                }
              }
            },
            scales: {
              x: { grid: { display: false }, ticks: { font: { size: 11 } } },
              y: {
                position: 'left',
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,.05)' },
                ticks: { font: { size: 11 }, callback: v => moneyInt(v) }
              },
              y1: {
                position: 'right',
                beginAtZero: true,
                grid: { display: false },
                ticks: { font: { size: 11 }, precision: 0 }
              }
            }
          }
        });
      }

      /* ---------- Category doughnut ---------- */
      function renderCategoryChart(c) {
        const ctx = $('#categoryChart');
        if (catChart) catChart.destroy();

        if (!c.labels.length) {
          ctx.parentElement.innerHTML = `
        <div class="text-center py-5 text-muted">
          <i class="bi bi-pie-chart fs-2"></i>
          <p class="mt-2 mb-0">No sales yet.</p>
        </div>`;
          return;
        }

        catChart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: c.labels,
            datasets: [{
              data: c.revenue,
              backgroundColor: c.labels.map((_, i) => PALETTE[i % PALETTE.length]),
              borderWidth: 2,
              borderColor: '#fff',
              hoverOffset: 6,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => {
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : '0.0';
                    return ` ${money(ctx.parsed)} (${pct}%)`;
                  }
                }
              }
            }
          }
        });
      }

      /* ---------- Recent orders ---------- */
      function renderRecent(rows) {
        if (!rows.length) {
          recentBody.innerHTML = `
        <tr><td colspan="5" class="text-center py-4 text-muted">No orders yet.</td></tr>`;
          return;
        }

        recentBody.innerHTML = rows.map(o => {
          const badge = STATUS_COLORS[o.order_raw] || 'bg-light text-dark';
          return `
        <tr>
          <td class="fw-semibold">
            <a href="orders.php?search=${encodeURIComponent(o.order_number)}"
               class="text-decoration-none">#${esc(o.order_number)}</a>
          </td>
          <td>
            <div>${esc(o.customer_name)}</div>
            ${o.customer_email ? `<small class="text-muted">${esc(o.customer_email)}</small>` : ''}
          </td>
          <td><small class="text-muted">${fmtDate(o.created_at)}</small></td>
          <td class="text-end fw-semibold">${money(o.total)}</td>
          <td><span class="badge ${badge}">${esc(o.order_status)}</span></td>
        </tr>
      `;
        }).join('');
      }

      /* ---------- Top products ---------- */
      function renderTop(rows) {
        if (!rows.length) {
          topWrap.innerHTML = `
        <div class="text-center py-4 text-muted">
          <i class="bi bi-bag fs-2"></i>
          <p class="mt-2 mb-0">No sales yet.</p>
        </div>`;
          return;
        }

        topWrap.innerHTML = rows.map((p, i) => `
      <div class="d-flex align-items-center gap-3 py-2 ${i < rows.length - 1 ? 'border-bottom' : ''}">
        <span class="badge bg-light text-dark" style="min-width:22px">${i + 1}</span>
        <img src="${esc(p.image) || FALLBACK}"
             width="40" height="40"
             style="object-fit:cover;border-radius:6px"
             onerror="this.onerror=null;this.src='${FALLBACK}'">
        <div class="flex-grow-1 overflow-hidden">
          <div class="fw-semibold text-truncate">${esc(p.product_name)}</div>
          <small class="text-muted">${num(p.units)} sold</small>
        </div>
        <div class="fw-bold text-success text-end">${money(p.revenue)}</div>
      </div>
    `).join('');
      }

      /* ---------- Low stock ---------- */
      function renderLowStock(rows) {
        if (!rows.length) {
          lowBadge.className = 'badge text-bg-success';
          lowBadge.textContent = 'All good';
          lowWrap.innerHTML = `
        <tr><td class="text-center py-4 text-muted">
          <i class="bi bi-check-circle fs-3 d-block mb-2 text-success"></i>
          Everything is well stocked.
        </td></tr>`;
          return;
        }

        lowBadge.className = 'badge text-bg-danger';
        lowBadge.textContent = rows.length + ' item' + (rows.length === 1 ? '' : 's');

        lowWrap.innerHTML = rows.map(p => `
      <tr>
        <td>
          <div class="d-flex align-items-center gap-2">
            <img src="${esc(p.image) || FALLBACK}"
                 width="32" height="32"
                 style="object-fit:cover;border-radius:5px"
                 onerror="this.onerror=null;this.src='${FALLBACK}'">
            <div class="overflow-hidden">
              <div class="fw-semibold text-truncate" style="max-width:200px">${esc(p.name)}</div>
              <small class="text-muted">${esc(p.sku)}</small>
            </div>
          </div>
        </td>
        <td class="text-end">
          ${p.is_out
            ? '<span class="badge bg-danger">Out</span>'
            : `<span class="badge bg-warning text-dark">${p.stock} left</span>`}
        </td>
        <td class="text-end" style="width:40px">
          <a href="products.php?id=${p.id}" class="btn btn-sm btn-light" title="Edit">
            <i class="bi bi-pencil"></i>
          </a>
        </td>
      </tr>
    `).join('');
      }

      /* ---------- Load ---------- */
      async function load() {
        try {
          const data = await api('init');

          renderStats(data.stats);
          renderSalesChart(data.timeseries);
          renderCategoryChart(data.categories);
          renderRecent(data.recent);
          renderTop(data.top);
          renderLowStock(data.low_stock);
        } catch (e) {
          statsWrap.innerHTML = `
        <div class="col-12">
          <div class="alert alert-danger mb-0">${esc(e.message)}</div>
        </div>`;
          recentBody.innerHTML = `
        <tr><td colspan="5" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderAdminShell === 'function') {
          SV.renderAdminShell('dashboard', 'Dashboard', 'Welcome back, here is your store overview');
        }
        load();
      });
    })();
  </script>
</body>

</html>