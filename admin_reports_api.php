<?php
declare(strict_types=1);

/* ==================================================================
 |  admin_reports_api.php  —  ADMIN only
 |
 |  GET  admin_reports_api.php?action=report&range=12m
 |  GET  admin_reports_api.php?action=export&range=12m      (CSV download)
 * ================================================================== */

require_once __DIR__ . '/../includes/auth.php';
require_role('admin', '../login.php');

if (session_status() === PHP_SESSION_NONE) {
  @session_start();
}

require_once __DIR__ . '/Admin_reports_process.php';

$input = array_merge($_GET, $_POST);
$action = strtolower(trim((string) ($input['action'] ?? 'report')));

try {
  $svc = new Admin_reports_process();

  if ($action === 'export') {
    $rows = $svc->getOrdersForExport($input);

    $filename = 'shopverse-orders-' . date('Ymd-His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');

    /* UTF-8 BOM so Excel opens it correctly */
    fwrite($out, "\xEF\xBB\xBF");

    /* header row */
    fputcsv($out, [
      'Order ID',
      'Order Number',
      'Date',
      'Customer',
      'Email',
      'Items',
      'Subtotal',
      'Discount',
      'Shipping',
      'Tax',
      'Total',
      'Payment Status',
      'Order Status',
    ]);

    foreach ($rows as $r) {
      fputcsv($out, [
        $r['id'],
        $r['order_number'],
        $r['created_at'],
        $r['customer_name'] ?? '',
        $r['customer_email'] ?? '',
        $r['item_count'],
        number_format((float) $r['subtotal'], 2, '.', ''),
        number_format((float) $r['discount'], 2, '.', ''),
        number_format((float) $r['shipping'], 2, '.', ''),
        number_format((float) $r['tax'], 2, '.', ''),
        number_format((float) $r['total'], 2, '.', ''),
        $r['payment_status'],
        $r['order_status'],
      ]);
    }

    fclose($out);
    exit;
  }

  /* default: JSON report */
  $payload = ['success' => true, 'data' => $svc->getReport($input)];

  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
} catch (Throwable $e) {
  error_log('[admin_reports_api] ' . $e->getMessage());
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'Server error.']);
  exit;
}