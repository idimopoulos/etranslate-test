<?php

declare(strict_types=1);

namespace App;

/**
 * Turns a raw success-callback body into the stored fields.
 *
 * Used by the callback endpoint and by bin/reprocess.php, so stored callbacks
 * can be recomputed after the decoding logic changes.
 */
final class CallbackAnalyzer {

  /**
   * @param array $request
   *   The translation_request row.
   * @param string $raw
   *   The raw callback body.
   *
   * @return array
   *   translation_callback columns (except id, kind, headers, timestamps).
   */
  /**
   * Request status for analyzed callback fields.
   *
   * "translated" only when the module's own output is usable AND equals the
   * correctly decoded result; otherwise the module would save something
   * broken (garbage, raw base64, unrestored placeholders).
   */
  public static function status(array $fields): string {
    $ok = $fields['translated_text'] !== NULL
      && empty($fields['unresolved'])
      && ($fields['fixed_text'] === NULL || $fields['fixed_text'] === $fields['translated_text']);
    return $ok ? 'translated' : 'unusable';
  }

  public static function analyze(array $request, string $raw): array {
    $body = ResponseDecoder::decodeBody($raw);
    $notes = [];

    if ($body === []) {
      $notes[] = 'Body is not a JSON object.';
    }
    if (isset($body['externalReference']) && (string) $body['externalReference'] !== (string) $request['external_reference']) {
      $notes[] = sprintf('externalReference mismatch: got "%s", expected "%s".', $body['externalReference'], $request['external_reference']);
    }

    // What eTranslation actually returned: base64 for documents, plain for
    // textToTranslate (v2 puts both in "result"; v1 used "translatedText").
    $sentAsDocument = $request['payload_type'] !== 'text';
    $decodedResult = ResponseDecoder::decodeResult($body, $sentAsDocument);
    if ($sentAsDocument && isset($body['result']) && $decodedResult === NULL) {
      $notes[] = '"result" of a document is not valid base64-encoded UTF-8.';
    }

    $translation = ResponseDecoder::extractTranslation($body);
    $final = NULL;
    $unresolved = [];
    $fixed = NULL;
    $fixedUnresolved = [];
    if ($translation === '') {
      $notes[] = 'No translatedText/result in body.';
    }
    else {
      // Old rows have no source_text; their sent text is the closest thing.
      $source = $request['source_text'] ?? $request['original_text'];
      $moduleDecodes = ResponseDecoder::moduleDecodes($source);
      if ($moduleDecodes !== $sentAsDocument) {
        $notes[] = sprintf(
          'Module decode mismatch: the text was sent as %s, so the reply is %s, but TranslationResponseProcessor %s because the source text %s.',
          $sentAsDocument ? "a {$request['payload_type']} document" : 'textToTranslate',
          $sentAsDocument ? 'base64' : 'plain text',
          $moduleDecodes ? 'base64-decodes it' : 'does not decode it',
          $moduleDecodes ? 'is HTML or longer than 1000 characters' : 'is plain and short',
        );
      }
      $final = ResponseDecoder::finalTranslation(
        $request['notranslate_mode'] ?? 'wrap',
        $source,
        (string) ($request['notranslate_patterns'] ?? Config::DEFAULT_NOTRANSLATE_PATTERNS),
        (bool) ($request['notranslate_clean'] ?? TRUE),
        $translation,
        $unresolved,
      );
      if ($unresolved) {
        $notes[] = 'Could not restore placeholders: NTPH' . implode(', NTPH', $unresolved) . '.';
      }
      // Same post-processing, but on the reply decoded by what was sent.
      if ($decodedResult !== NULL) {
        $fixed = ResponseDecoder::restore(
          $request['notranslate_mode'] ?? 'wrap',
          $source,
          (string) ($request['notranslate_patterns'] ?? Config::DEFAULT_NOTRANSLATE_PATTERNS),
          (bool) ($request['notranslate_clean'] ?? TRUE),
          $decodedResult,
          $fixedUnresolved,
        );
      }
      if (!mb_check_encoding($final, 'UTF-8')) {
        $notes[] = 'The module output is not valid UTF-8 (it base64-decoded a value that was not base64).';
        $final = NULL;
      }
    }

    return [
      'request_id' => isset($body['requestId']) ? (string) $body['requestId'] : NULL,
      'target_language' => $body['targetLanguage'] ?? NULL,
      'external_reference' => isset($body['externalReference']) ? (string) $body['externalReference'] : NULL,
      'decoded_result' => $decodedResult,
      'translated_text' => $final,
      'unresolved' => $unresolved ? implode(',', $unresolved) : NULL,
      'fixed_text' => $fixed,
      'fixed_unresolved' => $fixedUnresolved ? implode(',', $fixedUnresolved) : NULL,
      'note' => $notes ? implode(' ', $notes) : NULL,
    ];
  }

}
