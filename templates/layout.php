<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>eTranslation test</title>
  <?php if (!empty($autoRefresh)): ?><meta http-equiv="refresh" content="10"><?php endif; ?>
  <style>
    :root { --fg:#1d2330; --muted:#5b6475; --line:#d9dde5; --bg:#fff; --soft:#f4f6f9; --accent:#0b5cad; --ok:#1d7a3a; --bad:#b3261e; --warn:#8a5a00; }
    * { box-sizing: border-box; }
    body { margin:0; font:15px/1.5 system-ui, sans-serif; color:var(--fg); background:var(--bg); }
    header { border-bottom:1px solid var(--line); padding:12px 24px; display:flex; gap:20px; align-items:center; }
    header strong { margin-right:auto; }
    header a { color:var(--accent); text-decoration:none; padding:4px 2px; border-bottom:2px solid transparent; }
    header a.active { border-bottom-color:var(--accent); font-weight:600; }
    .crumbs { color:var(--muted); font-size:14px; margin-bottom:4px; } .crumbs a { color:var(--accent); text-decoration:none; }
    .pager { display:flex; justify-content:space-between; gap:12px; font-size:14px; } .pager a { color:var(--accent); text-decoration:none; }
    .pair { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
    .pair h3 { margin:0 0 6px; font-size:14px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
    tr.link { cursor:pointer; } tr.link:hover td { background:var(--soft); }
    .banner { padding:10px 14px; border-radius:6px; background:#fff7e0; color:var(--warn); margin:12px 0; }
    .banner.ok { background:#e8f5ec; color:var(--ok); } .banner.bad { background:#fdecea; color:var(--bad); }
    td.muted, .muted { color:var(--muted); }
    .badge.mode-wrap { background:#eef2f7; } .badge.mode-placeholders { background:#e6f0fb; color:var(--accent); }
    .modes { display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:10px; margin-top:6px; }
    .modes label { font-weight:400; margin:0; border:1px solid var(--line); border-radius:8px; padding:10px 12px; cursor:pointer; }
    .modes label:has(input:checked) { border-color:var(--accent); box-shadow:0 0 0 1px var(--accent) inset; }
    .modes b { display:block; }
    main { max-width:1100px; margin:0 auto; padding:24px; }
    main.wide { max-width:none; }
    label { display:block; font-weight:600; margin:14px 0 4px; }
    input[type=text], input[type=url], textarea, select { width:100%; padding:8px; border:1px solid var(--line); border-radius:6px; font:inherit; }
    textarea { min-height:160px; font-family:ui-monospace, monospace; font-size:13px; }
    .row { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:16px; }
    button { margin-top:18px; padding:9px 18px; background:var(--accent); color:#fff; border:0; border-radius:6px; font:inherit; cursor:pointer; }
    .hint { color:var(--muted); font-size:13px; }
    .errors { background:#fdecea; color:var(--bad); padding:10px 14px; border-radius:6px; }
    table { width:100%; border-collapse:collapse; }
    th, td { text-align:left; padding:6px 8px; border-bottom:1px solid var(--line); vertical-align:top; }
    pre { background:var(--soft); padding:12px; border-radius:6px; overflow:auto; white-space:pre-wrap; word-break:break-word; font-size:13px; }
    .badge { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; background:var(--soft); }
    .s-translated { color:var(--ok); } .s-failed, .s-error, .s-unusable { color:var(--bad); } .s-sent, .s-pending { color:var(--warn); }
    .card { border:1px solid var(--line); border-radius:8px; padding:14px 16px; margin:16px 0; }
    iframe { width:100%; min-height:160px; border:1px solid var(--line); border-radius:6px; }
    dl { display:grid; grid-template-columns:max-content 1fr; gap:4px 16px; } dt { color:var(--muted); } dd { margin:0; word-break:break-all; }
    pre.broken { white-space: pre-wrap; word-break: break-all; border: 2px solid #c62828; background: #fdecea; color: #8e0000; }
  pre.fixed { border: 2px dashed #2e7d32; }
</style>
</head>
<body>
<header>
  <?php $nav = $nav ?? ''; $cls = fn(string $k): string => $nav === $k ? ' class="active"' : ''; ?>
  <strong><a href="/" style="color:inherit">eTranslation test</a></strong>
  <a href="/"<?= $cls('send') ?>>New translation</a>
  <a href="/requests"<?= $cls('results') ?>>Results</a>
  <a href="/domains"<?= $cls('domains') ?>>Domains</a>
</header>
<main<?= !empty($wide) ? ' class="wide"' : '' ?>><?= $content ?></main>
<script>
  // Make whole table rows clickable (the # link stays the accessible target).
  document.querySelectorAll('tr[data-href]').forEach(tr => tr.addEventListener('click', e => {
    if (!e.target.closest('a')) location.href = tr.dataset.href;
  }));
</script>
</body>
</html>
