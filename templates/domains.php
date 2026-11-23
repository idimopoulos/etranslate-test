<div class="crumbs"><a href="/">Home</a> › Domains</div>
<h1>Domains &amp; language pairs</h1>
<p class="muted">Live <code>getDomains</code> response (HTTP <?= e($raw['status']) ?>). Opening this page also refreshes the cached list the send form uses.</p>
<?php if (!empty($raw['error'])): ?><div class="errors"><?= e($raw['error']) ?></div><?php endif; ?>

<?php if ($summary): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css">
<style>
  .cards { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; margin:16px 0 20px; }
  .dcard { border:1px solid var(--line); border-radius:8px; padding:10px 12px; background:var(--bg); text-align:left; font:inherit; color:inherit; cursor:pointer; }
  .dcard:hover { border-color:var(--accent); }
  .dcard.active { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent) inset; }
  .dcard b { display:block; font-size:18px; }
  .dcard span { color:var(--muted); font-size:13px; }
  .gridjs-container { color:var(--fg); }
  .gridjs-th, .gridjs-td { padding:8px 10px !important; }
  .gridjs-search-input { width:280px; }
</style>

<div class="cards" id="cards">
  <button type="button" class="dcard active" data-domain="">
    <b>All</b><span><?= count($summary) ?> domains · <?= count($rows) ?> pairs</span>
  </button>
  <?php foreach ($summary as $domain => $s): ?>
    <button type="button" class="dcard" data-domain="<?= e($domain) ?>">
      <b><?= e($domain) ?></b>
      <span><?= (int) $s['raw_pairs'] ?> pairs · <?= (int) $s['sources'] ?> → <?= (int) $s['targets'] ?> languages</span>
    </button>
  <?php endforeach; ?>
</div>

<div id="pairs-grid"></div>
<noscript><p class="muted">Enable JavaScript for the searchable table; the raw response is below.</p></noscript>
<p class="hint">"Variant" lists the extra suffixes eTranslation adds to a pair (e.g. <code>EN-FR-LLM</code>); "standard" is the plain <code>EN-FR</code> form. The send form uses only the first two parts, like the module.</p>
<?php endif; ?>

<details class="card">
  <summary>Raw response</summary>
  <pre style="max-height:480px"><?= e(pretty($raw['body'])) ?></pre>
</details>

<?php if ($summary): ?>
<script src="https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js"></script>
<script>
(() => {
  if (typeof gridjs === 'undefined') return;
  const rows = <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
  const grid = new gridjs.Grid({
    columns: ['Domain', 'Source', 'Target', 'Variant'],
    data: rows,
    search: true,
    sort: true,
    pagination: { limit: 25, summary: true },
    language: { search: { placeholder: 'Search language, code or domain…' } },
  }).render(document.getElementById('pairs-grid'));

  document.querySelectorAll('#cards .dcard').forEach(card => card.addEventListener('click', () => {
    document.querySelectorAll('#cards .dcard').forEach(c => c.classList.toggle('active', c === card));
    const d = card.dataset.domain;
    grid.updateConfig({ data: d ? rows.filter(r => r[0] === d) : rows }).forceRender();
  }));
})();
</script>
<?php endif; ?>
