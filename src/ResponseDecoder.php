<?php

declare(strict_types=1);

namespace App;

/**
 * Decodes callback bodies like the module's CallbackController and
 * TranslationResponseProcessor.
 */
final class ResponseDecoder {

  /**
   * Decodes a JSON body; returns [] when it is not a JSON object.
   */
  public static function decodeBody(string $raw): array {
    $body = json_decode($raw, TRUE);
    return is_array($body) ? $body : [];
  }

  /**
   * ETranslationTranslatorRestV2::extractBody().
   */
  public static function extractTranslation(array $body): string {
    return (string) ($body['translatedText'] ?? $body['result'] ?? '');
  }

  /**
   * The callback value decoded on its own terms, before marker handling.
   *
   * Decided by what was SENT: a document comes back base64-encoded, a
   * textToTranslate snippet comes back as plain text. eTranslation v2 puts
   * both in "result" (with outputFormat), v1 used "translatedText" for text.
   * Unlike moduleDecode(), this does not look at the source text, so it shows
   * exactly what came back.
   *
   * @return string|null
   *   The decoded value, or NULL when there is nothing to decode or a
   *   document "result" is not valid base64 / UTF-8.
   */
  public static function decodeResult(array $body, bool $sentAsDocument): ?string {
    $value = $body['translatedText'] ?? $body['result'] ?? NULL;
    if (!is_string($value)) {
      return NULL;
    }
    if (!$sentAsDocument || isset($body['translatedText'])) {
      return $value;
    }
    $decoded = base64_decode($value, TRUE);
    return $decoded !== FALSE && mb_check_encoding($decoded, 'UTF-8') ? $decoded : NULL;
  }

  /**
   * Whether TranslationResponseProcessor::process() base64-decodes the reply.
   *
   * The module decides on the SOURCE field text, not on what was sent.
   */
  public static function moduleDecodes(string $sourceText): bool {
    return PayloadBuilder::isHtml($sourceText) || !PayloadBuilder::canTextToTranslateBeUsed($sourceText);
  }

  /**
   * TranslationResponseProcessor::process() decoding, before marker handling.
   */
  public static function moduleDecode(string $sourceText, string $response): string {
    // The module uses a non-strict base64_decode().
    return self::moduleDecodes($sourceText) ? (string) base64_decode($response) : $response;
  }

  /**
   * Full module post-processing for the given non-translatable mode.
   *
   * @param list<int>|null $unresolved
   *   Receives the placeholders unmask() could not restore (placeholders mode).
   */
  public static function finalTranslation(string $mode, string $sourceText, string $patterns, bool $clean, string $response, ?array &$unresolved = []): string {
    return self::restore($mode, $sourceText, $patterns, $clean, self::moduleDecode($sourceText, $response), $unresolved);
  }

  /**
   * Marker handling on an already decoded reply: unmask() or strip the tags.
   *
   * Used with the correctly decoded result to show the outcome of the
   * proposed fix: process() deciding on base64 from the text that was SENT
   * (mask($source) / wrap($source)), like buildPayload() does.
   *
   * @param list<int>|null $unresolved
   */
  public static function restore(string $mode, string $sourceText, string $patterns, bool $clean, string $text, ?array &$unresolved = []): string {
    $unresolved = [];
    if ($mode === 'placeholders') {
      return (new NonTranslatableParts(['patterns' => $patterns, 'clean' => $clean]))->unmask($sourceText, $text, $unresolved);
    }
    // Current release: just remove the tags.
    return str_replace(['[notranslate]', '[/notranslate]'], '', $text);
  }

}
