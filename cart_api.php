<?php
declare(strict_types=1);

/* ==================================================================
 |  cart_api.php
 |
 |  GET  cart_api.php?action=get
 |  GET  cart_api.php?action=count
 |  POST cart_api.php?action=add       {product_id, quantity, variant_id?}
 |  POST cart_api.php?action=set       {product_id, quantity, variant_id?}
 |  POST cart_api.php?action=remove    {product_id, variant_id?}
 |  POST cart_api.php?action=clear
 |  POST cart_api.php?action=coupon    {code}
 |  POST cart_api.php?action=coupon_remove
 |  POST cart_api.php?action=merge     (call after login)
 * ================================================================== */

/* Use the app's own session (name SHOPVERSE_SESS). A bare session_start() here creates a
 * separate PHPSESSID session, so the cart never sees the logged-in user and stays a guest cart. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/Cart_process.php';

is_logged_in(); // validates the session (timeout / user-agent) and restores "remember me" logins

$rawBody = file_get_contents('php://input') ?: '';
$json = json_decode($rawBody, true);
$input = array_merge($_GET, $_POST, is_array($json) ? $json : []);

$action = strtolower(trim((string) ($input['action'] ?? 'get')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Cart_process();

  $pid = (int) ($input['product_id'] ?? 0);
  $vid = isset($input['variant_id']) && $input['variant_id'] !== '' && $input['variant_id'] !== null
    ? (int) $input['variant_id'] : null;

  $result = match ($action) {

    'get', 'list', 'view' => [
      'success' => true,
      'data' => $svc->getCart(),
    ],

    'count' => [
      'success' => true,
      'data' => ['count' => $svc->count()],
    ],

    'add' => (function () use ($svc, $pid, $vid, $input) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        $qty = (int) ($input['quantity'] ?? 1);
        return $svc->addItem($pid, $qty, $vid);
      })(),

    'set', 'update', 'quantity' => (function () use ($svc, $pid, $vid, $input) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return $svc->setQuantity($pid, (int) ($input['quantity'] ?? 1), $vid);
      })(),

    'remove', 'delete' => (function () use ($svc, $pid, $vid) {
        if ($pid <= 0) {
          return ['success' => false, 'message' => 'Missing product_id.'];
        }
        return $svc->removeItem($pid, $vid);
      })(),

    'clear' => $svc->clearCart(!empty($input['keep_coupon'])),

    'coupon', 'apply_coupon' => $svc->applyCoupon((string) ($input['code'] ?? '')),

    'coupon_remove', 'remove_coupon' => $svc->removeCoupon(),

    'merge' => $svc->mergeGuestCartToUser(),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  // Mutating actions (add/set/remove/clear/coupon) return the cart under "cart", while the
  // cart page reads "data" like the 'get' action does. Provide both so every page works.
  if (isset($result['cart']) && !isset($result['data'])) {
    $result['data'] = $result['cart'];
  }

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[cart_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}