<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("config/conexion.php");

$ticket_id_busqueda = $_GET['ticket_id'] ?? '';
$ventas = [];

try {
    if (!empty($ticket_id_busqueda)) {
        $stmt = $conexion->prepare("SELECT * FROM ventas WHERE id = ?");
        $stmt->execute([$ticket_id_busqueda]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $hoy = date('Y-m-d');
        $stmt = $conexion->prepare("SELECT * FROM ventas WHERE DATE(fecha) = ? ORDER BY fecha DESC");
        $stmt->execute([$hoy]);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error al consultar las ventas: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Devoluciones - Buscar Ticket</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; padding: 20px; color: #333; }
        .container { max-width: 1000px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; border-bottom: 2px solid #2575fc; padding-bottom: 10px; margin-bottom: 20px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 15px; background: #2575fc; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #1a5bc5; }
        .btn-volver { background: #6c757d; }
        .search-form { margin-bottom: 20px; display: flex; gap: 10px; }
        .search-form input { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
        .table-container { border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px 15px; border-bottom: 1px solid #dee2e6; text-align: left; }
        .table th { background-color: #34495e; color: white; }
        .table tbody tr:hover { background-color: #f1f1f1; }
    </style>
</head>
<body>
    <div class="container">
        <a href="panel.php" class="btn btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        <h1><i class="fas fa-undo-alt"></i> Iniciar una Devolución</h1>
        <p>Mostrando tickets de hoy. Use el buscador para encontrar un ticket específico por su ID.</p>
        
        <form method="GET" class="search-form">
            <input type="number" name="ticket_id" placeholder="Buscar por ID de Ticket..." value="<?= htmlspecialchars($ticket_id_busqueda) ?>">
            <button type="submit" class="btn"><i class="fas fa-search"></i> Buscar</button>
        </form>

        <?php if (isset($error)): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Ticket</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Total</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr><td colspan="5" style="text-align:center; padding: 20px;">No se encontraron ventas para hoy o para el ID buscado.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td><?= htmlspecialchars($venta['id']) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($venta['fecha']))) ?></td>
                                <td><?= htmlspecialchars($venta['cliente'] ?? 'Consumidor Final') ?></td>
                                <td>C$ <?= htmlspecialchars(number_format($venta['total'], 2)) ?></td>
                                <td>
                                    <a href="crear_devolucion.php?venta_id=<?= $venta['id'] ?>" class="btn">Crear Devolución</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>