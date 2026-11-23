<?php

declare(strict_types=1);

namespace App\Controller;

use App\CallbackAnalyzer;
use App\Db;
use App\Hash;
use App\ResponseDecoder;

/**
 * Receives eTranslation success / error callbacks.
 *
 * Every authenticated call is stored raw, even when the body is unexpected.
 */
final class CallbackController {

  public function success(int $id, string $hash): void {
    $request = $this->authorize($id, $hash);
    if ($request === NULL) {
      return;
    }

    $raw = (string) file_get_contents('php://input');
    $fields = CallbackAnalyzer::analyze($request, $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) {
      $fields['note'] = trim(($fields['note'] ?? '') . ' Raw body was not valid UTF-8 and is stored base64-encoded.');
      $raw = 'base64:' . base64_encode($raw);
    }

    Db::insert('translation_callback', [
      'translation_request_id' => $id,
      'kind' => 'success',
      'raw_headers' => $this->headers(),
      'raw_body' => $raw,
    ] + $fields);

    // A callback arrived either way; "unusable" means the module would not get a valid translation out of it.
    Db::update('translation_request', $id, ['status' => CallbackAnalyzer::status($fields)]);

    http_response_code(204);
  }

  public function error(int $id, string $hash): void {
    if ($this->authorize($id, $hash) === NULL) {
      return;
    }

    $raw = (string) file_get_contents('php://input');
    $body = ResponseDecoder::decodeBody($raw);

    Db::insert('translation_callback', [
      'translation_request_id' => $id,
      'kind' => 'error',
      'raw_headers' => $this->headers(),
      'raw_body' => $raw,
      'request_id' => isset($body['requestId']) ? (string) $body['requestId'] : NULL,
      'external_reference' => isset($body['externalReference']) ? (string) $body['externalReference'] : NULL,
      'error_code' => isset($body['errorCode']) ? (string) $body['errorCode'] : NULL,
      'error_message' => isset($body['errorMessage']) ? (string) $body['errorMessage'] : NULL,
      'note' => $body === [] ? 'Body is not a JSON object.' : NULL,
    ]);

    Db::update('translation_request', $id, ['status' => 'error']);
    http_response_code(204);
  }

  private function authorize(int $id, string $hash): ?array {
    if (!Hash::verify($id, $hash)) {
      http_response_code(403);
      return NULL;
    }
    $request = Db::find('translation_request', $id);
    if ($request === NULL) {
      http_response_code(404);
    }
    return $request;
  }

  private function headers(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    unset($headers['Authorization'], $headers['Cookie']);
    return json_encode($headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

}
