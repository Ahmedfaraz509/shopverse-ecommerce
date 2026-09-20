<?php
declare(strict_types=1);

/* ==================================================================
 |  Account_process  —  ShopVerse (customer "My Account" page)
 |
 |  Everything on the account page now comes from the database:
 |    users, addresses, orders, order_items, wishlist, products
 |  (before, the page showed demo orders / demo addresses from localStorage)
 * ================================================================== */

foreach ([
  __DIR__ . '/database/connect.php',
  __DIR__ . '/../database/connect.php',
] as $__candidate) {
  if (is_file($__candidate)) {
    require_once $__candidate;
    break;
  }
}
unset($__candidate);

if (!class_exists('Connect')) {
  throw new RuntimeException('Connect class not found — check the path to database/connect.php');
}

class Account_process
{
  private PDO $db;
  private int $userId;

  public function __construct(?PDO $db, int $userId)
  {
    $this->db = $db ?? (new Connect())->getConnection();
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->userId = $userId;
  }

  /* ------------------------------------------------------------
   |  Dashboard data
   * ------------------------------------------------------------ */
  public function overview(): array
  {
    $st = $this->db->prepare(
      "SELECT id, name, email, phone, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1"
    );
    $st->execute([':id' => $this->userId]);
    $user = $st->fetch();
    if (!$user) {
      return ['success' => false, 'message' => 'Account not found.'];
    }

    $st = $this->db->prepare(
      "SELECT COUNT(*)                                                     AS total,
              COALESCE(SUM(order_status IN ('pending','processing')), 0)   AS pending,
              COALESCE(SUM(order_status = 'delivered'), 0)                 AS completed
         FROM orders WHERE user_id = :u"
    );
    $st->execute([':u' => $this->userId]);
    $o = $st->fetch();

    $st = $this->db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = :u");
    $st->execute([':u' => $this->userId]);
    $wishCount = (int) $st->fetchColumn();

    $st = $this->db->prepare(
      "SELECT o.id, o.order_number, o.created_at, o.total, o.order_status, o.payment_status,
              (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
         FROM orders o
        WHERE o.user_id = :u
     ORDER BY o.created_at DESC, o.id DESC
        LIMIT 5"
    );
    $st->execute([':u' => $this->userId]);
    $recent = array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'order_number' => $r['order_number'],
      'date' => substr((string) $r['created_at'], 0, 10),
      'total' => (float) $r['total'],
      'status' => $r['order_status'],
      'payment_status' => $r['payment_status'],
      'item_count' => (int) $r['item_count'],
    ], $st->fetchAll());

    $st = $this->db->prepare(
      "SELECT p.id, p.name, p.slug, p.price,
              (SELECT pi.image_path FROM product_images pi
                WHERE pi.product_id = p.id
             ORDER BY pi.is_primary DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS image
         FROM wishlist w
         JOIN products p ON p.id = w.product_id
        WHERE w.user_id = :u AND p.status = 'active'
     ORDER BY w.created_at DESC, w.id DESC
        LIMIT 4"
    );
    $st->execute([':u' => $this->userId]);
    $wish = array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'name' => $r['name'],
      'slug' => $r['slug'],
      'price' => (float) $r['price'],
      'image' => $r['image'],
    ], $st->fetchAll());

    return [
      'success' => true,
      'data' => [
        'user' => [
          'id' => (int) $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'phone' => (string) ($user['phone'] ?? ''),
          'email_verified' => $user['email_verified_at'] !== null,
          'member_since' => substr((string) $user['created_at'], 0, 10),
        ],
        'stats' => [
          'orders' => (int) $o['total'],
          'pending' => (int) $o['pending'],
          'completed' => (int) $o['completed'],
          'wishlist' => $wishCount,
        ],
        'recent_orders' => $recent,
        'wishlist_preview' => $wish,
        'addresses' => $this->addresses(),
        'default_address_line' => $this->defaultAddressLine(),
      ],
    ];
  }

  /* ------------------------------------------------------------
   |  Profile (users + default address)
   * ------------------------------------------------------------ */
  public function updateProfile(array $in): array
  {
    $name = trim((string) ($in['fullname'] ?? $in['name'] ?? ''));
    $phone = trim((string) ($in['phone'] ?? ''));
    $address = trim((string) ($in['address'] ?? ''));

    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
      $errors[] = 'Full name must be 3–100 characters.';
    }
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
      $errors[] = 'Please enter a valid phone number.';
    }
    if ($address === '' || mb_strlen($address) > 500) {
      $errors[] = 'Address is required (max 500 characters).';
    }
    if ($errors) {
      return ['success' => false, 'message' => implode(' ', $errors)];
    }

    $parts = preg_split('/\s+/', $name, 2);
    $first = $parts[0] ?? $name;
    $last = $parts[1] ?? '';

    try {
      $this->db->beginTransaction();

      $this->db->prepare("UPDATE users SET name = :n, phone = :p WHERE id = :id")
        ->execute([':n' => $name, ':p' => $phone, ':id' => $this->userId]);

      $st = $this->db->prepare(
        "SELECT id FROM addresses WHERE user_id = :u AND is_default = 1 ORDER BY id ASC LIMIT 1"
      );
      $st->execute([':u' => $this->userId]);
      $addrId = $st->fetchColumn();

      if ($addrId) {
        $this->db->prepare(
          "UPDATE addresses SET first_name = :f, last_name = :l, phone = :p, address_line = :a WHERE id = :id"
        )->execute([':f' => $first, ':l' => $last, ':p' => $phone, ':a' => $address, ':id' => $addrId]);
      } else {
        $this->db->prepare(
          "INSERT INTO addresses (user_id, first_name, last_name, phone, address_line, city, country, address_type, is_default)
           VALUES (:u, :f, :l, :p, :a, '', 'Pakistan', 'both', 1)"
        )->execute([':u' => $this->userId, ':f' => $first, ':l' => $last, ':p' => $phone, ':a' => $address]);
      }

      $this->db->commit();
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ACCOUNT][PROFILE] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not save your profile.'];
    }

    $_SESSION['user_name'] = $name;
    return ['success' => true, 'message' => 'Profile updated.', 'name' => $name];
  }

  /* ------------------------------------------------------------
   |  Address book
   * ------------------------------------------------------------ */
  public function addresses(): array
  {
    $st = $this->db->prepare(
      "SELECT id, first_name, last_name, phone, address_line, city, state, postal_code, country, is_default
         FROM addresses
        WHERE user_id = :u
     ORDER BY is_default DESC, id DESC
        LIMIT 20"
    );
    $st->execute([':u' => $this->userId]);
    return array_map(static fn(array $r): array => [
      'id' => (int) $r['id'],
      'name' => trim($r['first_name'] . ' ' . $r['last_name']),
      'phone' => $r['phone'],
      'address_line' => $r['address_line'],
      'city' => $r['city'],
      'state' => (string) ($r['state'] ?? ''),
      'postal_code' => (string) ($r['postal_code'] ?? ''),
      'country' => $r['country'],
      'is_default' => (bool) $r['is_default'],
    ], $st->fetchAll());
  }

  private function defaultAddressLine(): string
  {
    $st = $this->db->prepare(
      "SELECT address_line FROM addresses WHERE user_id = :u ORDER BY is_default DESC, id ASC LIMIT 1"
    );
    $st->execute([':u' => $this->userId]);
    return (string) ($st->fetchColumn() ?: '');
  }

  /** Add (no id) or edit (id) one of the user's own addresses. */
  public function saveAddress(array $in): array
  {
    $id = (int) ($in['id'] ?? 0);
    $line = trim((string) ($in['address_line'] ?? ''));
    $city = trim((string) ($in['city'] ?? ''));
    $state = trim((string) ($in['state'] ?? ''));
    $postal = trim((string) ($in['postal_code'] ?? ''));
    $country = trim((string) ($in['country'] ?? '')) ?: 'Pakistan';
    $makeDefault = !empty($in['is_default']);

    $errors = [];
    if ($line === '' || mb_strlen($line) > 500) {
      $errors[] = 'Street address is required (max 500 characters).';
    }
    if ($city === '' || mb_strlen($city) > 100) {
      $errors[] = 'City is required.';
    }
    if (mb_strlen($state) > 100) {
      $errors[] = 'State is too long.';
    }
    if ($postal !== '' && !preg_match('/^[A-Za-z0-9\s\-]{3,20}$/', $postal)) {
      $errors[] = 'Please enter a valid postal code.';
    }
    if (mb_strlen($country) > 100) {
      $errors[] = 'Country is too long.';
    }
    if ($errors) {
      return ['success' => false, 'message' => implode(' ', $errors)];
    }

    try {
      $this->db->beginTransaction();

      if ($id > 0) {
        $st = $this->db->prepare("SELECT id FROM addresses WHERE id = :id AND user_id = :u LIMIT 1");
        $st->execute([':id' => $id, ':u' => $this->userId]);
        if (!$st->fetchColumn()) {
          $this->db->rollBack();
          return ['success' => false, 'message' => 'Address not found.'];
        }
        $this->db->prepare(
          "UPDATE addresses SET address_line = :a, city = :c, state = :s, postal_code = :p, country = :k
            WHERE id = :id AND user_id = :u"
        )->execute([
              ':a' => $line,
              ':c' => $city,
              ':s' => $state !== '' ? $state : null,
              ':p' => $postal !== '' ? $postal : null,
              ':k' => $country,
              ':id' => $id,
              ':u' => $this->userId,
            ]);
      } else {
        $u = $this->db->prepare("SELECT name, phone FROM users WHERE id = :id");
        $u->execute([':id' => $this->userId]);
        $user = $u->fetch() ?: ['name' => '', 'phone' => ''];
        $parts = preg_split('/\s+/', trim((string) $user['name']), 2);

        $this->db->prepare(
          "INSERT INTO addresses (user_id, first_name, last_name, phone, address_line, city, state, postal_code, country, address_type, is_default)
           VALUES (:u, :f, :l, :ph, :a, :c, :s, :p, :k, 'both', 0)"
        )->execute([
              ':u' => $this->userId,
              ':f' => $parts[0] ?? '',
              ':l' => $parts[1] ?? '',
              ':ph' => (string) ($user['phone'] ?? ''),
              ':a' => $line,
              ':c' => $city,
              ':s' => $state !== '' ? $state : null,
              ':p' => $postal !== '' ? $postal : null,
              ':k' => $country,
            ]);
        $id = (int) $this->db->lastInsertId();
      }

      if ($makeDefault) {
        $this->db->prepare("UPDATE addresses SET is_default = (id = :id) WHERE user_id = :u")
          ->execute([':id' => $id, ':u' => $this->userId]);
      }

      $this->db->commit();
    } catch (Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      error_log('[ACCOUNT][ADDRESS] ' . $e->getMessage());
      return ['success' => false, 'message' => 'Could not save the address.'];
    }

    return ['success' => true, 'message' => 'Address saved.', 'addresses' => $this->addresses()];
  }

  /* ------------------------------------------------------------
   |  Password
   * ------------------------------------------------------------ */
  public function changePassword(string $current, string $new, string $confirm): array
  {
    $email = (string) ($_SESSION['user_email'] ?? '');
    $ip = client_ip();

    if (failed_attempts($this->db, $email, $ip, 1, 'pwchange') >= 5) {
      return ['success' => false, 'message' => 'Too many attempts. Please try again in 1 minute.'];
    }
    if ($current === '' || $new === '') {
      return ['success' => false, 'message' => 'Please fill in both password fields.'];
    }
    if ($confirm !== '' && $confirm !== $new) {
      return ['success' => false, 'message' => 'The new passwords do not match.'];
    }
    if (!is_strong_password($new)) {
      return ['success' => false, 'message' => 'Password must be 8+ chars and include upper, lower, digit, and symbol.'];
    }

    $st = $this->db->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $st->execute([':id' => $this->userId]);
    $hash = (string) $st->fetchColumn();

    if ($hash === '' || !password_verify($current, $hash)) {
      log_attempt($this->db, $email, $ip, false, 'pwchange');
      return ['success' => false, 'message' => 'Your current password is incorrect.'];
    }
    if (hash_equals($current, $new)) {
      return ['success' => false, 'message' => 'Choose a new password that is different from the current one.'];
    }

    $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
      ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $this->userId]);

    // drop "remember me" logins on other devices
    $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = :id")->execute([':id' => $this->userId]);

    audit($this->db, $this->userId, 'password_changed', 'users', $this->userId);
    return ['success' => true, 'message' => 'Password changed.'];
  }
}
