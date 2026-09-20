<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/login_process.php';

// Already signed in? Skip the form.
if (is_logged_in()) {
  header('Location: ' . (current_role() === 'admin' ? 'admin/index.php' : 'index.php'));
  exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $result = (new Login())->attempt($_POST);
  if ($result['ok']) {
    header('Location: ' . $result['redirect']);
    exit;
  }
  $error = $result['message'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login – ShopVerse</title>
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
        <div class="col-lg-9 col-xl-8">
          <div class="auth-card">
            <div class="row g-0">
              <div class="col-md-5 auth-side d-none d-md-flex flex-column justify-content-between">
                <a href="index.php" class="sv-logo text-white d-inline-flex align-items-center gap-2">
                  <i class="bi bi-bag-check-fill"></i>ShopVerse
                </a>
                <div>
                  <h2 class="mb-3">Welcome back</h2>
                  <p class="opacity-75">Sign in to track orders, manage your wishlist and check out faster.</p>
                  <ul class="list-unstyled small opacity-75">
                    <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Order tracking in real time</li>
                    <li class="mb-2"><i class="bi bi-check-circle me-2"></i>Saved addresses &amp; payment</li>
                    <li><i class="bi bi-check-circle me-2"></i>Exclusive member discounts</li>
                  </ul>
                </div>
                <small class="opacity-75">&copy; <span id="yr"></span> ShopVerse</small>
              </div>

              <div class="col-md-7">
                <div class="p-4 p-lg-5">
                  <h3 class="mb-1">Login to your account</h3>
                  <p class="text-muted-2 mb-4">Don't have one? <a href="register.php">Create an account</a></p>

                  <?php $flashSuccess = flash_get('success'); ?>
                  <?php if ($flashSuccess): ?>
                    <div class="alert alert-success">
                      <?= e($flashSuccess) ?>
                    </div>
                  <?php endif; ?>
                  <?php if ($error): ?>
                    <div class="alert alert-danger">
                      <?= e($error) ?>
                    </div>
                  <?php endif; ?>

                  <form id="loginForm" method="POST" action="login.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <div class="mb-3">
                      <label class="form-label">Email address</label>
                      <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" name="email" required maxlength="255"
                          placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Password</label>
                      <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" name="password" required minlength="8"
                          maxlength="72" placeholder="••••••••" id="pwField">
                        <button class="btn btn-outline-secondary" type="button" id="togglePw">
                          <i class="bi bi-eye"></i>
                        </button>
                        <div class="invalid-feedback">Password must be at least 8 characters.</div>
                      </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                        <label class="form-check-label" for="remember">Remember me</label>
                      </div>
                      <a href="forgot_password.php">Forgot password?</a>
                    </div>

                    <button class="btn btn-primary w-100 btn-lg" id="loginBtn" type="submit">Login</button>
                  </form>

                  <div class="text-center my-3 text-muted-2 small">or continue with</div>
                  <div class="row g-2">
                    <div class="col-6">
                      <button class="btn btn-light border w-100" type="button"
                        onclick="SV.toast('Social login is not available','info')">
                        <i class="bi bi-google me-1"></i>Google
                      </button>
                    </div>
                    <div class="col-6">
                      <button class="btn btn-light border w-100" type="button"
                        onclick="SV.toast('Social login is not available','info')">
                        <i class="bi bi-facebook me-1"></i>Facebook
                      </button>
                    </div>
                  </div>

                  <p class="text-center mt-4 mb-0">
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
  <script>
    document.getElementById('yr').textContent = new Date().getFullYear();

    // Password toggle
    document.getElementById('togglePw').addEventListener('click', function () {
      const p = document.getElementById('pwField');
      const i = this.querySelector('i');
      if (p.type === 'password') {
        p.type = 'text';
        i.className = 'bi bi-eye-slash';
      } else {
        p.type = 'password';
        i.className = 'bi bi-eye';
      }
    });

  </script>
</body>

</html>