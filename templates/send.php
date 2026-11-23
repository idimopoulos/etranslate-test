<h1>Send text to eTranslation</h1>
<?php if ($errors): ?>
  <div class="errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php
  // Options rendered server-side so the form works without JavaScript too.
  $domainNames = array_keys($pairs);
  if (!in_array($values['domain'], $domainNames, TRUE)) { array_unshift($domainNames, $values['domain']); }
  $sources = isset($pairs[$values['domain']]) ? array_keys($pairs[$values['domain']]) : array_keys($languageNames);
  $targets = $pairs[$values['domain']][$values['source_lang']] ?? array_keys($languageNames);
  $label = fn(string $code): string => ($languageNames[$code] ?? strtoupper($code)) . ' (' . strtoupper($code) . ')';
  $options = function (array $codes, string $selected) use ($label): string {
    $sorted = $codes;
    usort($sorted, fn($a, $b) => strcmp($label($a), $label($b)));
    if ($selected !== '' && !in_array($selected, $sorted, TRUE)) { array_unshift($sorted, $selected); }
    $html = '';
    foreach ($sorted as $code) {
      $html .= sprintf('<option value="%s"%s>%s</option>', e($code), $code === $selected ? ' selected' : '', e($label($code)));
    }
    return $html;
  };
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.default.min.css">
<style>
  .ts-wrapper .ts-control { border-color: var(--line); border-radius: 6px; padding: 7px 8px; font: inherit; }
  .ts-dropdown { font: inherit; }
</style>
<form method="post" action="/send">
  <div class="row">
    <div>
      <label for="domain">Domain</label>
      <select id="domain" name="domain" required>
        <?php foreach ($domainNames as $d): ?><option value="<?= e($d) ?>"<?= $d === $values['domain'] ? ' selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="source_lang">Source language</label>
      <select id="source_lang" name="source_lang" required><?= $options($sources, $values['source_lang']) ?></select>
    </div>
    <div>
      <label for="target_lang">Target language</label>
      <select id="target_lang" name="target_lang" required><?= $options($targets, $values['target_lang']) ?></select>
    </div>
  </div>
  <p class="hint" id="pair-hint"><?= $pairs ? 'Type to search. The lists only show the pairs the chosen domain supports.' : '' ?></p>
  <?php if (!$domains): ?><p class="hint">Domain list unavailable (no credentials or getDomains failed). Check <a href="/domains">/domains</a>. Showing the EU languages; any pair can be tried.</p><?php endif; ?>

  <label for="text">Text</label>
  <textarea id="text" name="text" required><?= e($values['text']) ?></textarea>
  <p class="hint">Same rules as the module: HTML is sent as a base64 <code>html</code> document, plain text over 1000 characters as a base64 <code>txt</code> document, anything else as <code>textToTranslate</code>.</p>

  <label>Non-translatable handling</label>
  <div class="modes">
    <label><input type="radio" name="mode" value="wrap" <?= $values['mode'] === 'wrap' ? 'checked' : '' ?>>
      <b>Wrap (current release)</b>
      <span class="muted">Matches are sent as <code>[notranslate]&lt;code&gt;…&lt;/code&gt;[/notranslate]</code>; the tags are stripped on receipt.</span></label>
    <label><input type="radio" name="mode" value="placeholders" <?= $values['mode'] === 'placeholders' ? 'checked' : '' ?>>
      <b>Placeholders (branch)</b>
      <span class="muted">Matches are sent as <code>[notranslate]NTPH1[/notranslate]</code> and restored from the source on receipt (<code>notranslate-placeholders</code>).</span></label>
    <label><input type="radio" name="mode" value="both" <?= $values['mode'] === 'both' ? 'checked' : '' ?>>
      <b>Both</b>
      <span class="muted">Send the same text twice, once per mode, and compare them.</span></label>
  </div>

  <label for="patterns">Non-translatable patterns</label>
  <textarea id="patterns" name="patterns" style="min-height:200px" placeholder="# one regexp per line, no delimiters&#10;[@!%][a-zA-Z0-9:_-]+"><?= e($values['patterns']) ?></textarea>
  <p class="hint">Defaults are Joinup's (<code>develop</code>, translator <code>ec_etranslation</code>). Empty the box to send the text unchanged.</p>
  <label style="font-weight:400"><input type="checkbox" name="clean" value="1" <?= $values['clean'] ? 'checked' : '' ?>> Clean up unbalanced [notranslate] tags</label>

  <label for="callback_base">Callback base URL</label>
  <input type="url" id="callback_base" name="callback_base" value="<?= e($values['callback_base']) ?>" placeholder="https://xxxx.example-tunnel.dev" required>
  <?php [, $source] = \App\Config::callbackBaseUrlSource(); ?>
  <p class="hint">Must be reachable from the internet.
    <?php if ($source === 'tunnel'): ?>Default: the temporary tunnel started by <code>ddev setup</code> / <code>ddev tunnel start</code>.
    <?php elseif ($source === 'env'): ?>Default: <code>CALLBACK_BASE_URL</code> from <code>.ddev/.env</code>.
    <?php else: ?>No default set. Run <code>ddev tunnel start</code> to get one.<?php endif; ?></p>

  <button type="submit">Send to eTranslation</button>
</form>

<h2 style="margin-top:40px">Recent results</h2>
<?php if (!$recent): ?>
  <p class="muted">Nothing sent yet. After you send, you are taken to the result page; all results are listed under <a href="/requests">Results</a>.</p>
<?php else: ?>
  <?php $rows = $recent; require __DIR__ . '/_results_table.php'; ?>
  <p><a href="/requests">All results →</a></p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(() => {
  if (typeof TomSelect === 'undefined') return; // CDN unavailable: plain selects still work.
  const pairs = <?= json_encode($pairs ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const names = <?= json_encode($languageNames ?: new stdClass(), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const allCodes = Object.keys(names);
  const label = c => `${names[c] || c.toUpperCase()} (${c.toUpperCase()})`;
  const opts = codes => codes.map(c => ({ value: c, text: label(c) })).sort((a, b) => a.text.localeCompare(b.text));
  const common = { maxOptions: null, create: false, searchField: ['text', 'value'], sortField: [{ field: 'text' }] };

  const domain = new TomSelect('#domain', { ...common, create: true, createOnBlur: true });
  const source = new TomSelect('#source_lang', common);
  const target = new TomSelect('#target_lang', common);

  const refill = (ts, codes) => {
    const keep = ts.getValue();
    ts.clear(true); ts.clearOptions(); ts.addOptions(opts(codes));
    ts.setValue(codes.includes(keep) ? keep : (codes.includes('en') && ts === source ? 'en' : codes[0] || ''), true);
  };
  const map = () => pairs[domain.getValue()];
  const updateTargets = () => refill(target, map() ? (map()[source.getValue()] || []) : allCodes);
  const updateSources = () => { refill(source, map() ? Object.keys(map()) : allCodes); updateTargets(); };

  domain.on('change', updateSources);
  source.on('change', updateTargets);
})();
</script>
