<?php
require_once __DIR__ . '/database/connect.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/mailer.php';

class RegisterProcess
{
  private PDO $db;

  public function __construct()
  {
    $this->db = (new Connect())->getConnection();
  }

  /**
   * Register a new customer.
   * @return array{ok:bool, message:string, user_id?:int}
   */
  public function registerUser(array $input): array
  {
    // 1) CSRF check
    if (!csrf_verify($input['csrf_token'] ?? null)) {
      return ['ok' => false, 'message' => 'Invalid session. Please refresh and try again.'];
    }

    $ip = client_ip();

    // 2) Trim & normalize
    $fullname = trim($input['fullname'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $phone = trim($input['phone'] ?? '');
    $password = (string) ($input['password'] ?? '');
    $confirm = (string) ($input['confirm'] ?? '');
    $address = trim($input['address'] ?? '');

    // 3) Server-side validation
    $errors = [];

    if ($fullname === '' || mb_strlen($fullname) < 3 || mb_strlen($fullname) > 100) {
      $errors[] = 'Full name must be 3–100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
      $errors[] = 'Please enter a valid email address.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
      $errors[] = 'Please enter a valid phone number.';
    }
    if (!is_strong_password($password)) {
      $errors[] = 'Password must be 8+ chars and include upper, lower, digit, and symbol.';
    }
    if ($password !== $confirm) {
      $errors[] = 'Passwords do not match.';
    }
    if ($address === '' || mb_strlen($address) > 500) {
      $errors[] = 'Address is required (max 500 chars).';
    }
    if (empty($input['agree'])) {
      $errors[] = 'You must accept the Terms of Service and Privacy Policy.';
    }

    // Plain form-validation errors are NOT logged as failed attempts, otherwise a
    // few typos would rate-limit the user (and previously lock the login form too).
    if ($errors) {
      return ['ok' => false, 'message' => implode(' ', $errors)];
    }

    // 4) Rate limiting (register counter only, 1-minute window)
    if (is_rate_limited($this->db, $email, $ip, REGISTER_MAX_ATTEMPTS, REGISTER_LOCKOUT_MINUTES)) {
      audit($this->db, null, 'register_rate_limited', 'users', null, ['email' => $email]);
      return [
        'ok' => false,
        'message' => 'Too many attempts. Please try again in '
          . lockout_wait_text(REGISTER_LOCKOUT_MINUTES) . '.',
      ];
    }

    // 5) Email uniqueness
    $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetchColumn()) {
      log_attempt($this->db, $email, $ip, false, 'register');
      return ['ok' => false, 'message' => 'Email already exists.'];
    }

    // 6) Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // 7) Email verification token
    $verifyToken = bin2hex(random_bytes(32));   // 64 hex chars
    $tokenHash = hash('sha256', $verifyToken); // store hash, send raw

    try {
      $this->db->beginTransaction();

      // 7a) Insert user — role = customer (never from client)
      $roleStmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'customer' LIMIT 1");
      $roleStmt->execute();
      $roleId = (int) $roleStmt->fetchColumn();
      if ($roleId <= 0) {
        throw new RuntimeException('Customer role missing.');
      }

      $sql = "INSERT INTO users (role_id, name, email, password_hash, phone, status, created_at, updated_at)
                    VALUES (:role_id, :name, :email, :hash, :phone, 'active', NOW(), NOW())";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        ':role_id' => $roleId,
        ':name' => $fullname,
        ':email' => $email,
        ':hash' => $hash,
        ':phone' => $phone,
      ]);
      $userId = (int) $this->db->lastInsertId();

      // 7b) Insert address
      $parts = preg_split('/\s+/', $fullname, 2);
      $firstName = $parts[0] ?? $fullname;
      $lastName = $parts[1] ?? '';

      $addrSql = "INSERT INTO addresses
                        (user_id, first_name, last_name, phone, address_line, city, state, postal_code, country, address_type, is_default, created_at, updated_at)
                        VALUES (:uid, :fn, :ln, :ph, :line, :city, :state, :pc, :country, 'both', 1, NOW(), NOW())";
      $addr = $this->db->prepare($addrSql);
      $addr->execute([
        ':uid' => $userId,
        ':fn' => $firstName,
        ':ln' => $lastName,
        ':ph' => $phone,
        ':line' => $address,
        ':city' => '',
        ':state' => null,
        ':pc' => null,
        ':country' => 'Pakistan',
      ]);

      // 7c) Create empty cart
      $this->db->prepare("INSERT INTO carts (user_id, created_at, updated_at) VALUES (:uid, NOW(), NOW())")
        ->execute([':uid' => $userId]);

      // 7d) Save verification token
      $this->db->prepare(
        "INSERT INTO password_reset_tokens (user_id, token_hash, type, expires_at, created_at)
                 VALUES (:uid, :hash, 'verify', (NOW() + INTERVAL 24 HOUR), NOW())"
      )->execute([':uid' => $userId, ':hash' => $tokenHash]);

      $this->db->commit();

    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[REGISTER ERROR] ' . $e->getMessage());
      try {
        log_attempt($this->db, $email, $ip, false, 'register');
      } catch (Throwable $ignored) {
      }
      return ['ok' => false, 'message' => 'Registration failed. Please try again.'];
    }

    // The account now exists. Anything below must NOT turn a successful
    // registration into a "Registration failed" message.
    try {
      // 7e) Audit + success log
      audit($this->db, $userId, 'user_registered', 'users', $userId, ['email' => $email]);
      log_attempt($this->db, $email, $ip, true, 'register');

      // 7f) Send verification email
      $link = app_url('verify.php?token=' . $verifyToken);

      $sent = send_mail(
        $email,
        'Verify your ShopVerse account',
        "<p>Welcome to ShopVerse!</p>
                 <p>Click below to verify your email (valid 24 hours):</p>
                 <p><a href='$link'>$link</a></p>"
      );

      if (!$sent) {
        // Account is created either way - just record why the email failed.
        error_log('[MAIL ERROR] ' . mail_last_error());
      }
    } catch (Throwable $e) {
      error_log('[REGISTER POST-COMMIT ERROR] ' . $e->getMessage());
    }

    return [
      'ok' => true,
      'message' => 'Registration successful. Check your email to verify.',
      'user_id' => $userId,
    ];
  }
}