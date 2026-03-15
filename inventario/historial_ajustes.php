<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 🔐 Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    die("
        <script>
            alert('Acceso denegado. Solo los administradores pueden acceder a esta sección.');
            window.location.href = 'panel.php';
        </script>
    ");
}

include("../config/conexion.php");

$ajustes_combinados = [];

try {
    // 1. Obtener ajustes manuales
    $stmt_manual = $conexion->prepare("
        SELECT 
            producto_nombre,
            cantidad,
            tipo,
            motivo,
            usuario,
            fecha
        FROM historial_ajustes
    ");
    $stmt_manual->execute();
    $ajustes_manuales = $stmt_manual->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener devoluciones (que son un tipo de 'entrada')
    $stmt_devoluciones = $conexion->prepare("
        SELECT 
            p.nombre as producto_nombre,
            dd.cantidad,
            'entrada' as tipo,
            CONCAT('Devolución de Venta #', d.venta_id) as motivo,
            d.usuario,
            d.fecha
        FROM detalles_devolucion dd
        JOIN devoluciones d ON dd.devolucion_id = d.id
        JOIN productos p ON dd.producto_id = p.id
    ");
    $stmt_devoluciones->execute();
    $ajustes_devolucion = $stmt_devoluciones->fetchAll(PDO::FETCH_ASSOC);

    // 3. Combinar y ordenar los resultados
    $ajustes = array_merge($ajustes_manuales, $ajustes_devolucion);

    // Ordenar el array combinado por fecha descendente
    usort($ajustes, function($a, $b) {
        return strtotime($b['fecha']) - strtotime($a['fecha']);
    });

} catch (Exception $e) {
    $ajustes = [];
    $error_message = "Error al cargar el historial: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Ajustes</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --warning: #f39c12;
            --danger: #e74c3c;
            --success: #27ae60;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --warning: #e67e22;
            --danger: #c0392b;
            --success: #2ecc71;
            --border: #333;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
            color: var(--text);
        }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { color: var(--accent); }
        .table-container { background: var(--card-bg); border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--accent); color: white; }
        .entrada { color: var(--success); font-weight: bold; }
        .salida { color: var(--danger); font-weight: bold; }
        .btn-volver {
            display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #2575fc, #6a11cb); color: white;
            text-decoration: none; border-radius: 40px; font-weight: 500; font-size: 14px; letter-spacing: 0.5px;
            text-transform: uppercase; box-shadow: 0 4px 12px rgba(37, 117, 252, 0.35); transition: all 0.3s ease; border: none;
        }
    </style>
</head>
<body>
    <div style="max-width: 1000px; margin: 0 auto;">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Histórico de Movimientos de Inventario</h1>
            <a href="../panel.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        </div>

        <?php if (isset($error_message)): ?>
            <p style="color: red;"><?= htmlspecialchars($error_message) ?></p>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Tipo</th>
                        <th>Motivo/Detalle</th>
                        <th>Usuario</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ajustes)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #888;">
                            <i>No hay movimientos de inventario registrados.</i>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($ajustes as $a): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['producto_nombre']); ?></td>
                            <td><strong><?php echo htmlspecialchars($a['cantidad']); ?></strong></td>
                            <td>
                                <span class="<?php echo $a['tipo']; ?>">
                                    <?php echo ($a['tipo'] === 'entrada') ? '➕ Entrada' : '➖ Salida'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($a['motivo']); ?></td>
                            <td><?php echo htmlspecialchars($a['usuario']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($a['fecha'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>