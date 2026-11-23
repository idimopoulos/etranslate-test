<?php

declare(strict_types=1);

namespace App;

/**
 * Signs callback URLs, like tmgmt_ec_etranslation's HashGenerator.
 *
 * Produces a 43-char URL-safe base64 HMAC-SHA256, the same shape as
 * Drupal's Crypt::hmacBase64().
 */
final class Hash {

  public static function generate(int $requestId): string {
    $hmac = base64_encode(hash_hmac('sha256', (string) $requestId, 'etranslate_test' . Config::appSecret(), TRUE));
    return str_replace(['+', '/', '='], ['-', '_', ''], $hmac);
  }

  public static function verify(int $requestId, string $hash): bool {
    return hash_equals(self::generate($requestId), $hash);
  }

}
