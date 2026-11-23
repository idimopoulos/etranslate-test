<?php

/**
 * Recomputes the derived fields of stored success callbacks from their raw body.
 *
 * Usage: ddev exec php bin/reprocess.php [request id ...]   (no ids = all)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\CallbackAnalyzer;
use App\Db;

$ids = array_map('intval', array_slice($argv, 1));
$where = $ids ? 'AND c.translation_request_id IN (' . implode(',', $ids) . ')' : '';
$rows = Db::all("SELECT c.id, c.translation_request_id, c.raw_body FROM translation_callback c WHERE c.kind = 'success' {$where} ORDER BY c.id");

foreach ($rows as $row) {
  $request = Db::find('translation_request', (int) $row['translation_request_id']);
  if ($request === NULL || str_starts_with((string) $row['raw_body'], 'base64:')) {
    continue;
  }
  $fields = CallbackAnalyzer::analyze($request, (string) $row['raw_body']);
  Db::update('translation_callback', (int) $row['id'], $fields);
  $status = CallbackAnalyzer::status($fields);
  Db::update('translation_request', (int) $request['id'], ['status' => $status]);
  printf("callback #%d (request #%d): %s\n", $row['id'], $request['id'], $fields['note'] ?? 'ok');
}
