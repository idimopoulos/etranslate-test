<?php

// Copied verbatim from ~/projects/tmgmt_ec_etranslation (branch notranslate-placeholders,
// uncommitted src/NonTranslatableParts.php) on 2026-11-23; only the namespace changed.

declare(strict_types=1);

namespace App;

/**
 * Protects the non-translatable parts of a text sent to eTranslation.
 *
 * Site builders configure regular expressions matching the parts of a text
 * that should not be translated. They are wrapped in [notranslate] tags, the
 * eTranslation API official way to exclude text from translation.
 *
 * Each wrapped part is sent as a numbered placeholder and restored from the
 * source text once the translation is received.
 *
 * @see https://language-tools.ec.europa.eu/dev-corner/additional-resources/tips-and-tricks#notranslate
 */
final class NonTranslatableParts {

  private const WRAPPED = '#\[notranslate](?:(?!\[/notranslate]).)*\[/notranslate]#s';

  private const PLACEHOLDER = '[notranslate]NTPH%d[/notranslate]';

  private const PLACEHOLDER_PATTERN = '#\[notranslate]\s*NTPH(\d+)\s*\[/notranslate]#';

  /**
   * The regular expressions matching non-translatable parts.
   *
   * @var list<string>
   */
  private readonly array $patterns;

  /**
   * Whether to remove [notranslate] tags missing an opening or closing tag.
   */
  private readonly bool $clean;

  /**
   * Constructs a new instance.
   *
   * @param array{patterns?: string, clean?: bool}|null $settings
   *   The translator 'non_translatable_parts' setting.
   */
  public function __construct(?array $settings) {
    $patterns = str_replace("\r", "\n", (string) ($settings['patterns'] ?? ''));
    $patterns = array_filter(array_map('trim', explode("\n", $patterns)));
    $this->patterns = array_values(array_filter(
      $patterns,
      fn(string $pattern): bool => !str_starts_with($pattern, '#'),
    ));
    $this->clean = (bool) ($settings['clean'] ?? FALSE);
  }

  /**
   * Wraps the non-translatable parts of a text in [notranslate] tags.
   *
   * Parts of the text already wrapped, either in the original text or by a
   * previous pattern, are preserved.
   *
   * @param string $text
   *   The initial text.
   *
   * @return string
   *   The text with non-translatable parts wrapped.
   */
  public function wrap(string $text): string {
    foreach ($this->patterns as $regexp) {
      preg_match_all(self::WRAPPED, $text, $matches, PREG_OFFSET_CAPTURE);

      $translatable_parts = $non_translatable_parts = [];
      $index = $offset = 0;

      foreach ($matches[0] as [$non_translatable, $position]) {
        $translatable = substr($text, $offset, $position - $offset);
        $translatable_parts[$index++] = $translatable;
        $offset += strlen($translatable);

        $non_translatable_parts[$index++] = $non_translatable;
        $offset += strlen($non_translatable);
      }
      if ($offset < strlen($text)) {
        $translatable_parts[$index] = substr($text, $offset);
      }

      $regexp = '/' . str_replace('/', '\/', $regexp) . '/';
      foreach ($translatable_parts as &$translatable) {
        if ($this->clean) {
          // An opening [notranslate] tag without a matching closing tag, or
          // the other way around, makes eTranslation reject the translation.
          $translatable = str_replace(['[notranslate]', '[/notranslate]'], '', $translatable);
        }

        $wrapped = preg_replace($regexp, '[notranslate]${0}[/notranslate]', $translatable);
        if ($wrapped !== NULL) {
          $translatable = $wrapped;
        }
      }
      unset($translatable);

      $parts = $translatable_parts + $non_translatable_parts;
      ksort($parts);
      $text = implode('', $parts);
    }

    return $text;
  }

  /**
   * Replaces the non-translatable parts of a text with placeholders.
   *
   * @param string $text
   *   The text to be sent for translation.
   *
   * @return string
   *   The text with each non-translatable part replaced by a placeholder.
   */
  public function mask(string $text): string {
    $index = 0;
    return preg_replace_callback(
      self::WRAPPED,
      function () use (&$index): string {
        return sprintf(self::PLACEHOLDER, ++$index);
      },
      $this->wrap($text),
    );
  }

  /**
   * Restores the non-translatable parts of a source text in its translation.
   *
   * @param string $source
   *   The source text, as passed to ::mask().
   * @param string $translation
   *   The translation received from eTranslation.
   * @param list<int> $unresolved
   *   Receives the placeholders that could not be restored, and the parts of
   *   the source whose placeholder is missing from the translation.
   *
   * @return string
   *   The translation, with non-translatable parts restored and any remaining
   *   [notranslate] tags removed.
   */
  public function unmask(string $source, string $translation, ?array &$unresolved = []): string {
    preg_match_all(self::WRAPPED, $this->wrap($source), $matches);
    $parts = [];
    foreach ($matches[0] as $index => $part) {
      $parts[$index + 1] = substr($part, strlen('[notranslate]'), -strlen('[/notranslate]'));
    }

    $restored = [];
    $unresolved = [];
    $translation = preg_replace_callback(
      self::PLACEHOLDER_PATTERN,
      function (array $match) use ($parts, &$restored, &$unresolved): string {
        $index = (int) $match[1];
        if (!isset($parts[$index])) {
          $unresolved[] = $index;
          return $match[0];
        }
        $restored[$index] = TRUE;
        return $parts[$index];
      },
      $translation,
    );
    $unresolved = array_merge($unresolved, array_keys(array_diff_key($parts, $restored)));

    return str_replace(['[notranslate]', '[/notranslate]'], '', $translation);
  }

}
