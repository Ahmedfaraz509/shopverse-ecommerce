<?php
class Connect
{
  private string $host = "localhost";
  private string $db_name = "shopverse";
  private string $username = "root";
  private string $password = "";
  private ?PDO $conn = null;

  public function getConnection(): PDO
  {
    if ($this->conn instanceof PDO) {
      return $this->conn;
    }

    try {
      $this->conn = new PDO(
        "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
        $this->username,
        $this->password,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
      );
    } catch (PDOException $e) {
      // Never expose DB errors to end-user
      error_log('[DB ERROR] ' . $e->getMessage());
      throw new RuntimeException('Database connection failed.');
    }

    return $this->conn;
  }
}