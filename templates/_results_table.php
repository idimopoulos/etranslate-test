<table>
  <thead><tr><th>#</th><th>Sent</th><th>Pair</th><th>Type</th><th>Mode</th><th>Status</th><th>Original</th><th>Translation</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr class="link" data-href="/requests/<?= (int) $row['id'] ?>">
      <td><a href="/requests/<?= (int) $row['id'] ?>">#<?= (int) $row['id'] ?></a></td>
      <td class="muted"><?= e($row['created_at']) ?></td>
      <td><?= e(strtoupper($row['source_lang']) . ' → ' . strtoupper($row['target_lang'])) ?></td>
      <td><span class="badge"><?= e($row['payload_type']) ?></span></td>
      <td><span class="badge mode-<?= e($row['notranslate_mode']) ?>"><?= e($row['notranslate_mode']) ?></span></td>
      <td class="s-<?= e($row['status']) ?>"><?= e($row['status']) ?><?= (int) $row['callbacks'] ? ' (' . (int) $row['callbacks'] . ')' : '' ?></td>
      <td><?= e($row['excerpt']) ?></td>
      <td><?= $row['translation'] !== null ? e($row['translation']) : '<span class="muted">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
