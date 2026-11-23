<?php

declare(strict_types=1);

namespace App;

/**
 * Language codes and the source → target pairs each domain supports.
 */
final class Languages {

  /**
   * Used when getDomains is unavailable: the 24 EU official languages.
   */
  public const FALLBACK = ['bg', 'cs', 'da', 'de', 'el', 'en', 'es', 'et', 'fi', 'fr', 'ga', 'hr', 'hu', 'it', 'lt', 'lv', 'mt', 'nl', 'pl', 'pt', 'ro', 'sk', 'sl', 'sv'];

  public static function name(string $code): string {
    $name = class_exists(\Locale::class) ? \Locale::getDisplayLanguage($code, 'en') : '';
    return $name !== '' && strtolower($name) !== strtolower($code) ? $name : strtoupper($code);
  }

  /**
   * Builds {domain: {source: [targets…]}} from getDomains data.
   *
   * Pairs look like "EN-FR" or "BG-CS-LLM"; like the module, only the first
   * two parts are used.
   *
   * @return array<string, array<string, list<string>>>
   */
  public static function pairsByDomain(array $domains): array {
    $result = [];
    foreach ($domains as $domain => $info) {
      $map = [];
      foreach ($info['languagePairs'] ?? [] as $pair) {
        $parts = explode('-', strtolower((string) $pair));
        if (count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '') {
          $map[$parts[0]][$parts[1]] = TRUE;
        }
      }
      ksort($map);
      $result[$domain] = array_map(function (array $targets): array {
        $targets = array_keys($targets);
        sort($targets);
        return $targets;
      }, $map);
    }
    return $result;
  }

  /**
   * Code → "Name" for every language in the pairs (or the fallback list).
   *
   * @return array<string, string>
   */
  public static function names(array $pairsByDomain): array {
    $codes = [];
    foreach ($pairsByDomain as $map) {
      foreach ($map as $source => $targets) {
        $codes[$source] = TRUE;
        foreach ($targets as $target) {
          $codes[$target] = TRUE;
        }
      }
    }
    $codes = $codes ? array_keys($codes) : self::FALLBACK;
    $names = [];
    foreach ($codes as $code) {
      $names[$code] = self::name($code);
    }
    asort($names);
    return $names;
  }

}
