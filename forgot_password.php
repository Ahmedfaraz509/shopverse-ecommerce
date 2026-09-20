<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/database/connect.php';

$msg = null;      // neutral message shown to the visitor
$devNote = null;  // dev-only hint (mail mode 'log' or a failed send on localhost)
$mailError = null;
$devLink = null;  // localhost only: reset link shown on screen when email can't be sent

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $msg = 'Invalid session. Please reload the page and try again.';
  } else {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $ip = client_ip();
    $db = (new Connect())->getConnection();

    // Always show the same message (prevents email enumeration)
    $msg = 'If that email exists, a reset link has been sent.';

    // Rate limit: max 5 requests per minute per email/IP
    if (is_rate_limited($db, $email, $ip, 5, 1, 'reset')) {
      $msg = 'Too many reset requests. Please try again in ' . lockout_wait_text(1) . '.';
    } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
      log_attempt($db, $email, $ip, false, 'reset');

      $stmt = $db->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
      $stmt->execute([':e' => $email]);
      $uid = $stmt->fetchColumn();

      if ($uid) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        // Invalidate any earlier unused reset links for this user
        $db->prepare(
          "UPDATE password_reset_tokens SET used_at = NOW()
              WHERE user_id = :uid AND type = 'reset' AND used_at IS NULL"
        )->execute([':uid' => $uid]);

        $db->prepare(
          "INSERT INTO password_reset_tokens (user_id, token_hash, type, expires_at, created_at)
                     VALUES (:uid, :h, 'reset', (NOW() + INTERVAL 1 HOUR), NOW())"
        )->execute([':uid' => $uid, ':h' => $tokenHash]);

        $link = app_url('reset_password.php?token=' . $token);
        $devLink = $link; // only displayed on localhost (see below)
        $sent = send_mail(
          $email,
          'Reset your ShopVerse password',
          "<p>Click below to reset your password (valid 1 hour):</p>
                     <p><a href='$link'>$link</a></p>"
        );

        if (!$sent) {
          $mailError = mail_last_error();
          error_log('[MAIL ERROR] ' . $mailError);
        }

        audit($db, (int) $uid, 'password_reset_requested', 'users', (int) $uid, [
          'mail_sent' => $sent ? 1 : 0,
        ]);
      }
    }

    $cfg = mail_config();
    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if ($isLocal && ($mailError || $cfg['mode'] === 'log')) {
      // Local testing only: email isn't configured, so show the link on screen.
      $devNote = 'Local test mode: real email is not set up yet'
        . ($mailError ? ' (' . $mailError . ')' : '')
        . ', so the reset link is shown below. Add your Gmail App Password in includes/mail_config.php to send real emails.';
    } else {
      $devLink = null; // never show the link on a real server
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Forgot Password – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container py-5" style="max-width:480px">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Reset your password</h4>
        <?php if ($msg): ?>
          <div class="alert alert-info">
            <?= e($msg) ?>
          </div>
        <?php endif; ?>
        <?php if ($devLink): ?>
          <a href="<?= e($devLink) ?>" class="btn btn-success w-100 mb-3">Open reset link (local test)</a>
        <?php endif; ?>
        <?php if ($devNote): ?>
          <div class="alert alert-warning small">
            <?= e($devNote) ?>
          </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required maxlength="255">
          </div>
          <button class="btn btn-primary w-100">Send reset link</button>
        </form>
        <p class="text-center mt-3 mb-0"><a href="login.php">Back to login</a></p>
      </div>
    </div>
  </div>
</body>

</html>