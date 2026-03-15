<h2>🆕 Últimos productos agregados</h2>
<table>
  <tr><th>Nombre</th><th>Precio</th><th>Stock</th></tr>
  <?php foreach ($ultimos_productos as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['nombre']) ?></td>
      <?= 'C$ ' . number_format($p['precio'], 2, '.', ',') ?>
      <td><?= $p['stock'] ?></td>
    </tr>
  <?php endforeach; ?>
</table>
