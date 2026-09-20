<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/database/connect.php';

if (is_logged_in()) {
  $db = (new Connect())->getConnection();
  audit($db, current_user_id(), 'logout', 'users', current_user_id());

  // Clear remember cookie if any
  if (!empty($_COOKIE['sv_remember'])) {
    [$selector] = explode(':', $_COOKIE['sv_remember'], 2);
    $db->prepare("DELETE FROM remember_tokens WHERE selector = :s")
      ->execute([':s' => $selector]);
    setcookie('sv_remember', '', time() - 3600, '/');
  }
}

destroy_session();
header('Location: login.php');
exit;