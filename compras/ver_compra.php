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

$compra_id = (int)($_GET['id'] ?? 0);

// Obtener datos de la compra
$stmt = $conexion->prepare("
    SELECT c.*, p.nombre as proveedor 
    FROM compras c
    JOIN proveedores p ON c.proveedor_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die("<h3 style='color: red; text-align: center;'>Compra no encontrada.</h3><a href='compras.php'>← Volver</a>");
}

// Obtener detalles de la compra
$stmt = $conexion->prepare("
    SELECT dc.*, prod.nombre as producto_nombre 
    FROM detalles_compra dc
    JOIN productos prod ON dc.producto_id = prod.id
    WHERE dc.compra_id = ?
");
$stmt->execute([$compra_id]);
$detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra #<?php echo $compra['id']; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }

        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --border: #333;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
            color: var(--text);
        }

        .receipt {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .header h1 {
            color: var(--accent);
            margin: 0;
        }

        .info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info div {
            padding: 8px;
        }

        .table-container {
            margin: 20px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--accent);
            color: white;
        }

        .total {
            text-align: right;
            font-size: 18px;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn:hover {
            background: #1a5bc5;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--accent);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
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
            margin-bottom: 20px;
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
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>

    <div class="receipt">
        <a href="compras.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Compras</a>
        <div class="header">
            <h1><i class="fas fa-shopping-cart"></i> Detalle de Compra</h1>
            <p>ID: #<?php echo $compra['id']; ?> | Fecha: <?php echo date('d/m/Y H:i', strtotime($compra['fecha'])); ?></p>
        </div>

        <div class="info">
            <div><strong>Proveedor:</strong> <?php echo htmlspecialchars($compra['proveedor']); ?></div>
            <div><strong>Método de Pago:</strong> <?php echo htmlspecialchars(ucfirst($compra['metodo_pago'])); ?></div>
            <div><strong>Subtotal:</strong> C$ <?php echo number_format($compra['subtotal'], 2); ?></div>
            <div><strong>IVA (15%):</strong> C$ <?php echo number_format($compra['iva'], 2); ?></div>
            <div><strong>Total:</strong> <strong style="color: var(--accent);">C$ <?php echo number_format($compra['total'], 2); ?></strong></div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Costo Unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalles as $d): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['producto_nombre']); ?></td>
                        <td><?php echo $d['cantidad']; ?></td>
                        <td>C$ <?php echo number_format($d['costo_unitario'], 2); ?></td>
                        <td>C$ <?php echo number_format($d['total_linea'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="total">
            Total de productos: <strong><?php echo array_sum(array_column($detalles, 'cantidad')); ?></strong>
        </div>

    </div>

    <script>
        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') body.setAttribute('data-theme', 'dark');
        themeToggle.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            body.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            themeToggle.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
        });
    </script>
</body>
</html>