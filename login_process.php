<?php
require_once __DIR__ . '/database/connect.php';
require_once __DIR__ . '/includes/security.php';

class Login
{
  private PDO $db;

  public function __construct()
  {
    $this->db = (new Connect())->getConnection();
  }

  /**
   * Attempt login.
   * @return array{ok:bool, message:string, redirect?:string}
   */
  public function attempt(array $input): array
  {
    // 1) CSRF
    if (!csrf_verify($input['csrf_token'] ?? null)) {
      return ['ok' => false, 'message' => 'Invalid session. Please refresh.'];
    }

    $ip = client_ip();
    $email = strtolower(trim($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $remember = !empty($input['remember']);

    // 2) Server-side validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
      log_attempt($this->db, $email ?: null, $ip, false);
      return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    // 3) Rate limit / lockout check
    if (is_locked_out($this->db, $email, $ip)) {
      audit($this->db, null, 'login_locked_out', 'users', null, ['email' => $email]);
      return [
        'ok' => false,
        'message' => 'Too many failed attempts. Try again in '
          . lockout_wait_text(LOGIN_LOCKOUT_MINUTES) . '.',
      ];
    }

    // 4) Fetch user
    $stmt = $this->db->prepare(
      "SELECT u.id, u.name, u.email, u.password_hash, u.status,
                    u.email_verified_at, r.name AS role
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email LIMIT 1"
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 5) Generic error (never reveal which field is wrong)
    $genericFail = function () use ($email, $ip) {
      log_attempt($this->db, $email, $ip, false);
      return ['ok' => false, 'message' => 'Invalid email or password.'];
    };

    if (!$user) {
      // Timing-attack mitigation: do a dummy verify anyway
      password_verify($password, '$2y$12$C6UzMDM.H6dfI/f/IKcEeO3Sd0p1fB7xHsGqAq/2i1fXBxV5qk0Iu');
      return $genericFail();
    }

    // 6) Account status
    if ($user['status'] === 'suspended') {
      log_attempt($this->db, $email, $ip, false);
      audit($this->db, (int) $user['id'], 'login_suspended', 'users', (int) $user['id']);
      return ['ok' => false, 'message' => 'Your account is suspended. Contact support.'];
    }
    if ($user['status'] === 'inactive') {
      log_attempt($this->db, $email, $ip, false);
      return ['ok' => false, 'message' => 'Your account is inactive.'];
    }

    // 7) Password verify
    $stored = (string) $user['password_hash'];
    $valid = password_verify($password, $stored);

    // Legacy plain-text password (the seeded admin row had 'admin123' stored
    // as-is, so password_verify() could never match it). Accept it once and
    // immediately upgrade it to a real hash.
    if (!$valid && password_get_info($stored)['algo'] === null && hash_equals($stored, $password)) {
      $valid = true;
      $newHash = password_hash($password, PASSWORD_DEFAULT);
      $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
        ->execute([':h' => $newHash, ':id' => $user['id']]);
      $user['password_hash'] = $newHash;
    }

    if (!$valid) {
      audit($this->db, (int) $user['id'], 'login_failed', 'users', (int) $user['id']);
      return $genericFail(); // logs the attempt exactly once
    }

    // 8) Email verification (optional — uncomment if required)
    // if (empty($user['email_verified_at'])) {
    //     log_attempt($this->db, $email, $ip, false);
    //     return ['ok' => false, 'message' => 'Please verify your email first.'];
    // }

    // 9) Password rehash if algorithm changed
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
      $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
        ->execute([
          ':h' => password_hash($password, PASSWORD_DEFAULT),
          ':id' => $user['id'],
        ]);
    }

    // 10) Success — regenerate session (prevents fixation)
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['ua_hash'] = hash('sha256', user_agent());
    $_SESSION['ip'] = $ip;

    // Rotate CSRF token on login
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // Move any guest (session) cart into the user's saved cart (cart_items).
    // Nothing called the cart 'merge' action before, so items added while logged out were lost.
    try {
      require_once __DIR__ . '/Cart_process.php';
      (new Cart_process($this->db, (int) $user['id']))->mergeGuestCartToUser();
    } catch (Throwable $e) {
      error_log('[LOGIN] guest cart merge failed: ' . $e->getMessage());
    }

    // 11) Update last_login
    $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
      ->execute([':id' => $user['id']]);

    // 12) Remember me — secure selector/validator pattern
    if ($remember) {
      $this->set_remember_cookie((int) $user['id']);
    }

    // 13) Logs
    log_attempt($this->db, $email, $ip, true);
    audit($this->db, (int) $user['id'], 'login_success', 'users', (int) $user['id']);

    // 14) Redirect by role
    $redirect = $user['role'] === 'admin' ? 'admin/index.php' : 'index.php';

    return [
      'ok' => true,
      'message' => 'Login successful.',
      'redirect' => $redirect,
    ];
  }

  /**
   * Remember-me cookie using selector + validator pattern.
   * Add table `remember_tokens (id, user_id, selector, validator_hash, expires_at)`.
   */
  private function set_remember_cookie(int $userId): void
  {
    $selector = bin2hex(random_bytes(9));   // 18 chars
    $validator = bin2hex(random_bytes(32));  // 64 chars
    $vHash = hash('sha256', $validator);
    $expires = time() + (30 * 24 * 60 * 60); // 30 days

    $stmt = $this->db->prepare(
      "INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, created_at)
             VALUES (:uid, :sel, :vh, FROM_UNIXTIME(:exp), NOW())"
    );
    $stmt->execute([
      ':uid' => $userId,
      ':sel' => $selector,
      ':vh' => $vHash,
      ':exp' => $expires,
    ]);

    setcookie(
      'sv_remember',
      $selector . ':' . $validator,
      [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
      ]
    );
  }
}