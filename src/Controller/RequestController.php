<?php

declare(strict_types=1);

namespace App\Controller;

use App\Db;
use App\ETranslationClient;
use App\Hash;
use App\Languages;
use App\View;

/**
 * Lists and shows stored requests; debug page for getDomains.
 */
final class RequestController {

  public function list(): void {
    // ?ids=1,2 narrows the list, e.g. right after a "Both" send.
    $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')))));
    View::render('list', ['requests' => self::recent(200, $ids), 'ids' => $ids, 'nav' => 'results', 'wide' => TRUE]);
  }

  /**
   * Latest requests with a callback count and the latest decoded translation.
   */
  public static function recent(int $limit, array $ids = []): array {
    $where = $ids ? 'WHERE r.id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
    return Db::all(
      'SELECT r.id, r.created_at, r.source_lang, r.target_lang, r.domain, r.payload_type, r.status, r.notranslate_mode,
              LEFT(r.original_text, 120) AS excerpt,
              (SELECT COUNT(*) FROM translation_callback c WHERE c.translation_request_id = r.id) AS callbacks,
              (SELECT LEFT(c.translated_text, 120) FROM translation_callback c
                WHERE c.translation_request_id = r.id AND c.translated_text IS NOT NULL
                ORDER BY c.id DESC LIMIT 1) AS translation
       FROM translation_request r ' . $where . ' ORDER BY r.id DESC LIMIT ' . (int) $limit
    );
  }

  public function show(int $id): void {
    $request = Db::find('translation_request', $id);
    if ($request === NULL) {
      View::render('notfound', [], 404);
      return;
    }
    $prev = Db::all('SELECT MAX(id) AS id FROM translation_request WHERE id < ?', [$id])[0]['id'] ?? NULL;
    $next = Db::all('SELECT MIN(id) AS id FROM translation_request WHERE id > ?', [$id])[0]['id'] ?? NULL;
    View::render('detail', [
      'nav' => 'results',
      'prev' => $prev,
      'next' => $next,
      'r' => $request,
      'hash' => Hash::generate($id),
      'callbacks' => Db::all('SELECT * FROM translation_callback WHERE translation_request_id = ? ORDER BY id', [$id]),
    ]);
  }

  public function domains(): void {
    $client = new ETranslationClient();
    $raw = $client->getDomainsRaw();
    if ($raw['status'] === 200) {
      // Refresh the cache used by the send form.
      $client->domains(TRUE);
    }
    $decoded = json_decode($raw['body'], TRUE);
    $list = is_array($decoded) ? (isset($decoded[0]['domain']) ? $decoded : array_values($decoded)) : [];

    // One row per domain + language pair; suffixes such as "LLM" or "LLM-GLS"
    // are grouped as variants of that pair.
    $grouped = [];
    $summary = [];
    foreach ($list as $info) {
      $domain = (string) ($info['domain'] ?? '?');
      $sources = $targets = [];
      foreach ($info['languagePairs'] ?? [] as $pair) {
        $parts = explode('-', strtoupper((string) $pair));
        if (count($parts) < 2) {
          continue;
        }
        [$src, $tgt] = $parts;
        $variant = count($parts) > 2 ? implode('-', array_slice($parts, 2)) : 'standard';
        $grouped["$domain|$src|$tgt"]['variants'][$variant] = TRUE;
        $grouped["$domain|$src|$tgt"] += ['domain' => $domain, 'src' => $src, 'tgt' => $tgt];
        $sources[$src] = $targets[$tgt] = TRUE;
      }
      $summary[$domain] = [
        'raw_pairs' => count($info['languagePairs'] ?? []),
        'sources' => count($sources),
        'targets' => count($targets),
      ];
    }

    $rows = [];
    $names = [];
    foreach ($grouped as $g) {
      foreach ([$g['src'], $g['tgt']] as $code) {
        $names[$code] ??= Languages::name(strtolower($code));
      }
      $rows[] = [$g['domain'], $names[$g['src']] . ' (' . $g['src'] . ')', $names[$g['tgt']] . ' (' . $g['tgt'] . ')', implode(', ', array_keys($g['variants']))];
    }

    View::render('domains', ['raw' => $raw, 'summary' => $summary, 'rows' => $rows, 'nav' => 'domains']);
  }

}
