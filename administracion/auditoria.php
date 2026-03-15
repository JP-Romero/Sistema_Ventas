<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("../config/conexion.php");

// Verificar si es administrador
$es_admin = false;
try {
    $stmt = $conexion->prepare("
        SELECT r.permisos 
        FROM usuarios u 
        JOIN roles r ON u.rol_id = r.id 
        WHERE u.usuario = ?
    ");
    $stmt->execute([$_SESSION['usuario']]);
    $permisos_json = $stmt->fetchColumn();
    $permisos = json_decode($permisos_json, true);
    $es_admin = isset($permisos['*']) && $permisos['*'];
} catch (Exception $e) {
    die("Error de autenticación.");
}

if (!$es_admin) {
    die("Acceso no autorizado.");
}

// === Filtros ===
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// === Consulta principal ===
$sql = "SELECT a.*, u.usuario as nombre_usuario FROM historial_actividades a JOIN usuarios u ON a.usuario_id = u.id WHERE 1=1";
$params = [];

if ($filtro_usuario) { 
    $sql .= " AND u.usuario LIKE ?"; 
    $params[] = "%$filtro_usuario%"; 
}
if ($filtro_accion) { 
    $sql .= " AND a.accion = ?"; 
    $params[] = $filtro_accion; 
}
if ($filtro_fecha) { 
    $sql .= " AND DATE(a.fecha) = ?"; 
    $params[] = $filtro_fecha; 
}
$sql .= " ORDER BY a.fecha DESC LIMIT 50"; // Límite para rendimiento

try {
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $actividades = [];
    $error_db = "Error al cargar actividades.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auditoría del Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --accent-hover: #1a5bc5;
            --border: #ddd;
            --danger: #e74c3c;
            --success: #27ae60;
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
            --table-header-bg: #2c3e50;
            --table-header-text: #ecf0f1;
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card: #1f1f1f;
            --text: #e0e0e0;
            --border: #333;
            --accent: #4a90e2;
            --accent-hover: #3a7bc8;
            --table-header-bg: #34495e;
            --table-header-text: #ecf0f1;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px;
            transition: all 0.4s;
        }
        .container {
            max-width: 1200px;
            margin: auto;
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .header {
            background: var(--accent);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 30px;
        }
        .form-filtros {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .form-filtros input,
        .form-filtros select,
        .form-filtros button {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }
        .form-filtros input,
        .form-filtros select {
            flex: 1;
            min-width: 150px;
        }
        .btn-refrescar {
            background: linear-gradient(135deg, var(--accent), #6a11cb);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 500;
            white-space: nowrap;
        }
        .btn-refrescar:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 117, 252, 0.3);
        }
        .btn-descargar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #f40, #d32f2f);
            color: white;
            padding: 10px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(221, 44, 0, 0.3);
            transition: all 0.3s ease;
            margin-left: 10px;
            white-space: nowrap;
        }
        .btn-descargar:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(221, 44, 0, 0.4);
            background: linear-gradient(135deg, #e53935, #c62828);
        }
        .table-wrapper {
            overflow-x: auto;
        }
        .table-container {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
        }
        thead {
            background: var(--table-header-bg);
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            font-weight: 600;
            color: var(--table-header-text);
        }
        tbody tr:hover {
            background: var(--border);
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
            margin-bottom: 20px;
        }
        .btn-volver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
            background: linear-gradient(135deg, #1a5bc5, #5a0fb0);
        }
        .alert-error {
            color: var(--danger);
            text-align: center;
            padding: 20px;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .container {
                padding: 15px;
            }
            .content {
                padding: 15px;
            }
            .form-filtros {
                flex-direction: column;
            }
            .form-filtros input, .form-filtros select, .form-filtros button, .form-filtros a {
                width: 100%;
                margin-left: 0;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Auditoría del Sistema</h1>
        </div>
        <div class="content">
            <a href="../panel.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
            <!-- Filtros -->
            <form method="GET" class="form-filtros">
                <input type="text" name="usuario" placeholder="Filtrar por usuario" value="<?= htmlspecialchars($filtro_usuario) ?>">
                
                <select name="accion">
                    <option value="">Todas las acciones</option>
                    <option value="login" <?= $filtro_accion == 'login' ? 'selected' : '' ?>>Inicio de sesión</option>
                    <option value="venta" <?= $filtro_accion == 'venta' ? 'selected' : '' ?>>Venta realizada</option>
                    <option value="compra" <?= $filtro_accion == 'compra' ? 'selected' : '' ?>>Compra realizada</option>
                    <option value="ajuste_stock" <?= $filtro_accion == 'ajuste_stock' ? 'selected' : '' ?>>Ajuste de inventario</option>
                    <option value="crear_usuario" <?= $filtro_accion == 'crear_usuario' ? 'selected' : '' ?>>Crear usuario</option>
                </select>

                <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>">

                <button type="submit" class="btn-refrescar">
                    <i class="fas fa-sync"></i> Aplicar Filtros
                </button>

                <!-- Botón para descargar PDF -->
                <a href="javascript:void(0);" onclick="descargarPDF()" class="btn-descargar">
                    <i class="fas fa-file-pdf"></i> Descargar PDF
                </a>
            </form>

            <!-- Tabla de actividades -->
            <h3 style="margin-bottom: 15px;">Historial de Actividades</h3>
            <?php if (isset($error_db)): ?>
                <div class="alert-error"><?= $error_db ?></div>
            <?php elseif (empty($actividades)): ?>
                <div style="text-align: center; color: #888; padding: 20px;">
                    <i>No se encontraron actividades con los filtros aplicados.</i>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actividades as $act): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($act['nombre_usuario']) ?></td>
                                        <td><?= ucfirst(str_replace('_', ' ', $act['accion'])) ?></td>
                                        <td><?= date('d/m/Y H:i:s', strtotime($act['fecha'])) ?></td>
                                        <td style="font-size: 14px; color: #555;">
                                            <?= htmlspecialchars($act['descripcion']) ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($act['ip']) ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Función para descargar el PDF con filtros
        function descargarPDF() {
            const usuario = document.querySelector('input[name="usuario"]')?.value || '';
            const accion = document.querySelector('select[name="accion"]')?.value || '';
            const fecha = document.querySelector('input[name="fecha"]')?.value || '';

            let url = 'generar_auditoria_pdf.php';
            const params = new URLSearchParams();
            if (usuario) params.append('usuario', usuario);
            if (accion) params.append('accion', accion);
            if (fecha) params.append('fecha', fecha);

            window.open(url + '?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
