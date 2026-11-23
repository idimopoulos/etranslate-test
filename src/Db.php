<?php

declare(strict_types=1);

namespace App;

/**
 * PDO connection to the ddev database; creates the schema on first use.
 */
final class Db {

  private static ?\PDO $pdo = NULL;

  public static function pdo(): \PDO {
    if (self::$pdo === NULL) {
      $host = Config::get('DB_HOST', 'db');
      $name = Config::get('DB_NAME', 'db');
      self::$pdo = new \PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        Config::get('DB_USER', 'db'),
        Config::get('DB_PASSWORD', 'db'),
        [
          \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ],
      );
      self::ensureSchema(self::$pdo);
    }
    return self::$pdo;
  }

  private static function ensureSchema(\PDO $pdo): void {
    if ($pdo->query("SHOW TABLES LIKE 'translation_callback'")->fetchColumn() !== FALSE) {
      self::migrate($pdo);
      return;
    }
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
      $pdo->exec($statement);
    }
  }

  /**
   * Small additive migrations for databases created by older versions.
   */
  private static function migrate(\PDO $pdo): void {
    $columns = [
      ['translation_callback', 'decoded_result', 'LONGTEXT NULL AFTER error_message'],
      ['translation_callback', 'unresolved', 'VARCHAR(255) NULL AFTER translated_text'],
      ['translation_callback', 'fixed_text', 'LONGTEXT NULL AFTER unresolved'],
      ['translation_callback', 'fixed_unresolved', 'VARCHAR(255) NULL AFTER fixed_text'],
      ['translation_request', 'source_text', 'LONGTEXT NULL AFTER payload_type'],
      ['translation_request', 'notranslate_mode', "ENUM('wrap','placeholders') NOT NULL DEFAULT 'wrap' AFTER source_text"],
      ['translation_request', 'notranslate_patterns', 'TEXT NULL AFTER notranslate_mode'],
      ['translation_request', 'notranslate_clean', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER notranslate_patterns'],
    ];
    $status = $pdo->query("SHOW COLUMNS FROM translation_request LIKE 'status'")->fetch();
    if ($status && !str_contains($status['Type'], "'unusable'")) {
      $pdo->exec("ALTER TABLE translation_request MODIFY status ENUM('pending','sent','failed','translated','unusable','error') NOT NULL DEFAULT 'pending'");
    }
    foreach ($columns as [$table, $column, $definition]) {
      if ($pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'")->fetchColumn() === FALSE) {
        $pdo->exec("ALTER TABLE {$table} ADD {$column} {$definition}");
      }
    }
  }

  public static function insert(string $table, array $row): int {
    $columns = array_keys($row);
    $sql = sprintf(
      'INSERT INTO %s (%s) VALUES (%s)',
      $table,
      implode(', ', $columns),
      implode(', ', array_map(fn($c) => ":$c", $columns)),
    );
    self::pdo()->prepare($sql)->execute($row);
    return (int) self::pdo()->lastInsertId();
  }

  public static function update(string $table, int $id, array $row): void {
    $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($row)));
    self::pdo()->prepare("UPDATE {$table} SET {$set} WHERE id = :id")
      ->execute($row + ['id' => $id]);
  }

  public static function find(string $table, int $id): ?array {
    $stmt = self::pdo()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: NULL;
  }

  public static function all(string $sql, array $params = []): array {
    $stmt = self::pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

}
