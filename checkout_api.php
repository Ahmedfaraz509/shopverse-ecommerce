<?php
declare(strict_types=1);

/* ==================================================================
 |  checkout_api.php  —  login-required
 |
 |  GET  checkout_api.php?action=data        → user + cart + summary
 |  POST checkout_api.php?action=place       → place the order
 * ================================================================== */

require_once __DIR__ . '/includes/auth.php';       // adjust if different
require_login_json();

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

require_once __DIR__ . '/Checkout_process.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

$rawBody = file_get_contents('php://input') ?: '';
$json = json_decode($rawBody, true);
$input = array_merge($_GET, $_POST, is_array($json) ? $json : []);

$action = strtolower(trim((string) ($input['action'] ?? 'data')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($userId <= 0) {
  jsonResponse(['success' => false, 'message' => 'Not authenticated.'], 401);
}

try {
  $svc = new Checkout_process(null, $userId);

  $result = match ($action) {

    'data', 'summary', 'get' => $svc->getCheckoutData(),

    'place', 'submit', 'order' => $svc->placeOrder($input),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[checkout_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}