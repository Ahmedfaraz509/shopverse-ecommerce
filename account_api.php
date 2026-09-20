<?php
declare(strict_types=1);

/* ==================================================================
 |  account_api.php  —  "My Account" endpoint (login required)
 |
 |  GET  account_api.php?action=overview
 |  POST account_api.php?action=update_profile   {fullname, phone, address}
 |  POST account_api.php?action=save_address     {id?, address_line, city, state, postal_code, country, is_default}
 |  POST account_api.php?action=change_password  {current, new, confirm}
 * ================================================================== */

require_once __DIR__ . '/includes/auth.php';       // starts the SHOPVERSE_SESS session
require_once __DIR__ . '/Account_process.php';

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!is_logged_in()) {
  jsonResponse(['success' => false, 'message' => 'Please log in.'], 401);
}
$userId = (int) ($_SESSION['user_id'] ?? 0);

$rawBody = file_get_contents('php://input') ?: '';
$json = json_decode($rawBody, true);
$input = array_merge($_GET, $_POST, is_array($json) ? $json : []);
$action = strtolower(trim((string) ($input['action'] ?? 'overview')));

/* state-changing actions must be POST */
if (in_array($action, ['update_profile', 'save_address', 'change_password'], true)
  && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  jsonResponse(['success' => false, 'message' => 'POST required.'], 405);
}

try {
  $svc = new Account_process(null, $userId);

  $result = match ($action) {
    'overview', 'get' => $svc->overview(),
    'update_profile' => $svc->updateProfile($input),
    'save_address' => $svc->saveAddress($input),
    'change_password' => $svc->changePassword(
      (string) ($input['current'] ?? ''),
      (string) ($input['new'] ?? ''),
      (string) ($input['confirm'] ?? '')
    ),
    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[account_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}
