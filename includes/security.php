<?php
// ============================================
// ShopVerse Security Helpers
// ============================================

// Keep PHP dates (mail.log, etc.) in the same timezone as the database
date_default_timezone_set('Asia/Karachi');

if (session_status() === PHP_SESSION_NONE) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,      // HTTPS-only in production
    'httponly' => true,          // JS cannot read
    'samesite' => 'Lax',         // CSRF mitigation
  ]);
  session_name('SHOPVERSE_SESS');
  session_start();
}

// ---------- CSRF ----------
function csrf_token(): string
{
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool
{
  return is_string($token)
    && !empty($_SESSION['csrf_token'])
    && hash_equals($_SESSION['csrf_token'], $token);
}

// ---------- Output escaping ----------
function e(?string $v): string
{
  return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------- Client info ----------
function client_ip(): string
{
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function user_agent(): string
{
  return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
}

/**
 * Absolute URL to a page in the app root, e.g. app_url('verify.php?token=abc')
 * -> http://localhost/shopverse/verify.php?token=abc
 * (works whether the site is at the domain root or in a sub-folder like XAMPP's /shopverse)
 */
function app_url(string $path = ''): string
{
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
    $host = 'localhost'; // reject spoofed/malformed Host headers
  }
  $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

// ---------- Logging ----------
function log_attempt(PDO $db, ?string $email, string $ip, bool $success, string $context = 'login'): void
{
  $stmt = $db->prepare(
    "INSERT INTO login_attempts (email, ip_address, successful, context, attempted_at)
         VALUES (:e, :ip, :s, :ctx, NOW())"
  );
  $stmt->execute([
    ':e' => $email,
    ':ip' => $ip,
    ':s' => (int) $success,
    ':ctx' => $context,
  ]);
}

function audit(PDO $db, ?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
{
  $stmt = $db->prepare(
    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details)
         VALUES (:uid, :action, :etype, :eid, :ip, :ua, :details)"
  );
  $stmt->execute([
    ':uid' => $userId,
    ':action' => $action,
    ':etype' => $entity,
    ':eid' => $entityId,
    ':ip' => client_ip(),
    ':ua' => user_agent(),
    ':details' => $details ? json_encode($details) : null,
  ]);
}

// ---------- Rate limiting ----------
// Both windows are 1 MINUTE: after too many failures the user waits 60 seconds.
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 1;
const REGISTER_MAX_ATTEMPTS = 5;
const REGISTER_LOCKOUT_MINUTES = 1;

/**
 * Count failed attempts in the last $minutes minutes.
 * $context keeps the login and register counters separate, so typos on the
 * register form can never lock the login form (and vice-versa).
 */
function failed_attempts(
  PDO $db,
  string $email,
  string $ip,
  int $minutes = LOGIN_LOCKOUT_MINUTES,
  string $context = 'login'
): int {
  // MySQL will not accept a placeholder inside INTERVAL, so cast to a safe int.
  $minutes = max(1, $minutes);

  $stmt = $db->prepare(
    "SELECT COUNT(*) FROM login_attempts
         WHERE successful = 0
           AND context = :ctx
           AND attempted_at > (NOW() - INTERVAL $minutes MINUTE)
           AND (ip_address = :ip OR email = :email)"
  );
  $stmt->bindValue(':ctx', $context);
  $stmt->bindValue(':ip', $ip);
  $stmt->bindValue(':email', $email);
  $stmt->execute();
  return (int) $stmt->fetchColumn();
}

/** Count failed login attempts for ONE column (email or ip) in the last $minutes. */
function failed_attempts_by(PDO $db, string $column, string $value, int $minutes, string $context = 'login'): int
{
  if (!in_array($column, ['email', 'ip_address'], true)) {
    return 0;
  }
  $minutes = max(1, $minutes);
  $stmt = $db->prepare(
    "SELECT COUNT(*) FROM login_attempts
         WHERE successful = 0 AND context = :ctx
           AND attempted_at > (NOW() - INTERVAL $minutes MINUTE)
           AND $column = :v"
  );
  $stmt->execute([':ctx' => $context, ':v' => $value]);
  return (int) $stmt->fetchColumn();
}

/**
 * Lock only when THIS email has 5 recent failures, or when the IP has a lot
 * (25). Before, any 5 failures from the same IP (e.g. localhost while testing
 * with other accounts) locked every account, including the admin.
 */
const LOGIN_MAX_ATTEMPTS_PER_IP = 25;

function is_locked_out(PDO $db, string $email, string $ip): bool
{
  return failed_attempts_by($db, 'email', $email, LOGIN_LOCKOUT_MINUTES) >= LOGIN_MAX_ATTEMPTS
    || failed_attempts_by($db, 'ip_address', $ip, LOGIN_LOCKOUT_MINUTES) >= LOGIN_MAX_ATTEMPTS_PER_IP;
}

/**
 * Generic rate limiter (used by registration).
 * True when the email OR ip has $maxAttempts or more failed attempts
 * within the last $minutes minutes.
 */
function is_rate_limited(
  PDO $db,
  string $email,
  string $ip,
  int $maxAttempts = REGISTER_MAX_ATTEMPTS,
  int $minutes = REGISTER_LOCKOUT_MINUTES,
  string $context = 'register'
): bool {
  return failed_attempts($db, $email, $ip, $minutes, $context) >= $maxAttempts;
}

/** Wording for the wait time, e.g. "1 minute" / "5 minutes". */
function lockout_wait_text(int $minutes = LOGIN_LOCKOUT_MINUTES): string
{
  return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
}

// ---------- Password strength (register use) ----------
function is_strong_password(string $pwd): bool
{
  if (strlen($pwd) < 8 || strlen($pwd) > 72)
    return false;
  if (!preg_match('/[A-Z]/', $pwd))
    return false;
  if (!preg_match('/[a-z]/', $pwd))
    return false;
  if (!preg_match('/\d/', $pwd))
    return false;
  if (!preg_match('/[\W_]/', $pwd))
    return false;
  return true;
}

// ---------- Flash messages ----------
function flash_set(string $key, string $msg): void
{
  $_SESSION['_flash'][$key] = $msg;
}

function flash_get(string $key): ?string
{
  if (!empty($_SESSION['_flash'][$key])) {
    $msg = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $msg;
  }
  return null;
}