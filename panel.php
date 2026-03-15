<?php
// Configuración de encabezados y codificación (debe ser lo primero)
header('Content-Type: text/html; charset=UTF-8');
session_start();

// Redirigir si no está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// === Inicializar variables de sesión y permisos ===
$logueado = isset($_SESSION['usuario']);
$es_admin = false;

// Verificar rol si existe
if ($logueado && isset($_SESSION['rol'])) { // CORREGIDO: de 'usuario_rol' a 'rol'
    $es_admin = ($_SESSION['rol'] === 'admin');
}

// Función mejorada para sanitizar texto
function sanitizeText($text) {
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8');
    }
    $text = normalizer_normalize($text, Normalizer::FORM_C);
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

// Obtener usuario sanitizado
$usuario = sanitizeText($_SESSION['usuario']);

// Incluir conexión
include("config/conexion.php");

// Función para verificar permisos (evitar redeclaración)
if (!function_exists('tienePermiso')) {
    function tienePermiso($conexion, $permiso) {
        if (!isset($_SESSION['usuario'])) return false;

        try {
            $stmt = $conexion->prepare("
                SELECT r.permisos 
                FROM usuarios u 
                JOIN roles r ON u.rol_id = r.id 
                WHERE u.usuario = ?
            ");
            $stmt->execute([$_SESSION['usuario']]);
            $resultado = $stmt->fetch();

            if (!$resultado) return false;

            $permisos = json_decode($resultado['permisos'], true);

            // Permiso global (*)
            if (isset($permisos['*']) && $permisos['*']) return true;

            return !empty($permisos[$permiso]) && $permisos[$permiso];
        } catch (Exception $e) {
            // En producción, loguea el error
            // error_log("Error en tienePermiso: " . $e->getMessage());
            return false; // Cambiado a false para mayor seguridad
        }
    }
}

// Configurar conexión para UTF-8
$conexion->exec("SET NAMES 'utf8mb4'");
$conexion->exec("SET CHARACTER SET utf8mb4");
$conexion->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");

// === Datos de resumen para tarjetas ===
try {
    $hoy = date('Y-m-d'); // Se mantiene por si se usa en otras partes del código.
    
    $stmt = $conexion->prepare("SELECT COUNT(*) as total_ventas, COALESCE(SUM(total), 0) as total_ingresos FROM ventas WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_ventas' => 0, 'total_ingresos' => 0];

    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    $stmt->execute();
    $productos = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0];

    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM clientes WHERE activo = 1");
    $stmt->execute();
    $clientes = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0];

    $ultimaHora = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $stmt = $conexion->prepare("SELECT COUNT(*) as nuevas FROM ventas WHERE fecha > ?");
    $stmt->execute([$ultimaHora]);
    $noti = $stmt->fetch(PDO::FETCH_ASSOC);
    $nuevasVentas = $noti['nuevas'] ?? 0;
} catch (PDOException $e) {
    $resumen = ['total_ventas' => 0, 'total_ingresos' => 0];
    $productos = $clientes = ['total' => 0];
    $nuevasVentas = 0;
}

// === Datos para Inventario y Compras ===
try {
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    $total_productos = ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $total_productos = 0;
}

try {
    $stmt = $conexion->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND stock <= stock_minimo");
    $bajo_stock_count = ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $bajo_stock_count = 0;
}

try {
    $stmt = $conexion->prepare("SELECT COUNT(*) as total FROM compras WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $compras_hoy = ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Exception $e) {
    $compras_hoy = 0;
}

try {
    $stmt = $conexion->prepare("SELECT COALESCE(SUM(total), 0) as gasto FROM compras WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $gasto_compras_hoy = ($stmt->fetch(PDO::FETCH_ASSOC)['gasto'] ?? 0);
} catch (Exception $e) {
    $gasto_compras_hoy = 0;
}

// === Datos para Gráfico de Productos Más Vendidos ===
try {
    $stmt_top_productos = $conexion->query("
        SELECT 
            p.nombre, 
            SUM(iv.cantidad) as total_vendido
        FROM inventario_venta iv
        JOIN productos p ON iv.producto_id = p.id
        GROUP BY p.nombre
        ORDER BY total_vendido DESC
        LIMIT 6
    ");
    $productos_mas_vendidos_chart = $stmt_top_productos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $productos_mas_vendidos_chart = []; // En caso de error, el gráfico estará vacío
}

// === Verificar si hay un turno activo para mostrar el botón de cierre ===
$turno_activo = false;
if (isset($_SESSION['usuario'])) {
    $stmt_check_turno = $conexion->prepare("SELECT id FROM caja_sesiones WHERE usuario_nombre = ? AND estado = 'abierto'");
    $stmt_check_turno->execute([$_SESSION['usuario']]);
    if ($stmt_check_turno->fetch()) {
        $turno_activo = true;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - Sistema de Ventas</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #f4f6f9;
            --text: #333;
            --sidebar-bg: #2c3e50;
            --sidebar-item-bg: #34495e;
            --sidebar-item-hover: #1a2530;
            --sidebar-active: #ecf0f1;
            --sidebar-active-text: #2c3e50;
            --header-bg: #ffffff;
            --header-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --card-bg: #ffffff;
            --card-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            --border: #dee2e6;
            --accent: #2575fc;
            --accent-hover: #1a5bc5;
            --danger: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --font: 'Poppins', sans-serif;
            --header-text: #2c3e50;
            --table-header-bg: #34495e;
            --table-header-text: #ecf0f1;
        }
        [data-theme="dark"] {
            --bg-color: #121212;
            --text: #e0e0e0;
            --sidebar-bg: #1e1e1e;
            --sidebar-item-bg: #2d2d2d;
            --sidebar-item-hover: #3a3a3a;
            --sidebar-active: #4a90e2;
            --sidebar-active-text: #ffffff;
            --header-bg: #1a1a1a;
            --header-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            --card-bg: #1f1f1f;
            --card-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
            --border: #333;
            --accent: #4a90e2;
            --accent-hover: #3a7bc8;
            --danger: #c0392b;
            --success: #2ecc71;
            --warning: #e67e22;
            --header-text: #ecf0f1;
            --table-header-bg: #2d2d2d;
            --table-header-text: #e0e0e0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: var(--font);
            background-color: var(--bg-color);
            color: var(--text);
            display: flex;
            min-height: 100vh;
            transition: background-color 0.4s ease;
        }
        /* Soporte para emojis modernos */
        @font-face {
            font-family: 'Noto Color Emoji';
            src: url('https://cdn.jsdelivr.net/npm/@fontsource/noto-color-emoji@5.0.0/latin-400.woff2') format('woff2');
            font-weight: 400;
            font-style: normal;
            font-display: swap;
        }
        .emoji {
            font-family: 'Noto Color Emoji', 'Segoe UI Emoji', 'Apple Color Emoji', 'Twemoji Mozilla', sans-serif;
        }
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border);
        }
        .sidebar-header img {
            max-width: 50px;
            border-radius: 50%;
            margin-bottom: 10px;
        }
        .sidebar-header h2 {
            color: var(--sidebar-active);
            font-size: 18px;
            font-weight: 600;
        }
        .sidebar-menu {
            list-style: none;
            margin-top: 20px;
        }
        .sidebar-menu li {
            margin: 4px 0;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--sidebar-active);
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            font-size: 15px;
            gap: 12px;
        }
        .sidebar-menu a i {
            width: 24px;
            text-align: center;
            font-size: 18px;
            color: inherit;
        }
        .sidebar-menu a:hover {
            background: var(--sidebar-item-hover);
            color: var(--accent);
        }
        .sidebar-menu a.active {
            background: var(--sidebar-item-bg);
            color: var(--sidebar-active-text);
            font-weight: 600;
            border-left-color: var(--accent);
        }
        /* Main Content */
        main {
            margin-left: 260px;
            padding: 20px;
            width: calc(100% - 260px);
            transition: all 0.3s ease;
        }
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--header-bg);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: var(--header-shadow);
            margin-bottom: 30px;
        }
        .user-info {
            font-size: 16px;
            font-weight: 500;
        }
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
        }
        .card-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .card-title {
            font-size: 16px;
            color: var(--text);
            margin-bottom: 10px;
        }
        .card-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
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
            margin-top: 10px;
        }
        .btn-volver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
            background: linear-gradient(135deg, #1a5bc5, #5a0fb0);
        }
        /* Gráficos */
        .chart-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        /* Tabla */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
            background-color: var(--card-bg);
            color: var(--text);
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
        .btn-ver-todas {
            display: block;
            text-align: center;
            padding: 12px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            font-weight: 500;
            margin-top: 10px;
            border-radius: 8px;
        }
        .btn-ver-todas:hover {
            background: var(--accent-hover);
        }
        /* Botón flotante */
        .fab-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 999;
        }
        .fab-main {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), #6a11cb);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(37, 117, 252, 0.4);
            transition: transform 0.3s;
        }
        .fab-main:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.3);
        }
        .fab-option {
            margin: 8px 0;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .fab-container.active .fab-option {
            opacity: 1;
            transform: translateY(0);
        }
        .fab-option a {
            width: 50px;
            height: 50px;
            background: var(--accent);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            transition: all 0.3s;
        }
        .fab-option a:hover {
            transform: scale(1.1);
        }
        /* Botón de tema */
        .theme-toggle {
            position: fixed;
            top: 0px;
            right: 0px;
            background: var(--accent);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }
        /* Bienvenida y fecha */
        .welcome-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            font-size: 16px;
        }
        .welcome-info div:first-child {
            text-align: left;
        }
        .welcome-info #saludo-dinamico {
            font-size: 14px;
            color: var(--accent);
            margin-top: 4px;
        }
        .fecha-hora {
            text-align: right;
            font-size: 14px;
            color: var(--text);
        }
        .fecha-hora span {
            display: block;
        }
        @media (max-width: 768px) {
            .welcome-info {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
        }

        /* === Estilos para el menú responsivo === */
        .menu-toggle {
            display: none; /* Oculto por defecto en escritorio */
            background: none;
            border: none;
            color: var(--header-text);
            font-size: 22px;
            cursor: pointer;
            margin-right: 15px;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1001; /* Asegurar que esté sobre el contenido */
            }
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 4px 0 15px rgba(0,0,0,0.2);
            }
            main {
                margin-left: 0;
                width: 100%;
            }
            .menu-toggle {
                display: block; /* Mostrar en móvil */
            }
            .dashboard-cards {
                grid-template-columns: 1fr; /* Una sola columna en móviles */
            }
        }

        /* === Estilos para Modales de Gestión === */
        .management-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow-y: auto;
            background-color: rgba(0,0,0,0.6);
            align-items: flex-start; /* Alinear arriba */
            justify-content: center;
            padding-top: 50px; /* Espacio desde arriba */
        }
        .management-modal .modal-content-lg {
            background-color: var(--card-bg);
            margin: auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
        }
        .management-modal .modal-content-sm {
            background-color: var(--card-bg);
            margin: auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
        }
        .management-modal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
            margin-bottom: 20px;
            color: var(--accent);
        }
        .management-modal .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text);
        }
        .usuarios-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .usuarios-table th, .usuarios-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .usuarios-table th {
            background: var(--table-header-bg);
            color: var(--table-header-text);
        }
        .usuarios-table tbody tr:hover {
            background: var(--border);
        }
        .form-actions {
            text-align: right;
            margin-top: 20px;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-primary {
            background: var(--accent);
        }
        .btn-success {
            background: var(--success);
        }
        .usuarios-table .btn {
            margin: 0 2px;
            padding: 8px 12px;
            font-size: 14px;
        }
        .usuarios-table .btn-edit {
            background: #f39c12; /* Naranja */
        }
        .usuarios-table .btn-pass {
            background: #3498db; /* Azul */
        }
        .usuarios-table .btn-delete {
            background: var(--danger);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="img/logoF.png" alt="Logo del sistema">
            <h2>Panel de Administración</h2>
            <button class="theme-toggle" id="themeToggle">
                <span class="emoji">🌙</span>
            </button>
        </div>
        <ul class="sidebar-menu">
    <li><a href="panel.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
    <li><a href="ventas/venta.php"><i class="fas fa-shopping-cart"></i> Realizar Venta</a></li>
    <?php if (tienePermiso($conexion, 'ver_ventas')): ?>
        <li><a href="ventas/ventas.php"><i class="fas fa-cash-register"></i> Ventas</a></li>
    <?php endif; ?>
    <?php if (tienePermiso($conexion, 'ver_clientes')): ?>
        <li><a href="clientes/clientes.php"><i class="fas fa-users"></i> Clientes</a></li>
    <?php endif; ?>
    <?php if (tienePermiso($conexion, 'ver_reportes')): ?>
        <li><a href="ventas/reportes.php"><i class="fas fa-chart-line"></i> Reportes</a></li>
    <?php endif; ?>
    <?php if (tienePermiso($conexion, 'gestionar_productos')): ?>
        <li><a href="inventario/productos.php"><i class="fas fa-wrench"></i> Inventario de Productos</a></li>
    <?php endif; ?>
    <?php if (tienePermiso($conexion, 'gestionar_usuarios')): ?>
        <li><a href="#" onclick="event.preventDefault(); openUserModal();"><i class="fas fa-users-cog"></i> Gestionar Usuarios</a></li>
    <?php endif; ?>
    <li><a href="ajustes.php"><i class="fas fa-cog"></i> Ajustes</a></li>
	
	<!-- ✅ ENLACE AÑADIDO AQUÍ -->
            <li><a href="acerca_de.php"><i class="fas fa-info-circle"></i> Acerca de</a></li>
    <!-- ✅ Enlace a perfil -->
    <li><a href="perfil.php"><i class="fas fa-user"></i> Mi Perfil</a></li>

    <!-- Solo para administradores -->
    <?php if ($es_admin): ?>
        <li><a href="administracion/roles.php"><i class="fas fa-users-cog"></i> Gestionar Roles</a></li>
        <li><a href="administracion/auditoria.php"><i class="fas fa-shield-alt"></i> Auditoría del Sistema</a></li>
    <?php endif; ?>

    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></li>
</ul>
    </aside>

    <!-- Main Content -->
    <main>
        <div class="header">
            <button class="menu-toggle" id="menu-toggle" aria-label="Toggle menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="welcome-info">
                <div>
                    <strong>Bienvenido, <?php echo $usuario; ?></strong>
                    <div id="saludo-dinamico"></div>
                </div>
                <div class="fecha-hora">
                    <span id="fecha-header"></span><br>
                    <span id="hora-header"></span>
                </div>
            </div>
        </div>

        <?php if ($turno_activo): ?>
        <div style="text-align: right; margin-bottom: 20px;">
            <a href="cerrar_turno.php" class="btn-volver" style="background: #e74c3c; box-shadow: 0 4px 12px rgba(231, 76, 60, 0.35);">
                <i class="fas fa-door-closed"></i> Cerrar Turno Actual
            </a>
        </div>
        <?php endif; ?>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon"><i class="fas fa-cash-register"></i></div>
                <div class="card-title">Ventas Hoy</div>
                <div class="card-value"><?php echo $resumen['total_ventas']; ?></div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="card-title">Ingresos Hoy</div>
                <div class="card-value">C$ <?php echo number_format($resumen['total_ingresos'], 2); ?></div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-box"></i></div>
                <div class="card-title">Productos en Inventario</div>
                <div class="card-value"><?php echo $total_productos; ?></div>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i></div>
                <div class="card-title">Bajo Stock</div>
                <div class="card-value" style="color: #e74c3c;"><?php echo $bajo_stock_count; ?></div>
                <a href="inventario/historial_ajustes.php" class="btn-volver">Historial de ajustes</a>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="card-title">Compras Hoy</div>
                <div class="card-value"><?php echo $compras_hoy; ?></div>
                <a href="compras/ver_compra.php" class="btn-volver">Ver compras</a>
            </div>
            <div class="card">
                <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
                <div class="card-title">Gasto en Compras (Hoy)</div>
                <div class="card-value">C$ <?php echo number_format($gasto_compras_hoy, 2); ?></div>
                <a href="compras/compras.php" class="btn-volver">Historial de compra</a>
            </div>
        </div>

        <!-- Gráfico de Ventas -->
        <div class="chart-container">
            <h3>Ventas de la Semana</h3>
            <canvas id="ventasChart" height="100"></canvas>
        </div>

        <!-- Gráfico de Productos Más Vendidos -->
        <div class="chart-container">
            <h3>Productos Más Vendidos</h3>
            <canvas id="productosChart" height="100"></canvas>
        </div>

        <!-- Clientes Registrados -->
        <?php
        try {
            $stmt = $conexion->prepare("SELECT id, nombre, telefono, correo, direccion FROM clientes WHERE activo = 1 ORDER BY nombre LIMIT 5");
            $stmt->execute();
            $lista_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $lista_clientes = [];
        }
        ?>
        <h3 style="margin-bottom: 15px;">Clientes Registrados</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Dirección</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista_clientes)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #888;">
                                <i>No hay clientes registrados.</i>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lista_clientes as $c): ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo sanitizeText($c['nombre']); ?></td>
                                <td><?php echo sanitizeText($c['telefono']); ?></td>
                                <td><?php echo sanitizeText($c['correo']); ?></td>
                                <td><?php echo sanitizeText($c['direccion']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="clientes/clientes.php" class="btn-ver-todas">Ver todos los clientes</a>
        </div>

        <!-- Últimas Ventas -->
        <?php
        try {
            $stmt = $conexion->prepare("SELECT id, cliente, total, metodo_pago, fecha FROM ventas ORDER BY fecha DESC LIMIT 5");
            $stmt->execute();
            $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $ventas = [];
        }
        ?>
        <h3 style="margin-bottom: 15px;">Últimas Ventas</h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Monto</th>
                        <th>Método</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventas)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #888;">
                                <i>No hay ventas registradas.</i>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ventas as $venta): ?>
                            <tr>
                                <td><?php echo $venta['id']; ?></td>
                                <td><?php echo sanitizeText($venta['cliente'] ?? 'Consumidor Final'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                                <td>C$ <?php echo number_format($venta['total'], 2); ?></td>
                                <td>
                                    <span style="background: var(--bg-color); padding: 4px 8px; border-radius: 12px; font-size: 13px;">
                                        <?php echo sanitizeText(ucfirst($venta['metodo_pago'] ?? 'Efectivo')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <a href="ventas/ventas.php" class="btn-ver-todas">Ver todas las ventas</a>
        </div>
    </main>

    <!-- Modal para Gestión de Usuarios -->
    <div id="user-management-modal" class="management-modal" style="display: none;">
        <div class="modal-content-lg">
            <div class="modal-header">
                <h2><i class="fas fa-users-cog"></i> Gestión de Usuarios</h2>
                <button class="close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <div class="modal-body">
                <button type="button" class="btn btn-success" onclick="openUserForm(null)"><i class="fas fa-plus"></i> Nuevo Usuario</button>
                <div class="table-container" style="margin-top: 20px;">
                    <table class="usuarios-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                            <!-- Los usuarios se cargarán aquí con JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Formulario de Usuario (Nuevo/Editar) -->
    <div id="user-form-modal" class="management-modal" style="display: none;">
        <div class="modal-content-sm">
            <div class="modal-header">
                <h2 id="user-form-title">Nuevo Usuario</h2>
                <button class="close-btn" onclick="closeUserFormModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="usuarioForm" onsubmit="handleUserFormSubmit(event)">
                    <input type="hidden" id="usuarioId" name="id">
                    <div class="form-group">
                        <label for="usuario">Nombre de Usuario</label>
                        <input type="text" id="usuario" name="usuario" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" placeholder="Dejar vacío para no cambiar">
                    </div>
                    <div class="form-group">
                        <label for="rol">Rol</label>
                        <select id="rol" name="rol" required>
                            <option value="admin">Administrador</option>
                            <option value="cajero">Cajero</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeUserFormModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Botón flotante -->
    <div class="fab-container" id="fabContainer">
        <div class="fab-main" onclick="this.parentElement.classList.toggle('active')">
            <i class="fas fa-plus"></i>
       </div>
        <div class="fab-option"><a href="ventas/venta.php" title="Nueva Venta"><i class="fas fa-shopping-cart"></i></a></div>
        <div class="fab-option"><a href="compras/registrar_compra.php" title="Nueva Compra"><i class="fas fa-truck"></i></a></div>
        <div class="fab-option"><a href="inventario/agregar_producto.php" title="Nuevo Producto"><i class="fas fa-box"></i></a></div>
        <div class="fab-option"><a href="inventario/ajuste_inventario.php" title="Ajustar Stock"><i class="fas fa-wrench"></i></a></div>
    </div>

    <script>
        // === Saludo y Fecha/Hora Dinámicos ===
        function actualizarSaludoYFechaHora() {
            const ahora = new Date();
            const opcionesFecha = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const opcionesHora = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            };
            const fechaFormateada = ahora.toLocaleDateString('es-NI', opcionesFecha);
            const horaFormateada = ahora.toLocaleTimeString('es-NI', opcionesHora);
            document.getElementById('fecha-header').textContent = fechaFormateada.charAt(0).toUpperCase() + fechaFormateada.slice(1);
            document.getElementById('hora-header').textContent = horaFormateada;
            const horas = ahora.getHours();
            let saludo = '';
            if (horas >= 6 && horas < 12) {
                saludo = '☀️ Buenos días';
            } else if (horas >= 12 && horas < 18) {
                saludo = '🌆 Buenas tardes';
            } else {
                saludo = '🌙 Buenas noches';
            }
            document.getElementById('saludo-dinamico').textContent = saludo;
        }
        actualizarSaludoYFechaHora();
        setInterval(actualizarSaludoYFechaHora, 1000);

        // Gráfico: Ventas de la semana
        fetch('api/ventas_semana.php')
            .then(res => res.json())
            .then(data => {
                const ctx1 = document.getElementById('ventasChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: data.dias,
                        datasets: [{
                            label: 'Ventas (C$)',
                            data: data.totales,
                            backgroundColor: '#2575fc',
                            borderColor: '#1a5bc5',
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, scales: { y: { beginAtZero: true } } }
                });
            })
            .catch(err => {
                console.error('Error al cargar ventas:', err);
                Swal.fire('Error', 'No se pudieron cargar las ventas de la semana.', 'error');
            });

        <?php
        // Preparar datos para el gráfico de productos más vendidos
        $top_nombres = array_column($productos_mas_vendidos_chart, 'nombre');
        $top_cantidades = array_column($productos_mas_vendidos_chart, 'total_vendido');
        ?>
        // Gráfico: Productos más vendidos
        const topProductosData = {
            nombres: <?php echo json_encode($top_nombres); ?>,
            cantidades: <?php echo json_encode($top_cantidades); ?>
        };

        if (topProductosData.nombres && topProductosData.nombres.length > 0) {
            const ctx2 = document.getElementById('productosChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: topProductosData.nombres,
                    datasets: [{
                        data: topProductosData.cantidades,
                        backgroundColor: ['#2575fc', '#6a11cb', '#f39c12', '#e74c3c', '#3498db', '#1abc9c']
                    }]
                },
                options: { responsive: true }
            });
        } else {
            // Opcional: Mostrar un mensaje si no hay datos
            const chartCanvas = document.getElementById('productosChart');
            if (chartCanvas) {
                const ctx2 = chartCanvas.getContext('2d');
                ctx2.font = '16px Poppins';
                ctx2.fillStyle = '#888';
                ctx2.textAlign = 'center';
                ctx2.fillText('No hay datos de productos para mostrar.', chartCanvas.width / 2, chartCanvas.height / 2);
            }
        }

        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        function applyTheme(theme) {
            if (theme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeToggle.innerHTML = '<span class="emoji">☀️</span>';
                localStorage.setItem('theme', 'dark');
            } else {
                body.removeAttribute('data-theme');
                themeToggle.innerHTML = '<span class="emoji">🌙</span>';
                localStorage.setItem('theme', 'light');
            }
        }

        function checkSavedTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme) {
                applyTheme(savedTheme);
            } else if (prefersDark) {
                applyTheme('dark');
            } else {
                applyTheme('light');
            }
        }

        function toggleTheme() {
            const isDark = body.getAttribute('data-theme') === 'dark';
            applyTheme(isDark ? 'light' : 'dark');
        }

        checkSavedTheme();
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
        themeToggle.addEventListener('click', toggleTheme);

        // Botón flotante
        const fabContainer = document.getElementById('fabContainer');
        document.addEventListener('click', (e) => {
            if (!fabContainer.contains(e.target) && !e.target.closest('.fab-main')) {
                fabContainer.classList.remove('active');
            }
        });

        // === Lógica para Sidebar Responsivo ===
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation(); // Evitar que el clic se propague al documento
                sidebar.classList.toggle('active');
            });

            // Opcional: cerrar el menú si se hace clic fuera de él
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('active') && !sidebar.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }

        // === Lógica para Gestión de Usuarios ===
        const userModal = document.getElementById('user-management-modal');
        const userFormModal = document.getElementById('user-form-modal');
        const userForm = document.getElementById('usuarioForm');
        const userFormTitle = document.getElementById('user-form-title');
        const userTableBody = document.getElementById('user-table-body');
        
        async function openUserModal() {
            if (!userModal) return;
            await loadUsers();
            userModal.style.display = 'flex';
        }

        function closeUserModal() {
            if (userModal) userModal.style.display = 'none';
        }

        async function loadUsers() {
            try {
                const response = await fetch('api/usuarios_ajax.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=get_users'
                });
                const data = await response.json();
                if (data.success) {
                    userTableBody.innerHTML = '';
                    data.usuarios.forEach(u => {
                        const row = `
                            <tr>
                                <td>${u.id}</td>
                                <td>${u.usuario}</td>
                                <td>${u.rol}</td>
                                <td>
                                    <button class="btn btn-edit" onclick="openUserForm(${u.id})"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-pass" onclick="openChangePasswordForm(${u.id})"><i class="fas fa-key"></i></button>
                                    <button class="btn btn-delete" onclick="deleteUser(${u.id})"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>`;
                        userTableBody.innerHTML += row;
                    });
                } else {
                    userTableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">${data.message}</td></tr>`;
                }
            } catch (error) {
                console.error('Error loading users:', error);
                userTableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">Error al cargar usuarios.</td></tr>`;
            }
        }

        async function openUserForm(id) {
            userForm.reset();
            document.getElementById('usuarioId').value = id || '';
            // Mostrar todos los campos para nuevo/editar
            document.getElementById('usuario').parentElement.style.display = 'block';
            document.getElementById('rol').parentElement.style.display = 'block';
            
            if (id) {
                userFormTitle.textContent = 'Editar Usuario';
                document.getElementById('password').placeholder = 'Dejar vacío para no cambiar';
                document.getElementById('password').required = false;
                
                const response = await fetch('api/usuarios_ajax.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=get_user&id=${id}`
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('usuario').value = data.usuario;
                    document.getElementById('rol').value = data.rol;
                }
            } else {
                userFormTitle.textContent = 'Nuevo Usuario';
                document.getElementById('password').placeholder = 'Contraseña requerida';
                document.getElementById('password').required = true;
            }
            userFormModal.style.display = 'flex';
        }

        function openChangePasswordForm(id) {
            userForm.reset();
            document.getElementById('usuarioId').value = id;
            userFormTitle.textContent = 'Cambiar Contraseña';
            
            // Ocultar campos no necesarios
            document.getElementById('usuario').parentElement.style.display = 'none';
            document.getElementById('rol').parentElement.style.display = 'none';
            
            document.getElementById('password').placeholder = 'Nueva contraseña';
            document.getElementById('password').required = true;
            userFormModal.style.display = 'flex';
        }

        function closeUserFormModal() {
            if (userFormModal) userFormModal.style.display = 'none';
        }

        async function handleUserFormSubmit(event) {
            event.preventDefault();
            const formData = new FormData(userForm);
            const id = formData.get('id');
            let action;

            // Determinar la acción basada en qué campos son visibles/requeridos
            if (document.getElementById('usuario').parentElement.style.display === 'none') {
                action = 'update_password';
            } else {
                action = id ? 'update_user' : 'add_user';
            }
            formData.append('action', action);

            try {
                const response = await fetch('api/usuarios_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire('Éxito', data.message, 'success');
                    closeUserFormModal();
                    loadUsers();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            }
        }

        async function deleteUser(id) {
             Swal.fire({
                title: '¿Estás seguro de eliminar a este usuario?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const response = await fetch('api/usuarios_ajax.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: `action=delete_user&id=${id}`
                        });
                        const data = await response.json();
                        if (data.success) {
                            Swal.fire('Eliminado', data.message, 'success');
                            loadUsers();
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (error) {
                         Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                    }
                }
            });
        }
    </script>
</body>
</html>
