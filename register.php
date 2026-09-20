<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/register_process.php';

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = (new RegisterProcess())->registerUser($_POST);
  if ($result['ok']) {
    // Prevent resubmission; message is shown once on the login page
    flash_set('success', $result['message']);
    header('Location: login.php?registered=1');
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Account – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>

  <section class="auth-wrap">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
          <div class="auth-card">
            <div class="row g-0">
              <div class="col-md-5 auth-side d-none d-md-flex flex-column justify-content-between">
                <a href="index.php" class="sv-logo text-white d-inline-flex align-items-center gap-2">
                  <i class="bi bi-bag-check-fill"></i>ShopVerse
                </a>
                <div>
                  <h2 class="mb-3">Join ShopVerse today</h2>
                  <p class="opacity-75">Create a free account and get a 10% welcome coupon on your first order.</p>
                  <ul class="list-unstyled small opacity-75">
                    <li class="mb-2"><i class="bi bi-gift me-2"></i>10% welcome discount</li>
                    <li class="mb-2"><i class="bi bi-truck me-2"></i>Free shipping over $99</li>
                    <li><i class="bi bi-stars me-2"></i>Early access to sales</li>
                  </ul>
                </div>
                <small class="opacity-75">Already registered?
                  <a href="login.php" class="text-white text-decoration-underline">Sign in</a>
                </small>
              </div>

              <div class="col-md-7">
                <div class="p-4 p-lg-5">
                  <h3 class="mb-1">Create your account</h3>
                  <p class="text-muted-2 mb-4">It only takes a minute.</p>

                  <?php if (!empty($result) && !$result['ok']): ?>
                    <div class="alert alert-danger"><?= e($result['message']) ?></div>
                  <?php endif; ?>

                  <form id="registerForm" method="POST" action="register.php" class="row g-3 needs-validation"
                    novalidate>

                    <!-- CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="col-12">
                      <label class="form-label">Full Name *</label>
                      <input class="form-control" name="fullname" required minlength="3" maxlength="100"
                        placeholder="John Doe" value="<?= e($_POST['fullname'] ?? '') ?>">
                      <div class="invalid-feedback">Please enter your full name.</div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Email *</label>
                      <input type="email" class="form-control" name="email" required maxlength="255"
                        placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                      <div class="invalid-feedback">Enter a valid email address.</div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Phone *</label>
                      <input type="tel" class="form-control" name="phone" required pattern="[0-9+\-\s\(\)]{7,20}"
                        placeholder="+92 300 1234567" value="<?= e($_POST['phone'] ?? '') ?>">
                      <div class="invalid-feedback">Enter a valid phone number.</div>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Password *</label>
                      <input type="password" class="form-control" name="password" required minlength="8" maxlength="72"
                        placeholder="••••••••">
                      <div class="invalid-feedback">Minimum 8 characters with upper, lower, digit & symbol.</div>
                      <div class="strength-bar mt-2"><span></span></div>
                      <small id="strengthLabel" class="fw-semibold"></small>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label">Confirm Password *</label>
                      <input type="password" class="form-control" name="confirm" required minlength="8" maxlength="72"
                        placeholder="••••••••">
                      <div class="invalid-feedback">Passwords must match.</div>
                    </div>

                    <div class="col-12">
                      <label class="form-label">Address *</label>
                      <textarea class="form-control" name="address" rows="2" required maxlength="500"
                        placeholder="Street, city, state, postal code"><?= e($_POST['address'] ?? '') ?></textarea>
                      <div class="invalid-feedback">Please enter your address.</div>
                    </div>

                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="agree" name="agree" required>
                        <label class="form-check-label" for="agree">
                          I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> *
                        </label>
                        <div class="invalid-feedback">You must accept the terms.</div>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="news" name="news" checked>
                        <label class="form-check-label" for="news">Send me offers and product updates</label>
                      </div>
                    </div>

                    <div class="col-12">
                      <button class="btn btn-primary btn-lg w-100" id="registerBtn" type="submit">
                        Create Account
                      </button>
                    </div>
                  </form>

                  <p class="text-center mt-4 mb-0">Already have an account? <a href="login.php">Login here</a></p>
                  <p class="text-center mb-0 mt-2">
                    <a href="index.php" class="text-muted-2"><i class="bi bi-arrow-left me-1"></i>Back to store</a>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <div id="sv-footer" class="d-none"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/session.js.php"></script>
  <script src="js/app.js"></script>
</body>

</html>