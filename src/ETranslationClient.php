<?php

declare(strict_types=1);

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin client for the eTranslation REST v2 API.
 */
final class ETranslationClient {

  private Client $http;

  public function __construct(?Client $http = NULL) {
    $this->http = $http ?? new Client([
      'base_uri' => Config::ENDPOINT,
      RequestOptions::TIMEOUT => 30,
      // Keep 4xx/5xx responses so their raw body can be stored.
      RequestOptions::HTTP_ERRORS => FALSE,
    ]);
  }

  /**
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function askTranslate(array $payload): ResponseInterface {
    return $this->http->request('POST', 'etranslation/api/askTranslate', [
      RequestOptions::AUTH => [Config::username(), Config::password(), 'basic'],
      RequestOptions::JSON => $payload,
    ]);
  }

  /**
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getDomains(): ResponseInterface {
    return $this->http->request('GET', 'etranslation/api/getDomains', [
      RequestOptions::AUTH => [Config::username(), Config::password(), 'basic'],
    ]);
  }

  /**
   * Returns domains keyed by name (cached in var/ for an hour), or [] on failure.
   *
   * @return array<string, array{domain?: string, languagePairs?: list<string>}>
   */
  public function domains(bool $refresh = FALSE): array {
    $cacheFile = self::varDir() . '/domains.json';
    if (!$refresh && is_file($cacheFile) && filemtime($cacheFile) > time() - 3600) {
      $data = json_decode((string) file_get_contents($cacheFile), TRUE);
      if (is_array($data)) {
        return $data;
      }
    }

    $raw = $this->getDomainsRaw();
    $data = json_decode($raw['body'], TRUE);
    if ($raw['status'] !== 200 || !is_array($data)) {
      return [];
    }

    // v2 returns a list of {domain, languagePairs}; normalize to keyed by name.
    if ($data !== [] && isset($data[0]['domain'])) {
      $data = array_column($data, NULL, 'domain');
    }
    file_put_contents($cacheFile, json_encode($data));
    return $data;
  }

  /**
   * @return array{status: int, body: string, error?: string}
   */
  public function getDomainsRaw(): array {
    try {
      $response = $this->getDomains();
      return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody()];
    }
    catch (\Throwable $e) {
      return ['status' => 0, 'body' => '', 'error' => $e->getMessage()];
    }
  }

  private static function varDir(): string {
    $dir = __DIR__ . '/../var';
    if (!is_dir($dir)) {
      mkdir($dir, 0775, TRUE);
    }
    return $dir;
  }

}
