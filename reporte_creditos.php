<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("config/conexion.php");

// Clientes con saldo pendiente
$stmt = $conexion->prepare("
    SELECT c.nombre, c.limite_credito, c.saldo, 
           (c.saldo / c.limite_credito) * 100 as porcentaje
    FROM clientes c
    WHERE c.activo = 1 AND c.saldo > 0
    ORDER BY c.saldo DESC
");
$stmt->execute();
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Créditos</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; padding: 20px; background: #f4f6f9; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #2575fc; text-align: center; }
        .summary {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 30px 0;
        }
        .card {
            background: white; padding: 15px; border-radius: 12px; text-align: center; box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        .card h3 { margin: 0; color: #2575fc; }
        .card p { margin: 5px 0 0; font-size: 14px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2575fc; color: white; }
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
        <a href="panel.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
        <h1><i class="fas fa-file-invoice-dollar"></i> Reporte de Créditos (C$)</h1>

        <div class="summary">
            <div class="card">
                <h3><?php echo count($clientes); ?></h3>
                <p>Clientes con Crédito</p>
            </div>
            <div class="card">
                <h3>C$ <?php echo number_format(array_sum(array_column($clientes, 'saldo')), 2); ?></h3>
                <p>Total por Cobrar</p>
            </div>
            <div class="card">
                <h3>C$ <?php echo number_format(array_sum(array_column($clientes, 'limite_credito')), 2); ?></h3>
                <p>Límite Total</p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Crédito Asignado</th>
                    <th>Saldo Pendiente</th>
                    <th>Porcentaje Usado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): 
                    $color = $c['porcentaje'] > 80 ? '#e74c3c' : ($c['porcentaje'] > 50 ? '#f39c12' : '#27ae60');
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['nombre']); ?></td>
                    <td>C$ <?php echo number_format($c['limite_credito'], 2); ?></td>
                    <td>C$ <?php echo number_format($c['saldo'], 2); ?></td>
                    <td>
                        <div style="width: 100%; background: #ecf0f1; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $c['porcentaje']; ?>%; background: <?php echo $color; ?>; height: 10px;"></div>
                        </div>
                        <small><?php echo number_format($c['porcentaje'], 1); ?>%</small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</body>
</html>