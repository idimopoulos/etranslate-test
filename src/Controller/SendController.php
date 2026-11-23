<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config;
use App\Db;
use App\ETranslationClient;
use App\Hash;
use App\Languages;
use App\NonTranslatableParts;
use App\PayloadBuilder;
use App\View;

/**
 * Send form and submission to askTranslate.
 */
final class SendController {

  public function __construct(private readonly ETranslationClient $client = new ETranslationClient()) {}

  public function form(array $errors = [], array $values = []): void {
    $domains = Config::hasCredentials() ? $this->client->domains() : [];
    $pairs = Languages::pairsByDomain($domains);
    View::render('send', [
      'pairs' => $pairs,
      'languageNames' => Languages::names($pairs),
      'nav' => 'send',
      'recent' => RequestController::recent(10),
      'domains' => $domains,
      'errors' => $errors,
      'values' => $values + [
        'source_lang' => 'en',
        'target_lang' => 'fr',
        'domain' => 'SPD',
        'text' => '',
        'patterns' => Config::DEFAULT_NOTRANSLATE_PATTERNS,
        'clean' => Config::DEFAULT_NOTRANSLATE_CLEAN ? '1' : '',
        'mode' => 'wrap',
        'callback_base' => Config::callbackBaseUrl(),
      ],
    ], $errors ? 422 : 200);
  }

  public function submit(): void {
    $values = [
      'source_lang' => strtolower(trim((string) ($_POST['source_lang'] ?? ''))),
      'target_lang' => strtolower(trim((string) ($_POST['target_lang'] ?? ''))),
      'domain' => trim((string) ($_POST['domain'] ?? '')),
      'text' => (string) ($_POST['text'] ?? ''),
      'patterns' => (string) ($_POST['patterns'] ?? ''),
      'clean' => isset($_POST['clean']) ? '1' : '',
      'mode' => in_array($_POST['mode'] ?? '', ['wrap', 'placeholders', 'both'], TRUE) ? $_POST['mode'] : 'wrap',
      'callback_base' => rtrim(trim((string) ($_POST['callback_base'] ?? '')), '/'),
    ];

    $errors = [];
    if (!Config::hasCredentials()) {
      $errors[] = 'ETRANSLATION_USERNAME / ETRANSLATION_PASSWORD are not set in .ddev/.env.';
    }
    foreach (['source_lang', 'target_lang', 'domain', 'text'] as $field) {
      if ($values[$field] === '') {
        $errors[] = "Field '{$field}' is required.";
      }
    }
    if (!filter_var($values['callback_base'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#', $values['callback_base'])) {
      $errors[] = 'Callback base URL must be an absolute http(s) URL reachable by eTranslation (e.g. the `ddev share` URL).';
    }
    // When getDomains is available, reject pairs the domain does not support.
    $pairs = Config::hasCredentials() ? Languages::pairsByDomain($this->client->domains()) : [];
    if (!$errors && isset($pairs[$values['domain']]) && !in_array($values['target_lang'], $pairs[$values['domain']][$values['source_lang']] ?? [], TRUE)) {
      $errors[] = sprintf('Domain %s does not support %s → %s.', $values['domain'], strtoupper($values['source_lang']), strtoupper($values['target_lang']));
    }
    if ($errors) {
      $this->form($errors, $values);
      return;
    }

    $modes = $values['mode'] === 'both' ? ['wrap', 'placeholders'] : [$values['mode']];
    $ids = array_map(fn(string $mode): int => $this->send($values, $mode), $modes);

    View::redirect(count($ids) === 1 ? "/requests/{$ids[0]}" : '/requests?ids=' . implode(',', $ids));
  }

  /**
   * Sends one request in the given non-translatable mode; returns its row id.
   *
   * - wrap: current release. Matches are wrapped in [notranslate] tags.
   * - placeholders: branch notranslate-placeholders. Each wrapped match is
   *   replaced by [notranslate]NTPHn[/notranslate] and restored on receipt.
   */
  private function send(array $values, string $mode): int {
    $parts = new NonTranslatableParts(['patterns' => $values['patterns'], 'clean' => $values['clean'] === '1']);
    $text = $mode === 'placeholders' ? $parts->mask($values['text']) : $parts->wrap($values['text']);

    $id = Db::insert('translation_request', [
      'source_lang' => $values['source_lang'],
      'target_lang' => $values['target_lang'],
      'domain' => $values['domain'],
      'source_text' => $values['text'],
      'notranslate_mode' => $mode,
      'notranslate_patterns' => $values['patterns'],
      'notranslate_clean' => $values['clean'] === '1' ? 1 : 0,
      // The text exactly as the module would send it.
      'original_text' => $text,
      'payload_type' => PayloadBuilder::payloadType($text),
    ]);

    $hash = Hash::generate($id);
    $urls = [
      'callback' => "{$values['callback_base']}/callback/{$id}/{$hash}",
      'error' => "{$values['callback_base']}/error/{$id}/{$hash}",
    ];
    $reference = (string) $id;
    $payload = PayloadBuilder::build($reference, $text, $values['source_lang'], $values['target_lang'], $values['domain'], $urls);

    $update = [
      'external_reference' => $reference,
      'callback_url' => $urls['callback'],
      'error_url' => $urls['error'],
      'sent_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];

    try {
      $response = $this->client->askTranslate($payload);
      $body = (string) $response->getBody();
      $update['http_status'] = $response->getStatusCode();
      $update['raw_response'] = $body;
      // v2 replies with {"requestId": N}; v1 replied with a bare number.
      // A positive id means accepted, a negative one is an eTranslation error code.
      $json = json_decode($body, TRUE);
      $requestId = is_array($json) ? (string) ($json['requestId'] ?? '') : trim($body);
      $ok = $response->getStatusCode() < 300 && is_numeric($requestId) && (int) $requestId > 0;
      $update['etranslation_request_id'] = is_numeric($requestId) ? $requestId : NULL;
      $update['status'] = $ok ? 'sent' : 'failed';
    }
    catch (\Throwable $e) {
      $update['raw_response'] = get_class($e) . ': ' . $e->getMessage();
      $update['status'] = 'failed';
    }

    Db::update('translation_request', $id, $update);
    return $id;
  }

}
