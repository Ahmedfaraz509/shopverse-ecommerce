<?php
/**
 * ShopVerse mail helper.
 *
 * send_mail() returns true only when the message was really handed off.
 * When it fails, the reason is available from mail_last_error().
 *
 * Configure everything in includes/mail_config.php.
 * No Composer / PHPMailer needed - the SMTP client below is self-contained.
 */

function mail_config(): array
{
  static $cfg = null;
  if ($cfg === null) {
    $file = __DIR__ . '/mail_config.php';
    $cfg = is_file($file) ? require $file : [];
    $cfg += ['mode' => 'log', 'from' => 'noreply@shopverse.com', 'from_name' => 'ShopVerse', 'always_log' => true];
    $cfg['smtp'] = ($cfg['smtp'] ?? []) + [
      'host' => 'smtp.gmail.com',
      'port' => 587,
      'secure' => 'tls',
      'user' => '',
      'pass' => '',
      'timeout' => 20,
      'verify_ssl' => true,
    ];
  }
  return $cfg;
}

function mail_last_error(?string $set = null): string
{
  static $err = '';
  if ($set !== null) {
    $err = $set;
  }
  return $err;
}

function mail_log(string $line): void
{
  $log = __DIR__ . '/../logs/mail.log';
  if (!is_dir(dirname($log))) {
    @mkdir(dirname($log), 0775, true);
  }
  @file_put_contents($log, $line, FILE_APPEND);
}

function send_mail(string $to, string $subject, string $htmlBody): bool
{
  $cfg = mail_config();
  mail_last_error('');

  if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    mail_last_error('Invalid recipient address.');
    return false;
  }

  $mode = $cfg['mode'];
  $ok = false;

  if ($mode === 'smtp') {
    $ok = smtp_send($to, $subject, $htmlBody, $cfg);
  } elseif ($mode === 'mail') {
    $headers = "MIME-Version: 1.0\r\n"
      . "Content-Type: text/html; charset=UTF-8\r\n"
      . 'From: ' . $cfg['from_name'] . ' <' . $cfg['from'] . ">\r\n";
    $ok = @mail($to, $subject, $htmlBody, $headers);
    if (!$ok) {
      mail_last_error('PHP mail() failed. On XAMPP/localhost there is no mail server - use mode "smtp".');
    }
  } else { // 'log'
    $ok = true;
  }

  if ($mode === 'log' || !empty($cfg['always_log']) || !$ok) {
    mail_log(
      '[' . date('Y-m-d H:i:s') . '] MODE: ' . $mode
      . ' | ' . ($ok ? 'SENT' : 'FAILED: ' . mail_last_error())
      . ' | TO: ' . $to . ' | SUBJ: ' . $subject . "\n" . $htmlBody . "\n\n"
    );
  }

  return $ok;
}

/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS or implicit SSL).
 */
function smtp_send(string $to, string $subject, string $htmlBody, array $cfg): bool
{
  $s = $cfg['smtp'];
  $host = $s['host'];
  $port = (int) $s['port'];
  $timeout = (int) $s['timeout'];
  $secure = strtolower((string) $s['secure']);

  if ($s['user'] === '' || $s['pass'] === '' || str_starts_with((string) $s['pass'], 'your-')) {
    mail_last_error('SMTP username/password not set in includes/mail_config.php.');
    return false;
  }

  $target = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
  $verify = !empty($s['verify_ssl']);
  $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify]]);
  $fp = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);

  if (!$fp) {
    mail_last_error("Cannot connect to $target ($errno $errstr). Check the port, your firewall or antivirus.");
    return false;
  }
  stream_set_timeout($fp, $timeout);

  $read = function () use ($fp): string {
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
      $data .= $line;
      if (strlen($line) < 4 || $line[3] !== '-') {
        break;
      }
    }
    return $data;
  };
  $cmd = function (string $c, string $expect) use ($fp, $read): bool {
    if ($c !== '') {
      fwrite($fp, $c . "\r\n");
    }
    $r = $read();
    if (!str_starts_with($r, $expect)) {
      mail_last_error('SMTP error after "' . (explode(' ', $c)[0] ?: 'CONNECT') . '": ' . trim($r));
      return false;
    }
    return true;
  };

  $me = $_SERVER['SERVER_NAME'] ?? 'localhost';

  try {
    if (!$cmd('', '220'))
      return false;
    if (!$cmd("EHLO $me", '250'))
      return false;

    if ($secure === 'tls') {
      if (!$cmd('STARTTLS', '220'))
        return false;
      $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
      if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
        mail_last_error('STARTTLS failed. Enable extension=openssl in php.ini, or set \'verify_ssl\' => false in mail_config.php (local testing only).');
        return false;
      }
      if (!$cmd("EHLO $me", '250'))
        return false;
    }

    if (!$cmd('AUTH LOGIN', '334'))
      return false;
    if (!$cmd(base64_encode($s['user']), '334'))
      return false;
    if (!$cmd(base64_encode($s['pass']), '235')) {
      mail_last_error(mail_last_error() . ' (Gmail needs a 16-character App Password, not your normal password.)');
      return false;
    }

    // Gmail only accepts mail sent as the authenticated account itself
    $isGoogle = stripos($host, 'gmail') !== false || stripos($host, 'google') !== false;
    $from = $isGoogle ? $s['user'] : ($cfg['from'] ?: $s['user']);
    if (!$cmd('MAIL FROM:<' . $from . '>', '250'))
      return false;
    if (!$cmd('RCPT TO:<' . $to . '>', '250'))
      return false;
    if (!$cmd('DATA', '354'))
      return false;

    $headers = 'From: ' . mb_encode_mimeheader($cfg['from_name']) . ' <' . $from . ">\r\n"
      . 'To: <' . $to . ">\r\n"
      . 'Subject: ' . mb_encode_mimeheader($subject) . "\r\n"
      . 'Date: ' . date('r') . "\r\n"
      . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $me . ">\r\n"
      . "MIME-Version: 1.0\r\n"
      . "Content-Type: text/html; charset=UTF-8\r\n"
      . "Content-Transfer-Encoding: 8bit\r\n";

    // Normalise line endings and dot-stuff
    $body = preg_replace("/\r\n|\r|\n/", "\r\n", $htmlBody);
    $body = preg_replace("/^\./m", '..', $body);

    fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
    if (!$cmd('', '250'))
      return false;

    $cmd('QUIT', '221');
    return true;
  } finally {
    @fclose($fp);
  }
}
