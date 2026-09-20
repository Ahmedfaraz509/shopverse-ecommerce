<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');   // must run before any HTML output
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports – ShopVerse Admin</title>
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

      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="d-flex align-items-center gap-2">
          <label class="text-muted-2 small mb-0 text-nowrap">Period</label>
          <select class="form-select form-select-sm" id="rangeSelect" style="width:190px">
            <option value="7d">Last 7 days</option>
            <option value="30d">Last 30 days</option>
            <option value="90d">Last 90 days</option>
            <option value="12m" selected>Last 12 months</option>
            <option value="ytd">Year to date</option>
          </select>
          <span class="text-muted small" id="rangeMeta"></span>
        </div>

        <button class="btn btn-primary btn-sm" id="exportBtn">
          <i class="bi bi-download me-1"></i>Export CSV
        </button>
      </div>

      <!-- Stats cards -->
      <div class="row g-3 mb-4" id="reportStats">
        <div class="col-12 text-center py-4 text-muted">Loading stats…</div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-xl-8">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Revenue &amp; Orders</h6>
              <span class="badge text-bg-light border" id="chartRangeBadge">Last 12 months</span>
            </div>
            <div class="card-body2">
              <div class="chart-wrap"><canvas id="reportSales"></canvas></div>
            </div>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Sales by Category</h6>
            </div>
            <div class="card-body2">
              <div class="chart-wrap"><canvas id="reportCategory"></canvas></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-xl-5">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Orders by Status</h6>
            </div>
            <div class="card-body2">
              <div class="chart-wrap"><canvas id="reportStatus"></canvas></div>
            </div>
          </div>
        </div>
        <div class="col-xl-7">
          <div class="admin-card h-100">
            <div class="card-head">
              <h6 class="mb-0">Top Selling Products</h6>
            </div>
            <div class="card-body2 table-responsive">
              <table class="table align-middle mb-0">
                <thead>
                  <tr>
                    <th style="width:52px">#</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-end">Units</th>
                    <th class="text-end">Revenue</th>
                  </tr>
                </thead>
                <tbody id="topSellingBody">
                  <tr>
                    <td colspan="5" class="text-center py-4 text-muted">Loading…</td>
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
     |  Admin Reports page — talks to admin_reports_api.php
     * ========================================================= */
    (function () {
      'use strict';

      const API = 'admin_reports_api.php';
      const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
       <rect width="100%" height="100%" fill="#f1f3f5"/>
     </svg>`
      );

      const $ = (s) => document.querySelector(s);
      const rangeSelect = $('#rangeSelect');
      const rangeMeta = $('#rangeMeta');
      const statsWrap = $('#reportStats');
      const topBody = $('#topSellingBody');
      const badgeEl = $('#chartRangeBadge');

      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
      }[m]));
      const money = (n) => '$' + Number(n || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2, maximumFractionDigits: 2
      });
      const moneyInt = (n) => '$' + Math.round(Number(n || 0)).toLocaleString();
      const num = (n) => Number(n || 0).toLocaleString();

      /* ---------- Chart palette ---------- */
      const PALETTE = [
        '#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
        '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#84cc16'
      ];

      const STATUS_COLORS = {
        'Pending': '#f59e0b',
        'Processing': '#0ea5e9',
        'Shipped': '#6366f1',
        'Delivered': '#10b981',
        'Cancelled': '#ef4444',
      };

      let salesChart = null;
      let categoryChart = null;
      let statusChart = null;

      /* ---------- API ---------- */
      async function api(params = {}) {
        const q = new URLSearchParams(params).toString();
        const res = await fetch(`${API}?${q}`);
        const json = await res.json();
        if (!res.ok || json.success === false) {
          throw new Error(json.message || 'Request failed.');
        }
        return json.data;
      }

      /* ---------- Stats cards ---------- */
      function renderStats(s) {
        const cards = [
          { label: 'Revenue', value: money(s.revenue), color: 'success', icon: 'bi-currency-dollar' },
          { label: 'Paid revenue', value: money(s.paid_revenue), color: 'primary', icon: 'bi-check2-circle' },
          { label: 'Orders', value: num(s.total_orders), color: 'primary', icon: 'bi-receipt' },
          { label: 'Avg. order', value: money(s.aov), color: 'info', icon: 'bi-graph-up' },
          { label: 'Units sold', value: num(s.units_sold), color: 'warning', icon: 'bi-box-seam' },
          { label: 'Unique customers', value: num(s.unique_customers), color: 'secondary', icon: 'bi-people' },
          { label: 'Open orders', value: num(s.open_orders), color: 'warning', icon: 'bi-hourglass-split' },
          { label: 'Cancelled', value: num(s.cancelled_orders), color: 'danger', icon: 'bi-x-circle' },
        ];

        statsWrap.innerHTML = cards.map(c => `
      <div class="col-6 col-md-3">
        <div class="admin-card p-3 h-100 d-flex align-items-center gap-3">
          <div class="d-flex align-items-center justify-content-center rounded-circle
                      bg-${c.color}-subtle text-${c.color}"
               style="width:44px;height:44px;flex-shrink:0">
            <i class="bi ${c.icon} fs-5"></i>
          </div>
          <div class="overflow-hidden">
            <div class="text-muted small">${c.label}</div>
            <div class="fs-5 fw-bold text-${c.color} text-truncate">${c.value}</div>
          </div>
        </div>
      </div>
    `).join('');
      }

      /* ---------- Sales chart (line, dual axis) ---------- */
      function renderSalesChart(ts) {
        const ctx = $('#reportSales');

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
                fill: false,
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
                display: true,
                position: 'top',
                align: 'end',
                labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } }
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => {
                    if (ctx.dataset.label === 'Revenue') {
                      return ' Revenue: ' + money(ctx.parsed.y);
                    }
                    return ' Orders: ' + num(ctx.parsed.y);
                  }
                }
              }
            },
            scales: {
              x: {
                grid: { display: false },
                ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { size: 11 } }
              },
              y: {
                position: 'left',
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,.05)' },
                ticks: {
                  font: { size: 11 },
                  callback: (v) => moneyInt(v)
                }
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

      /* ---------- Sales by Category (doughnut) ---------- */
      function renderCategoryChart(c) {
        const ctx = $('#reportCategory');

        if (categoryChart) categoryChart.destroy();

        if (!c.labels.length) {
          ctx.parentElement.innerHTML =
            '<div class="text-center py-5 text-muted"><i class="bi bi-pie-chart fs-2"></i><p class="mt-2 mb-0">No sales in this period.</p></div>';
          return;
        }

        categoryChart = new Chart(ctx, {
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
                position: 'right',
                labels: {
                  usePointStyle: true,
                  boxWidth: 8,
                  font: { size: 11 },
                  generateLabels: (chart) => {
                    const data = chart.data;
                    const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                    return data.labels.map((label, i) => {
                      const val = data.datasets[0].data[i];
                      const pct = total > 0 ? ((val / total) * 100).toFixed(1) : '0.0';
                      return {
                        text: `${label} (${pct}%)`,
                        fillStyle: data.datasets[0].backgroundColor[i],
                        strokeStyle: data.datasets[0].backgroundColor[i],
                        lineWidth: 0,
                        pointStyle: 'circle',
                        hidden: false,
                        index: i,
                      };
                    });
                  }
                }
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

      /* ---------- Orders by Status (bar) ---------- */
      function renderStatusChart(s) {
        const ctx = $('#reportStatus');

        if (statusChart) statusChart.destroy();

        const colors = s.labels.map(l => STATUS_COLORS[l] || PALETTE[0]);

        statusChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: s.labels,
            datasets: [{
              data: s.values,
              backgroundColor: colors,
              borderRadius: 6,
              barThickness: 32,
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) => ` ${num(ctx.parsed.y)} order${ctx.parsed.y === 1 ? '' : 's'}`
                }
              }
            },
            scales: {
              x: {
                grid: { display: false },
                ticks: { font: { size: 11 } }
              },
              y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,.05)' },
                ticks: { font: { size: 11 }, precision: 0 }
              }
            }
          }
        });
      }

      /* ---------- Top products table ---------- */
      function renderTop(rows) {
        if (!rows.length) {
          topBody.innerHTML = `
        <tr><td colspan="5" class="text-center py-5 text-muted">
          <i class="bi bi-bag-x fs-3 d-block mb-2"></i>
          No sales in this period.
        </td></tr>`;
          return;
        }

        topBody.innerHTML = rows.map((p, i) => `
      <tr>
        <td class="text-muted fw-semibold">${i + 1}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <img src="${esc(p.image) || FALLBACK}"
                 width="36" height="36"
                 style="object-fit:cover;border-radius:6px"
                 onerror="this.onerror=null;this.src='${FALLBACK}'">
            <div class="overflow-hidden">
              <div class="fw-semibold text-truncate" style="max-width:280px">
                ${esc(p.product_name)}
              </div>
              <small class="text-muted">${esc(p.sku)}</small>
            </div>
          </div>
        </td>
        <td><small>${esc(p.category_name)}</small></td>
        <td class="text-end fw-semibold">${num(p.units)}</td>
        <td class="text-end fw-semibold text-success">${money(p.revenue)}</td>
      </tr>
    `).join('');
      }

      /* ---------- Range meta ---------- */
      function renderRangeMeta(range) {
        const fmt = (s) => {
          const d = new Date(s);
          return isNaN(d) ? s : d.toLocaleDateString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric'
          });
        };
        rangeMeta.textContent = `${fmt(range.start)} → ${fmt(range.end)}`;
        badgeEl.textContent = range.label;
      }

      /* ---------- Load ---------- */
      async function load() {
        const range = rangeSelect.value;

        statsWrap.innerHTML =
          '<div class="col-12 text-center py-4 text-muted">Loading stats…</div>';
        topBody.innerHTML =
          '<tr><td colspan="5" class="text-center py-4 text-muted">Loading…</td></tr>';

        try {
          const data = await api({ action: 'report', range });

          renderRangeMeta(data.range);
          renderStats(data.stats);
          renderSalesChart(data.timeseries);
          renderCategoryChart(data.categories);
          renderStatusChart(data.statuses);
          renderTop(data.top);
        } catch (e) {
          statsWrap.innerHTML =
            `<div class="col-12"><div class="alert alert-danger mb-0">${esc(e.message)}</div></div>`;
          topBody.innerHTML =
            `<tr><td colspan="5" class="text-center py-4 text-danger">${esc(e.message)}</td></tr>`;
        }
      }

      /* ---------- Export ---------- */
      $('#exportBtn').addEventListener('click', () => {
        const range = rangeSelect.value;
        window.location.href = `${API}?action=export&range=${encodeURIComponent(range)}`;
      });

      rangeSelect.addEventListener('change', load);

      /* ---------- Init ---------- */
      document.addEventListener('DOMContentLoaded', () => {
        if (window.SV && typeof SV.renderAdminShell === 'function') {
          SV.renderAdminShell('reports', 'Reports', 'Analyse sales, orders and product performance');
        }
        load();
      });
    })();
  </script>
</body>

</html>