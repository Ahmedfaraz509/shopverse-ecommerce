<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Account – ShopVerse</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<header id="sv-header" class="sv-sticky"></header>

<section class="page-head">
  <div class="container">
    <h1 class="h3 mb-2">My Account</h1>
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="index.php">Home</a></li>
      <li class="breadcrumb-item active">Account</li>
    </ol></nav>
  </div>
</section>

<main class="section">
  <div class="container">
    <div class="row g-4">

      <!-- Sidebar -->
      <aside class="col-lg-3">
        <div class="card p-3 text-center mb-3">
          <img id="userAvatar" src="" class="rounded-circle mx-auto mb-2" width="80" height="80" style="object-fit:cover" alt="Avatar">
          <h6 class="mb-0" id="userName">Customer</h6>
          <small class="text-muted-2" id="userEmail">email</small>
        </div>
        <div class="list-group account-nav">
          <button class="list-group-item active" data-bs-toggle="pill" data-bs-target="#paneDashboard"><i class="bi bi-speedometer2 me-2"></i>Dashboard</button>
          <button class="list-group-item" data-bs-toggle="pill" data-bs-target="#paneProfile"><i class="bi bi-person me-2"></i>Profile</button>
          <a class="list-group-item" href="orders.php"><i class="bi bi-bag-check me-2"></i>Orders</a>
          <button class="list-group-item" data-bs-toggle="pill" data-bs-target="#paneWishlist"><i class="bi bi-heart me-2"></i>Wishlist</button>
          <button class="list-group-item" data-bs-toggle="pill" data-bs-target="#paneAddresses"><i class="bi bi-geo-alt me-2"></i>Addresses</button>
          <button class="list-group-item" data-bs-toggle="pill" data-bs-target="#paneSettings"><i class="bi bi-gear me-2"></i>Account Settings</button>
          <button class="list-group-item text-danger" onclick="SV.logout()"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
        </div>
      </aside>

      <!-- Content -->
      <div class="col-lg-9">
        <div class="tab-content">

          <!-- Dashboard -->
          <div class="tab-pane fade show active" id="paneDashboard">
            <div class="row g-3 mb-4" id="accountStats"></div>
            <div class="card p-3 p-md-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">View all</a>
              </div>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead><tr><th>Order</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th></tr></thead>
                  <tbody id="recentOrders"></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Profile -->
          <div class="tab-pane fade" id="paneProfile">
            <div class="card p-3 p-md-4">
              <h5 class="mb-3">Profile Information</h5>
              <form id="profileForm" class="row g-3">
                <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="fullname" required></div>
                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" readonly title="Your email is your login and cannot be changed here"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" required></div>
                <div class="col-12"><label class="form-label">Default address</label><textarea class="form-control" name="address" rows="2" required maxlength="500"></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Save changes</button></div>
              </form>
            </div>
          </div>

          <!-- Wishlist -->
          <div class="tab-pane fade" id="paneWishlist">
            <div class="card p-3 p-md-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Saved Products</h5>
                <a href="wishlist.php" class="btn btn-sm btn-outline-primary">Open wishlist</a>
              </div>
              <div class="row g-3" id="wishPreview"></div>
            </div>
          </div>

          <!-- Addresses -->
          <div class="tab-pane fade" id="paneAddresses">
            <div class="card p-3 p-md-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Address Book</h5>
                <button class="btn btn-sm btn-primary" id="addAddressBtn"><i class="bi bi-plus-lg me-1"></i>Add address</button>
              </div>
              <div class="row g-3" id="addressList"></div>
            </div>
          </div>

          <!-- Settings -->
          <div class="tab-pane fade" id="paneSettings">
            <div class="card p-3 p-md-4">
              <h5 class="mb-3">Change password</h5>
              <form id="settingsForm" class="row g-3" autocomplete="off">
                <div class="col-md-4"><label class="form-label">Current password</label><input type="password" class="form-control" name="current" required autocomplete="current-password"></div>
                <div class="col-md-4"><label class="form-label">New password</label><input type="password" class="form-control" name="new" required minlength="8" maxlength="72" autocomplete="new-password"></div>
                <div class="col-md-4"><label class="form-label">Confirm new password</label><input type="password" class="form-control" name="confirm" required minlength="8" maxlength="72" autocomplete="new-password"></div>
                <div class="col-12"><small class="text-muted-2">8+ characters with upper and lower case letters, a digit and a symbol.</small></div>
                <div class="col-12"><button class="btn btn-primary">Update password</button></div>
              </form>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<!-- Address modal -->
<div class="modal fade" id="addressModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Address details</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="addressForm">
        <div class="modal-body row g-3">
          <input type="hidden" name="address_id" value="">
          <div class="col-12"><label class="form-label">Street address</label><input class="form-control" name="address_line" required maxlength="500"></div>
          <div class="col-md-6"><label class="form-label">City</label><input class="form-control" name="city" required maxlength="100"></div>
          <div class="col-md-6"><label class="form-label">State / Province</label><input class="form-control" name="state" maxlength="100"></div>
          <div class="col-md-6"><label class="form-label">Postal code</label><input class="form-control" name="postal_code" maxlength="20"></div>
          <div class="col-md-6"><label class="form-label">Country</label><input class="form-control" name="country" value="Pakistan" maxlength="100"></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_default" id="addrDefault"><label class="form-check-label" for="addrDefault">Make this my default address</label></div></div>
        </div>
        <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save address</button></div>
      </form>
    </div>
  </div>
</div>

<div id="sv-footer"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/session.js.php"></script>
<script src="js/app.js"></script>
<script src="js/products.js"></script>
<script src="js/auth.js"></script>
<script>document.addEventListener('DOMContentLoaded', SV.initAccount);</script>
</body>
</html>
