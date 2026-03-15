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

// Obtener compras con proveedor
$stmt = $conexion->prepare("
    SELECT c.id, c.fecha, c.total, p.nombre as proveedor, c.metodo_pago
    FROM compras c
    JOIN proveedores p ON c.proveedor_id = p.id
    ORDER BY c.fecha DESC
");
$stmt->execute();
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compras - Sistema de Ventas</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --accent-hover: #1a5bc5;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }

        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --accent-hover: #3a7bc8;
            --border: #333;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
            color: var(--text);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        h1 {
            color: var(--accent);
        }

        .btn {
            padding: 10px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
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

        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--accent);
            color: white;
        }

        tbody tr:hover {
            background: var(--border);
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
		
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon"></i>
    </button>
<div style="text-align: center; margin: 20px 0;">
            <a href="/sistema_ventas/panel.php" class="btn-volver">← Volver al panel</a>
            </a>
</div>

<style>
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
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Historial de Compras</h1>
        <a href="registrar_compra.php" class="btn">+ Nueva Compra</a>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Total</th>
                    <th>Método</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($compras)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #888;">
                            <i>No hay compras registradas.</i>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($compras as $c): ?>
                        <tr>
                            <td>#<?php echo $c['id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($c['proveedor']); ?></td>
                            <td><strong>C$ <?php echo number_format($c['total'], 2); ?></strong></td>
                            <td><?php echo htmlspecialchars(ucfirst($c['metodo_pago'])); ?></td>
                            <td>
                                <a href="ver_compra.php?id=<?php echo $c['id']; ?>" style="color: var(--accent); text-decoration: none; margin-right: 10px;">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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