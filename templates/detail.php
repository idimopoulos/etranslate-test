<?php
  $autoRefresh = $r['status'] === 'sent';
  $latest = null;
  $latestFixed = null;
  foreach ($callbacks as $c) {
    if ($c['kind'] === 'success' && $c['translated_text'] !== null) { $latest = $c; }
    if ($c['kind'] === 'success' && ($c['fixed_text'] ?? null) !== null) { $latestFixed = $c; }
  }
  $fixDiffers = $latestFixed && (!$latest || $latestFixed['fixed_text'] !== $latest['translated_text']);
?>
<div class="crumbs"><a href="/">Home</a> › <a href="/requests">Results</a> › #<?= (int) $r['id'] ?></div>
<div class="pager">
  <span><?php if ($prev): ?><a href="/requests/<?= (int) $prev ?>">← #<?= (int) $prev ?> (older)</a><?php endif; ?></span>
  <span><?php if ($next): ?><a href="/requests/<?= (int) $next ?>">#<?= (int) $next ?> (newer) →</a><?php endif; ?></span>
</div>
<h1>Result #<?= (int) $r['id'] ?> <span class="s-<?= e($r['status']) ?>" style="font-size:18px">(<?= e($r['status']) ?>)</span></h1>
<p class="muted"><?= e(strtoupper($r['source_lang']) . ' → ' . strtoupper($r['target_lang'])) ?> · domain <?= e($r['domain']) ?> · sent as <code><?= e($r['payload_type']) ?></code> · mode <span class="badge mode-<?= e($r['notranslate_mode']) ?>"><?= e($r['notranslate_mode'] === 'placeholders' ? 'placeholders (branch)' : 'wrap (current release)') ?></span> · <?= e($r['created_at']) ?></p>

<?php if ($r['status'] === 'sent'): ?>
  <div class="banner">Accepted by eTranslation (request id <?= e($r['etranslation_request_id']) ?>). Waiting for the callback; this page refreshes every 10 seconds.</div>
<?php elseif ($r['status'] === 'failed'): ?>
  <div class="banner bad">eTranslation did not accept the request. See the raw reply under <a href="#request">Request sent</a>.</div>
<?php elseif ($r['status'] === 'error'): ?>
  <div class="banner bad">eTranslation sent an error callback. See <a href="#callbacks">Raw callbacks</a>.</div>
<?php elseif ($r['status'] === 'unusable'): ?>
  <div class="banner bad">eTranslation replied (<?= count($callbacks) ?> callback<?= count($callbacks) === 1 ? '' : 's' ?>), but the module could not get a usable translation out of it. The note under <a href="#callbacks">Raw callbacks</a> explains why, and the decoded result shows what eTranslation actually sent back.</div>
<?php elseif ($r['status'] === 'translated'): ?>
  <div class="banner ok">Translation received (<?= count($callbacks) ?> callback<?= count($callbacks) === 1 ? '' : 's' ?>).</div>
<?php endif; ?>

<p>Jump to: <a href="#result">Result</a> · <a href="#callbacks">Raw callbacks (<?= count($callbacks) ?>)</a> · <a href="#request">Request sent</a></p>

<div class="card" id="result">
  <div class="pair">
    <div>
      <h3>Original (<?= e(strtoupper($r['source_lang'])) ?>)</h3>
      <pre><?= e($r['source_text'] ?? $r['original_text']) ?></pre>
    </div>
    <div>
      <?php
        // The module's own output for the newest success callback, even when broken.
        $moduleCb = null;
        foreach ($callbacks as $c) { if ($c['kind'] === 'success') { $moduleCb = $c; } }
        $broken = $r['status'] === 'unusable';
      ?>
      <h3>Module output (<?= e(strtoupper($r['target_lang'])) ?>) <span class="muted" style="font-weight:400;font-size:13px">what Joinup would save today</span></h3>
      <?php if ($moduleCb === null): ?>
        <pre class="muted"><?= $callbacks ? 'Only error callbacks received.' : 'Not received yet.' ?></pre>
      <?php elseif ($moduleCb['translated_text'] === null): ?>
        <pre class="broken">✗ Binary garbage (the module base64-decoded a plain-text reply; not even valid UTF-8).</pre>
      <?php else: ?>
        <pre class="<?= $broken ? 'broken' : '' ?>"><?= e($moduleCb['translated_text']) ?></pre>
      <?php endif; ?>
      <?php if ($broken && $moduleCb): ?>
        <p class="s-error"><strong>✗ Broken:</strong> <?= e($moduleCb['note'] ?? '') ?></p>
      <?php endif; ?>
      <?php if ($moduleCb && ($moduleCb['fixed_text'] ?? null) !== null && $moduleCb['fixed_text'] !== $moduleCb['translated_text']): ?>
        <h3>With decode fix <span class="muted" style="font-weight:400;font-size:13px">not in the module yet: decode by what was sent</span></h3>
        <pre class="fixed"><?= e($moduleCb['fixed_text']) ?></pre>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($latest && !$broken && $r['payload_type'] === 'html'): ?>
    <h3 style="font-size:14px;color:var(--muted)">Rendered preview (sandboxed)</h3>
    <iframe sandbox srcdoc="<?= e($latest['translated_text']) ?>"></iframe>
  <?php endif; ?>
</div>

<h2 id="callbacks">Raw callbacks (<?= count($callbacks) ?>)</h2>
<?php if (!$callbacks): ?><p class="hint">None received yet.</p><?php endif; ?>
<?php foreach ($callbacks as $c): ?>
  <div class="card">
    <h3 style="margin-top:0">#<?= (int) $c['id'] ?> · <span class="<?= $c['kind'] === 'error' ? 's-error' : 's-translated' ?>"><?= e($c['kind']) ?></span> · <?= e($c['received_at']) ?></h3>
    <dl>
      <dt>requestId</dt><dd><?= e($c['request_id']) ?></dd>
      <dt>targetLanguage</dt><dd><?= e($c['target_language']) ?></dd>
      <dt>externalReference</dt><dd><?= e($c['external_reference']) ?></dd>
      <?php if ($c['kind'] === 'error'): ?>
        <dt>errorCode</dt><dd><?= e($c['error_code']) ?></dd>
        <dt>errorMessage</dt><dd><?= e($c['error_message']) ?></dd>
      <?php endif; ?>
      <?php if (!empty($c['unresolved'])): ?><dt>Unresolved</dt><dd class="s-error">NTPH<?= e(str_replace(',', ', NTPH', $c['unresolved'])) ?></dd><?php endif; ?>
      <?php if ($c['note']): ?><dt>Note</dt><dd class="s-error"><?= e($c['note']) ?></dd><?php endif; ?>
    </dl>
    <h4>Raw body</h4>
    <pre><?= e(pretty($c['raw_body'])) ?></pre>
    <?php if ($c['kind'] === 'success'): ?>
      <h4>Decoded result <span class="muted" style="font-weight:400">(base64-decoded for documents, as is for <code>textToTranslate</code>; [notranslate] tags and placeholders kept)</span></h4>
      <?php if ($c['decoded_result'] !== null): ?>
        <pre><?= e($c['decoded_result']) ?></pre>
      <?php else: ?>
        <pre class="muted">Nothing to decode.</pre>
      <?php endif; ?>
      <h4>Final translation <span class="muted" style="font-weight:400">(module logic: decode, then <?= $r['notranslate_mode'] === 'placeholders' ? 'unmask() from the source' : 'strip [notranslate]' ?>)</span></h4>
      <?php if ($c['translated_text'] !== null): ?>
        <pre><?= e($c['translated_text']) ?></pre>
      <?php else: ?>
        <pre class="muted">None.</pre>
      <?php endif; ?>
      <h4>Final translation, with decode fix <span class="muted" style="font-weight:400">(decoded result above, then <?= $r['notranslate_mode'] === 'placeholders' ? 'unmask() from the source' : 'strip [notranslate]' ?>; i.e. process() decides base64 from the text that was sent)</span>
        <?php if (($c['fixed_text'] ?? null) !== null): ?><?= $c['fixed_text'] === $c['translated_text'] ? '<span class="s-translated" style="font-weight:400">· same as module</span>' : '<span class="s-error" style="font-weight:400">· differs from module</span>' ?><?php endif; ?></h4>
      <?php if (($c['fixed_text'] ?? null) !== null): ?>
        <pre><?= e($c['fixed_text']) ?></pre>
        <?php if (!empty($c['fixed_unresolved'])): ?><p class="s-error">Unresolved with fix: NTPH<?= e(str_replace(',', ', NTPH', $c['fixed_unresolved'])) ?></p><?php endif; ?>
      <?php else: ?>
        <pre class="muted">None.</pre>
      <?php endif; ?>
    <?php endif; ?>
    <details><summary>Raw headers</summary><pre><?= e(pretty($c['raw_headers'])) ?></pre></details>
  </div>
<?php endforeach; ?>

<div class="card" id="request">
  <h2 style="margin-top:0">Request sent</h2>
  <dl>
    <dt>Created</dt><dd><?= e($r['created_at']) ?></dd>
    <dt>Pair / domain</dt><dd><?= e($r['source_lang'] . ' → ' . $r['target_lang'] . ' / ' . $r['domain']) ?></dd>
    <dt>Payload type</dt><dd><?= e($r['payload_type']) ?></dd>
    <dt>External reference</dt><dd><?= e($r['external_reference']) ?></dd>
    <dt>eTranslation request id</dt><dd><?= e($r['etranslation_request_id']) ?></dd>
    <dt>HTTP status</dt><dd><?= e($r['http_status']) ?></dd>
    <dt>Callback URL</dt><dd><?= e($r['callback_url']) ?></dd>
    <dt>Error URL</dt><dd><?= e($r['error_url']) ?></dd>
    <dt>Hash</dt><dd><code><?= e($hash) ?></code></dd>
  </dl>
  <h3>Text as sent (<?= $r['notranslate_mode'] === 'placeholders' ? 'after wrap + mask()' : 'after wrap()' ?>)</h3>
  <pre><?= e($r['original_text']) ?></pre>
  <h3>Sent payload (askTranslate body)</h3>
  <pre><?= e(pretty($r['sent_payload'])) ?></pre>
  <h3>Raw askTranslate response</h3>
  <pre><?= e($r['raw_response']) ?></pre>
</div>

<details class="card">
  <summary>Simulate a callback locally</summary>
  <pre><?= e(sprintf(
    "ddev exec curl -si -X POST -H 'Content-Type: application/json' \\\n  -d '%s' \\\n  http://localhost/callback/%d/%s",
    json_encode($r['payload_type'] === 'text'
      ? ['requestId' => (int) $r['etranslation_request_id'], 'externalReference' => $r['external_reference'], 'targetLanguage' => strtoupper($r['target_lang']), 'translatedText' => 'Simulated translation']
      : ['requestId' => (int) $r['etranslation_request_id'], 'externalReference' => $r['external_reference'], 'targetLanguage' => strtoupper($r['target_lang']), 'result' => base64_encode('<p>Simulated translation</p>')],
      JSON_UNESCAPED_SLASHES),
    $r['id'], $hash
  )) ?></pre>
</details>
