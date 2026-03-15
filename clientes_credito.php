<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("config/conexion.php");
$mensaje = '';

// Registrar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    $monto = floatval($_POST['monto']);
    $metodo = $_POST['metodo_pago'];

    try {
        $conexion->beginTransaction();

        // Obtener cliente
        $stmt = $conexion->prepare("SELECT nombre, saldo FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente || $monto <= 0) throw new Exception("Datos inválidos.");

        // Actualizar saldo
        $nuevo_saldo = max(0, $cliente['saldo'] - $monto);
        $stmt = $conexion->prepare("UPDATE clientes SET saldo = ? WHERE id = ?");
        $stmt->execute([$nuevo_saldo, $cliente_id]);

        // Registrar pago
        $stmt = $conexion->prepare("
            INSERT INTO pagos_credito (cliente_id, monto, metodo_pago, usuario)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$cliente_id, $monto, $metodo, $_SESSION['usuario']]);

        $conexion->commit();
        $mensaje = "✅ Pago registrado. Cliente: {$cliente['nombre']}. Nuevo saldo: C$ " . number_format($nuevo_saldo, 2);
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Créditos de Clientes</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #2575fc; }
        .card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2575fc; color: white; }
        .btn { padding: 10px 16px; background: #2575fc; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .alert { padding: 12px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; }
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
    <div class="container">
        <h1><i class="fas fa-handshake"></i> Gestión de Créditos</h1>

        <?php if ($mensaje): ?>
            <div class="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <!-- Registrar pago -->
        <div class="card">
            <h2>Registrar Pago de Crédito</h2>
            <form method="POST">
                <div style="margin-bottom: 15px;">
                    <label>Cliente</label>
                    <select name="cliente_id" required>
                        <option value="">Seleccionar cliente...</option>
                        <?php
                        $clientes = $conexion->query("SELECT id, nombre, saldo FROM clientes WHERE activo = 1 AND saldo > 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($clientes as $c):
                        ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['nombre']); ?> (Debe: C$ <?php echo number_format($c['saldo'], 2); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label>Monto (C$)</label>
                        <input type="number" name="monto" step="0.01" min="0" required>
                    </div>
                    <div>
                        <label>Método</label>
                        <select name="metodo_pago">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="registrar_pago" class="btn">Registrar Pago</button>
            </form>
        </div>

        <!-- Lista de clientes con saldo pendiente -->
        <div class="card">
            <h2>Clientes con Saldo Pendiente</h2>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Límite de Crédito</th>
                        <th>Saldo Actual</th>
                        <th>Porcentaje Usado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conexion->query("
                        SELECT nombre, limite_credito, saldo 
                        FROM clientes 
                        WHERE activo = 1 AND saldo > 0 
                        ORDER BY saldo DESC
                    ");
                    while ($c = $stmt->fetch(PDO::FETCH_ASSOC)):
                        $porcentaje = $c['limite_credito'] > 0 ? ($c['saldo'] / $c['limite_credito']) * 100 : 0;
                        $color = $porcentaje > 80 ? '#e74c3c' : ($porcentaje > 50 ? '#f39c12' : '#27ae60');
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                        <td>C$ <?php echo number_format($c['limite_credito'], 2); ?></td>
                        <td>C$ <?php echo number_format($c['saldo'], 2); ?></td>
                        <td>
                            <div style="width: 100%; background: #ecf0f1; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo $porcentaje; ?>%; background: <?php echo $color; ?>; height: 10px;"></div>
                            </div>
                            <small><?php echo number_format($porcentaje, 1); ?>%</small>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div style="text-align: center; margin: 30px 0;">
    <a href="panel.php" class="btn-volver">
        <i class="fas fa-arrow-left"></i> Volver al Panel
    </a>
</div>
    </div>
</body>
</html>