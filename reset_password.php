<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/database/connect.php';

$db = (new Connect())->getConnection();
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = null;
$done = false;

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  exit('Invalid or missing token.');
}
$tokenHash = hash('sha256', $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $error = 'Invalid session.';
  } else {
    $pwd = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!is_strong_password($pwd)) {
      $error = 'Password must be 8+ chars with upper, lower, digit, symbol.';
    } elseif ($pwd !== $confirm) {
      $error = 'Passwords do not match.';
    } else {
      // Validate token
      $stmt = $db->prepare(
        "SELECT id, user_id FROM password_reset_tokens
                 WHERE token_hash = :h AND type = 'reset'
                   AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
      );
      $stmt->execute([':h' => $tokenHash]);
      $row = $stmt->fetch();

      if (!$row) {
        $error = 'Link is invalid or expired.';
      } else {
        $db->beginTransaction();
        try {
          $hash = password_hash($pwd, PASSWORD_DEFAULT);
          $db->prepare("UPDATE users SET password_hash = :h, updated_at = NOW() WHERE id = :id")
            ->execute([':h' => $hash, ':id' => $row['user_id']]);

          $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id")
            ->execute([':id' => $row['id']]);

          // Clear old failed-login lockouts so the user can sign in right away
          $db->prepare(
            "DELETE FROM login_attempts
                     WHERE successful = 0 AND context = 'login'
                       AND email = (SELECT email FROM users WHERE id = :uid)"
          )->execute([':uid' => $row['user_id']]);

          // Invalidate any remember tokens
          $db->prepare("DELETE FROM remember_tokens WHERE user_id = :uid")
            ->execute([':uid' => $row['user_id']]);

          audit($db, (int) $row['user_id'], 'password_reset_completed', 'users', (int) $row['user_id']);
          $db->commit();
          $done = true;
        } catch (Throwable $e) {
          $db->rollBack();
          error_log($e->getMessage());
          $error = 'Something went wrong.';
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reset Password – ShopVerse</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <div class="container py-5" style="max-width:480px">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="mb-3">Set new password</h4>
        <?php if ($done): ?>
          <div class="alert alert-success">Password updated. <a href="login.php">Login now</a></div>
        <?php else: ?>
          <?php if ($error): ?>
            <div class="alert alert-danger">
              <?= e($error) ?>
            </div>
          <?php endif; ?>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
              <label class="form-label">New password</label>
              <input type="password" name="password" class="form-control" required minlength="8" maxlength="72">
            </div>
            <div class="mb-3">
              <label class="form-label">Confirm password</label>
              <input type="password" name="confirm" class="form-control" required minlength="8" maxlength="72">
            </div>
            <button class="btn btn-primary w-100">Update password</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>

</html>