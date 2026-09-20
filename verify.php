<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/database/connect.php';

$token = $_GET['token'] ?? '';
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
  exit('Invalid token.');
}
$hash = hash('sha256', $token);
$db = (new Connect())->getConnection();

// Look in users table (recommended: dedicated columns) OR password_reset_tokens
$stmt = $db->prepare(
  "SELECT id, user_id FROM password_reset_tokens
     WHERE token_hash = :h AND type = 'verify'
       AND used_at IS NULL AND expires_at > NOW() LIMIT 1"
);
$stmt->execute([':h' => $hash]);
$row = $stmt->fetch();
if (!$row)
  exit('Link expired or already used.');

$db->beginTransaction();
try {
  $db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = :uid")
    ->execute([':uid' => $row['user_id']]);
  $db->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id")
    ->execute([':id' => $row['id']]);
  audit($db, (int) $row['user_id'], 'email_verified', 'users', (int) $row['user_id']);
  $db->commit();
  echo 'Email verified! You can now <a href="login.php">login</a>.';
} catch (Throwable $e) {
  $db->rollBack();
  error_log($e->getMessage());
  echo 'Verification failed.';
}