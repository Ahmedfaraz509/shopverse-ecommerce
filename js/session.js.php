<?php
// Exposes the PHP login session to the front-end scripts (app.js header, account page...).
// Loaded BEFORE js/app.js:  <script src="js/session.js.php"></script>
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store');

$user = null;
if (is_logged_in()) {
  $user = [
    'id' => current_user_id(),
    'name' => (string) ($_SESSION['user_name'] ?? ''),
    'email' => (string) ($_SESSION['user_email'] ?? ''),
    'role' => (string) ($_SESSION['role'] ?? 'customer'),
  ];
}
echo 'window.SV_USER = ',
  json_encode($user, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),
  ';';
