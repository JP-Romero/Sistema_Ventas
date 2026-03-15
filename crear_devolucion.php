<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("config/conexion.php");

$venta_id = $_GET['venta_id'] ?? null;
if (!$venta_id || !filter_var($venta_id, FILTER_VALIDATE_INT)) {
    die("ID de venta no proporcionado o no válido.");
}

// Obtener detalles de la venta y los productos vendidos
try {
    $stmt_venta = $conexion->prepare("SELECT * FROM ventas WHERE id = ?");
    $stmt_venta->execute([$venta_id]);
    $venta = $stmt_venta->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        throw new Exception("Venta no encontrada.");
    }

    $stmt_items = $conexion->prepare("
        SELECT 
            iv.id AS inventario_venta_id, 
            p.id AS producto_id, 
            p.nombre, 
            iv.cantidad AS cantidad_vendida, 
            iv.precio,
            (
                SELECT COALESCE(SUM(dd.cantidad), 0) 
                FROM detalles_devolucion dd
                JOIN devoluciones d ON dd.devolucion_id = d.id
                WHERE d.venta_id = iv.venta_id AND dd.producto_id = iv.producto_id
            ) AS cantidad_devuelta
        FROM 
            inventario_venta iv 
        JOIN 
            productos p ON iv.producto_id = p.id 
        WHERE 
            iv.venta_id = ?
    ");
    $stmt_items->execute([$venta_id]);
    $items_vendidos = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Devolución</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; padding: 20px; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 15px; background: #2575fc; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #dee2e6; text-align: left; }
        .table th { background-color: #f8f9fa; }
        input[type='number'] { width: 80px; text-align: center; padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        .form-actions { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-undo-alt"></i> Crear Devolución para Venta #<?= htmlspecialchars($venta_id) ?></h1>
        <p><strong>Cliente:</strong> <?= htmlspecialchars($venta['cliente'] ?? 'Consumidor Final') ?></p>
        <p><strong>Fecha de Venta:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($venta['fecha']))) ?></p>
        
        <form action="guardar_devolucion.php" method="POST">
            <input type="hidden" name="venta_id" value="<?= $venta_id ?>">
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant. Vendida</th>
                        <th>Cant. Devuelta</th>
                        <th>Cant. Pendiente</th>
                        <th>Precio Unit.</th>
                        <th>Cant. a Devolver</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_vendidos as $item): ?>
                    <?php 
                        $cantidad_vendida = $item['cantidad_vendida'];
                        $cantidad_devuelta = $item['cantidad_devuelta'];
                        $cantidad_pendiente = $cantidad_vendida - $cantidad_devuelta;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nombre']) ?></td>
                        <td><?= htmlspecialchars($cantidad_vendida) ?></td>
                        <td><?= htmlspecialchars($cantidad_devuelta) ?></td>
                        <td><strong><?= htmlspecialchars($cantidad_pendiente) ?></strong></td>
                        <td>C$ <?= htmlspecialchars(number_format($item['precio'], 2)) ?></td>
                        <td>
                            <input type="number" name="cantidades[<?= $item['producto_id'] ?>]" value="0" min="0" max="<?= $cantidad_pendiente ?>" required <?= $cantidad_pendiente == 0 ? 'disabled' : '' ?>>
                            <input type="hidden" name="precios[<?= $item['producto_id'] ?>]" value="<?= $item['precio'] ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="form-group" style="margin-top: 20px;">
                <label for="motivo">Motivo de la Devolución</label>
                <textarea id="motivo" name="motivo" rows="3" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn" style="background: #e74c3c;">Procesar Devolución</button>
                <a href="devoluciones.php" class="btn" style="background: #6c757d;">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>