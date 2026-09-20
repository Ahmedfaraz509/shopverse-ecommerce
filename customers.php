<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');   // must run before any HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customers – ShopVerse Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">

<aside class="admin-sidebar" id="adminSidebar"></aside>

<div class="admin-main">
  <div class="admin-topbar" id="adminTopbar"></div>
  <div class="admin-content">
    <div class="row g-3 mb-4" id="custStats"></div>
    <div class="admin-card">
      <div class="card-head">
        <h6 class="mb-0">All Customers</h6>
        <input class="form-control form-control-sm" id="custSearch" placeholder="Search customers..." style="width:230px">
      </div>
      <div class="card-body2 table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr><th>Customer</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody id="customersBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="custModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Customer Profile</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="custModalBody"></div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/session.js.php"></script>
<script src="../js/app.js"></script>
<script src="../js/products.js"></script>
<script src="../js/auth.js"></script>
<script src="../js/admin.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    SV.renderAdminShell('customers', 'Customers', 'View customer profiles and purchase history');
    SV.initAdminCustomers();
  });
</script>
</body>
</html>
