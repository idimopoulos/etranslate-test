<?php

declare(strict_types=1);

namespace App;

/**
 * Reads settings from environment variables (populated by .ddev/.env).
 */
final class Config {

  public const ENDPOINT = 'https://language-tools.ec.europa.eu/';

  /**
   * Default non-translatable patterns, copied from Joinup develop
   * (config/sync/tmgmt.translator.ec_etranslation.yml, non_translatable_parts).
   */
  public const DEFAULT_NOTRANSLATE_PATTERNS = <<<'PATTERNS'
    # Preformatted text
    (?si)<pre\b[^>]*>.*?</pre>

    # Inline code
    (?si)<code\b[^>]*>.*?</code>

    # Drupal placeholders
    [@!%][a-zA-Z0-9:_-]+

    # Drupal tokens
    \[[a-zA-Z0-9:_-]+\]
    PATTERNS;

  /**
   * Joinup develop: non_translatable_parts.clean.
   */
  public const DEFAULT_NOTRANSLATE_CLEAN = TRUE;

  public static function get(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value === FALSE || $value === '' ? $default : $value;
  }

  public static function username(): string {
    return self::get('ETRANSLATION_USERNAME');
  }

  public static function password(): string {
    return self::get('ETRANSLATION_PASSWORD');
  }

  public static function caller(): string {
    // v2 API limit: callerInformation.username maxLength is 10.
    return mb_substr(self::get('ETRANSLATION_CALLER', 'eyi'), 0, 10);
  }

  /**
   * File written by `ddev tunnel start` with the current Quick Tunnel URL.
   */
  public const TUNNEL_URL_FILE = __DIR__ . '/../var/callback_base_url';

  /**
   * Default callback base URL: the running tunnel, else CALLBACK_BASE_URL.
   */
  public static function callbackBaseUrl(): string {
    return self::callbackBaseUrlSource()[0];
  }

  /**
   * @return array{0: string, 1: string}
   *   The URL and where it came from ('tunnel', 'env' or 'none').
   */
  public static function callbackBaseUrlSource(): array {
    if (is_file(self::TUNNEL_URL_FILE)) {
      $url = trim((string) file_get_contents(self::TUNNEL_URL_FILE));
      if ($url !== '') {
        return [rtrim($url, '/'), 'tunnel'];
      }
    }
    $env = rtrim(self::get('CALLBACK_BASE_URL'), '/');
    return [$env, $env === '' ? 'none' : 'env'];
  }

  public static function appSecret(): string {
    return self::get('APP_SECRET', 'change-me');
  }

  public static function hasCredentials(): bool {
    return self::username() !== '' && self::password() !== '';
  }

}
