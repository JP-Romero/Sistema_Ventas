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
$mensaje = '';
$error = '';

// Obtener productos para el dropdown
$productos = $conexion->query("SELECT id, nombre, stock FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = (int)$_POST['producto_id'];
    $tipo = $_POST['tipo']; // 'entrada' o 'salida'
    $cantidad = (int)$_POST['cantidad'];
    $motivo = trim($_POST['motivo']);

    // Validar datos de entrada
    $producto_stmt = $conexion->prepare("SELECT nombre, stock FROM productos WHERE id = ?");
    $producto_stmt->execute([$producto_id]);
    $prod = $producto_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod || $cantidad <= 0) {
        $error = "Datos inválidos. Por favor, seleccione un producto y una cantidad mayor a cero.";
    } elseif ($tipo === 'salida' && $cantidad > $prod['stock']) {
        $error = "No puedes retirar una cantidad mayor al stock actual ({$prod['stock']}).";
    } else {
        // Si la validación es correcta, proceder con la transacción
        try {
            $conexion->beginTransaction();

            // 1. Actualizar el stock del producto
            $nuevo_stock = $tipo === 'entrada' ? $prod['stock'] + $cantidad : $prod['stock'] - $cantidad;
            $stmt_stock = $conexion->prepare("UPDATE productos SET stock = ? WHERE id = ?");
            $stmt_stock->execute([$nuevo_stock, $producto_id]);

            // 2. Registrar la operación en el historial de ajustes
            $stmt_historial = $conexion->prepare("
                INSERT INTO historial_ajustes (producto_id, producto_nombre, cantidad, tipo, motivo, usuario, fecha)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt_historial->execute([
                $producto_id,
                $prod['nombre'],
                $cantidad,
                $tipo,
                $motivo,
                $_SESSION['usuario']
            ]);

            $conexion->commit();
            $mensaje = "✅ Ajuste realizado con éxito. Nuevo stock para <strong>{$prod['nombre']}</strong> es <strong>{$nuevo_stock}</strong>.";
            // Refrescar la lista de productos para mostrar el stock actualizado en el dropdown
            $productos = $conexion->query("SELECT id, nombre, stock FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $conexion->rollBack();
            $error = "Error al procesar el ajuste: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ajuste de Inventario</title>
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
            --border: #333;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
            color: var(--text);
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        h1 { color: var(--accent); margin-bottom: 20px; text-align: center; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; }
        select, input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--card-bg);
            color: var(--text);
        }
        .btn { padding: 12px; background: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-volver {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #2575fc, #6a11cb);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(37, 117, 252, 0.35);
            transition: all 0.3s ease;
            border: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="productos.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Productos
        </a>
        <h1><i class="fas fa-warehouse"></i> Ajuste Manual de Inventario</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Producto *</label>
                <select name="producto_id" required>
                    <option value="">Seleccionar producto...</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo htmlspecialchars($p['nombre']); ?> (Stock: <?php echo $p['stock']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo de Ajuste *</label>
                <select name="tipo" required>
                    <option value="entrada">➕ Entrada (devolución, sobrante)</option>
                    <option value="salida">➖ Salida (merma, robo, obsoleto, regalo)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Cantidad *</label>
                <input type="number" name="cantidad" min="1" required>
            </div>
            <div class="form-group">
                <label>Motivo *</label>
                <textarea name="motivo" rows="3" required placeholder="Ej: Merma por vencimiento, Devolución de cliente, Robo, Ajuste de conteo..."></textarea>
            </div>
            <button type="submit" class="btn">Realizar Ajuste</button>
        </form>
    </div>
</body>
</html>