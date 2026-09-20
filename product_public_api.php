<?php
declare(strict_types=1);

/* ==================================================================
 |  product_public_api.php
 |
 |  GET  product_public_api.php?action=get&slug=classic-tshirt
 |  GET  product_public_api.php?action=get&id=12
 |  GET  product_public_api.php?action=related&id=12&limit=4
 |  GET  product_public_api.php?action=reviews&id=12
 |  GET  product_public_api.php?action=full&slug=classic-tshirt
 |         → product + images + variants + reviews + breakdown + related
 * ================================================================== */

require_once __DIR__ . '/Product_public_process.php';

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string) ($input['action'] ?? 'full')));

function jsonResponse(array $payload, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: public, max-age=120');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $svc = new Product_public_process();

  $id = (int) ($input['id'] ?? 0);
  $slug = trim((string) ($input['slug'] ?? ''));
  $key = $slug !== '' ? $slug : (string) $id;

  $result = match ($action) {

    'get', 'view' => (function () use ($svc, $key) {
        if ($key === '' || $key === '0') {
          return ['success' => false, 'message' => 'Missing product identifier.'];
        }
        $p = $svc->getBySlugOrId($key);
        return $p
        ? ['success' => true, 'data' => $p]
        : ['success' => false, 'message' => 'Product not found.'];
      })(),

    'related' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        $catId = (int) ($input['category_id'] ?? 0);
        $limit = (int) ($input['limit'] ?? 4);
        if ($id <= 0 || $catId <= 0) {
          return ['success' => false, 'message' => 'Missing id or category_id.'];
        }
        return ['success' => true, 'data' => $svc->getRelatedOrFallback($id, $catId, $limit)];
      })(),

    'reviews' => (function () use ($svc, $input) {
        $id = (int) ($input['id'] ?? 0);
        if ($id <= 0) {
          return ['success' => false, 'message' => 'Missing id.'];
        }
        return [
        'success' => true,
        'data' => [
          'reviews' => $svc->getReviews($id),
          'breakdown' => $svc->getRatingBreakdown($id),
        ],
        ];
      })(),

    'full' => (function () use ($svc, $key, $input) {
        if ($key === '' || $key === '0') {
          return ['success' => false, 'message' => 'Missing product identifier.'];
        }
        $p = $svc->getBySlugOrId($key);
        if (!$p) {
          return ['success' => false, 'message' => 'Product not found.'];
        }

        $limit = (int) ($input['related_limit'] ?? 4);

        return [
        'success' => true,
        'data' => [
          'product' => $p,
          'related' => $svc->getRelatedOrFallback($p['id'], $p['category_id'], $limit),
          'reviews' => $svc->getReviews($p['id']),
          'breakdown' => $svc->getRatingBreakdown($p['id']),
        ],
        ];
      })(),

    default => ['success' => false, 'message' => 'Unknown action: ' . $action],
  };

  jsonResponse($result);
} catch (Throwable $e) {
  error_log('[product_public_api] ' . $e->getMessage());
  jsonResponse(['success' => false, 'message' => 'Server error.'], 500);
}