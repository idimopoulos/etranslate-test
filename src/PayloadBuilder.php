<?php

declare(strict_types=1);

namespace App;

/**
 * Builds the askTranslate body exactly like tmgmt_ec_etranslation.
 *
 * Ported from ETranslationTranslatorRestV2::buildPayload(),
 * ::preserveNonTranslatables() and TextValidator.
 */
final class PayloadBuilder {

  /**
   * TextValidatorInterface::MAX_TEXT_TO_TRANSLATE_LENGTH.
   */
  public const MAX_TEXT_TO_TRANSLATE_LENGTH = 1000;

  public static function isHtml(string $text): bool {
    return strip_tags($text) !== $text;
  }

  public static function canTextToTranslateBeUsed(string $text): bool {
    return mb_strlen($text) <= self::MAX_TEXT_TO_TRANSLATE_LENGTH;
  }

  /**
   * Returns which payload type the module would choose: text, html or txt.
   */
  public static function payloadType(string $text): string {
    if (self::isHtml($text)) {
      return 'html';
    }
    if (!self::canTextToTranslateBeUsed($text)) {
      return 'txt';
    }
    return 'text';
  }

  /**
   * @param array{callback: string, error: string} $urls
   */
  public static function build(string $reference, string $text, string $source, string $target, string $domain, array $urls): array {
    $data = [
      'sourceLanguage' => strtoupper($source),
      'targetLanguages' => [strtoupper($target)],
      'domain' => $domain,
      'callerInformation' => [
        'username' => Config::caller(),
        'externalReference' => mb_substr($reference, 0, 200),
      ],
      'deliveries' => ['http' => $urls['callback']],
      'notifications' => ['failure' => ['http' => $urls['error']]],
    ];

    $type = self::payloadType($text);
    if ($type === 'text') {
      $data['textToTranslate'] = $text;
    }
    else {
      $data['documentToTranslate'] = [
        'document' => ['content' => base64_encode($text), 'format' => $type],
      ];
    }

    return $data;
  }

  /**
   * Wraps regex matches in [notranslate] tags (current release behaviour).
   */
  public static function preserveNonTranslatables(string $text, string $patterns, bool $clean): string {
    return (new NonTranslatableParts(['patterns' => $patterns, 'clean' => $clean]))->wrap($text);
  }

}
