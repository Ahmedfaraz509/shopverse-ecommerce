<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connect.php';

const SESSION_TIMEOUT = 1800; // 30 minutes

/** Check if user is logged in AND session not expired */
function is_logged_in(): bool
{
  if (empty($_SESSION['user_id'])) {
    return restore_from_remember_cookie();
  }

  // Session timeout
  if (
    !empty($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT
  ) {
    destroy_session();
    return false;
  }
  $_SESSION['last_activity'] = time();

  // IP binding (optional, may break mobile users with rotating IPs)
  // if (($_SESSION['ip'] ?? '') !== client_ip()) { destroy_session(); return false; }

  // User agent fingerprint
  if (($_SESSION['ua_hash'] ?? '') !== hash('sha256', user_agent())) {
    destroy_session();
    return false;
  }

  return true;
}

/** "Remember me": log the user back in from the sv_remember cookie. */
function restore_from_remember_cookie(): bool
{
  $c = $_COOKIE['sv_remember'] ?? '';
  if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{64})$/', $c, $m)) {
    return false;
  }
  try {
    $db = (new Connect())->getConnection();
    $st = $db->prepare(
      "SELECT rt.validator_hash, u.id, u.name, u.email, u.status, r.name AS role
         FROM remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         JOIN roles r ON r.id = u.role_id
        WHERE rt.selector = :s AND rt.expires_at > NOW() LIMIT 1"
    );
    $st->execute([':s' => $m[1]]);
    $row = $st->fetch();
    if (!$row || $row['status'] !== 'active' || !hash_equals($row['validator_hash'], hash('sha256', $m[2]))) {
      return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['user_email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ua_hash'] = hash('sha256', user_agent());
    $_SESSION['ip'] = client_ip();
    return true;
  } catch (Throwable $e) {
    error_log('[REMEMBER ERROR] ' . $e->getMessage());
    return false;
  }
}

/** Force login or redirect */
function require_login(string $redirect = 'login.php'): void
{
  if (!is_logged_in()) {
    flash_set('error', 'Please login to continue.');
    header("Location: $redirect");
    exit;
  }
}

/**
 * For JSON endpoints: answer 401 + JSON instead of redirecting to login.php (an HTML page),
 * which made fetch(...).json() fail for guests with "Unexpected token '<'".
 */
function require_login_json(): void
{
  if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'message' => 'Please log in to continue.', 'login_required' => true]);
    exit;
  }
}

/** Require specific role (admin etc.) */
function require_role(string $role, string $redirect = 'login.php'): void
{
  require_login($redirect);
  if (($_SESSION['role'] ?? '') !== $role) {
    http_response_code(403);
    exit('Access denied.');
  }
}

/** Fully destroy session */
function destroy_session(): void
{
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $p['path'],
      $p['domain'],
      $p['secure'],
      $p['httponly']
    );
  }
  session_destroy();
}

/** Get current user id */
function current_user_id(): ?int
{
  return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/** Get current user role */
function current_role(): ?string
{
  return $_SESSION['role'] ?? null;
}