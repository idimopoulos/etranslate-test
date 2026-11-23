<div class="crumbs"><a href="/">Home</a> › Results</div>
<h1>Results</h1>
<?php if ($ids): ?><p>Showing requests #<?= e(implode(', #', $ids)) ?> only. <a href="/requests">Show all</a></p><?php endif; ?>
<?php if (!$requests): ?>
  <p>Nothing sent yet. <a href="/">Send a translation</a>.</p>
<?php else: ?>
  <p class="muted">Click a row to see the raw callback and the decoded translation. The number in brackets is how many callbacks arrived.</p>
  <?php $rows = $requests; require __DIR__ . '/_results_table.php'; ?>
<?php endif; ?>
