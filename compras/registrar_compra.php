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

// Obtener proveedores y productos
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$productos = $conexion->query("SELECT id, nombre, costo FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proveedor_id = (int)$_POST['proveedor_id'];
    $metodo_pago = $_POST['metodo_pago'];
    $productos_ids = $_POST['producto_id'] ?? [];
    $cantidades = $_POST['cantidad'] ?? [];
    $costos = $_POST['costo_unitario'] ?? [];

    if (empty($productos_ids) || $proveedor_id <= 0) {
        $error = "Debe seleccionar un proveedor y al menos un producto.";
    } else {
        try {
            $conexion->beginTransaction();

            // Calcular totales
            $subtotal = 0;
            foreach ($cantidades as $i => $cantidad) {
                $costo = $costos[$i] ?? 0;
                $subtotal += $cantidad * $costo;
            }
            $iva = $subtotal * 0.15; // 15%
            $total = $subtotal + $iva;

            // Insertar compra
            $stmt = $conexion->prepare("
                INSERT INTO compras (proveedor_id, subtotal, iva, total, metodo_pago)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$proveedor_id, $subtotal, $iva, $total, $metodo_pago]);
            $compra_id = $conexion->lastInsertId();

            // Insertar detalles y actualizar stock
            $stmt_detalle = $conexion->prepare("
                INSERT INTO detalles_compra (compra_id, producto_id, cantidad, costo_unitario)
                VALUES (?, ?, ?, ?)
            ");
            $stmt_producto = $conexion->prepare("
                UPDATE productos SET stock = stock + ? WHERE id = ?
            ");

            foreach ($productos_ids as $i => $producto_id) {
                $cantidad = (int)$cantidades[$i];
                $costo = floatval($costos[$i]);
                if ($producto_id > 0 && $cantidad > 0) {
                    $stmt_detalle->execute([$compra_id, $producto_id, $cantidad, $costo]);
                    $stmt_producto->execute([$cantidad, $producto_id]);
                }
            }

            $conexion->commit();
            $mensaje = "✅ Compra registrada y inventario actualizado.";
        } catch (Exception $e) {
            $conexion->rollback();
            $error = "Error al registrar la compra: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Compra</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; padding: 20px; background: #f4f6f9; }
        .form-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; }
        input, select, button { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .btn { padding: 12px; background: #2575fc; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1a5bc5; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .producto-item { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px; align-items: end; }
        .btn-remove { background: #e74c3c; width: auto; padding: 6px 10px; color: white; }
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

.btn-volver:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
    background: linear-gradient(135deg, #1a5bc5, #5a0fb0);
}

.btn-volver i {
    margin-right: 6px;
    transition: transform 0.3s;
}

.btn-volver:hover i {
    transform: translateX(-2px);
}
    </style>
</head>
<body>
    <div class="form-container">
        <h1><i class="fas fa-shopping-cart"></i> Registrar Nueva Compra</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Proveedor *</label>
                <select name="proveedor_id" required>
                    <option value="">Seleccionar proveedor...</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <h3>Productos</h3>
            <div id="productos-container">
                <div class="producto-item">
                    <select name="producto_id[]" required>
                        <option value="">Producto</option>
                        <?php foreach ($productos as $prod): ?>
                            <option value="<?php echo $prod['id']; ?>" data-costo="<?php echo $prod['costo']; ?>">
                                <?php echo htmlspecialchars($prod['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cantidad[]" placeholder="Cantidad" min="1" required>
                    <input type="number" name="costo_unitario[]" placeholder="Costo" step="0.01" min="0" required>
                    <button type="button" class="btn-remove" onclick="this.closest('.producto-item').remove()">✖</button>
                </div>
            </div>

            <button type="button" class="btn" onclick="agregarProducto()">+ Agregar Producto</button>

            <div class="form-group" style="margin-top: 20px;">
                <label>Método de Pago</label>
                <select name="metodo_pago">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Tarjeta">Tarjeta</option>
                </select>
            </div>

            <button type="submit" class="btn">Registrar Compra</button>
        </form>
        <br>
		<div style="text-align: center; margin: 30px 0;">
    <a href="compras.php" class="btn-volver">
        <i class="fas fa-arrow-left"></i> Volver al listado
    </a>
</div>
      
    </div>

    <script>
        function agregarProducto() {
            const container = document.getElementById('productos-container');
            const item = document.createElement('div');
            item.className = 'producto-item';
            item.innerHTML = `
                <select name="producto_id[]" required onchange="actualizarCosto(this)">
                    <option value="">Producto</option>
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?php echo $prod['id']; ?>" data-costo="<?php echo $prod['costo']; ?>">
                            <?php echo htmlspecialchars($prod['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="cantidad[]" placeholder="Cantidad" min="1" required>
                <input type="number" name="costo_unitario[]" placeholder="Costo" step="0.01" min="0" required>
                <button type="button" class="btn-remove" onclick="this.closest('.producto-item').remove()">✖</button>
            `;
            container.appendChild(item);
        }

        function actualizarCosto(select) {
            const costoInput = select.closest('.producto-item').querySelector('[name="costo_unitario[]"]');
            const costo = select.selectedOptions[0].getAttribute('data-costo');
            if (costo) costoInput.value = costo;
        }
    </script>
</body>
</html>