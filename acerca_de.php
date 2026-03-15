<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
include("config/conexion.php");

// === Función para sanitizar texto ===
function sanitizeText($text) {
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8');
    }
    $text = normalizer_normalize($text, Normalizer::FORM_C);
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

// === Obtener datos del usuario ===
try {
    $stmt = $conexion->prepare("SELECT nombre FROM usuarios WHERE usuario = ?");
    $stmt->execute([$_SESSION['usuario']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $nombre_usuario = $user['nombre'] ?? $_SESSION['usuario'];
} catch (Exception $e) {
    $nombre_usuario = $_SESSION['usuario'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca de - Sistema de Ventas</title>
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
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card: #1f1f1f;
            --text: #e0e0e0;
            --border: #333;
            --accent: #4a90e2;
            --accent-hover: #3a7bc8;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 30px;
            transition: all 0.4s;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 30px;
        }
        .header {
            background: var(--accent);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 25px;
        }
        .section h2 {
            color: var(--accent);
            font-size: 18px;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 6px;
            display: inline-block;
        }
        .section ul {
            padding-left: 20px;
            margin: 10px 0;
        }
        .section li {
            margin-bottom: 6px;
        }
        .tech-item {
            display: inline-block;
            background: var(--bg);
            padding: 6px 12px;
            margin: 4px;
            border-radius: 8px;
            font-size: 14px;
            border: 1px solid var(--border);
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: #666;
            font-size: 14px;
        }
        .btn-volver {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), #6a11cb);
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
            margin-top: 20px;
        }
        .btn-volver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
            background: linear-gradient(135deg, var(--accent-hover), #5a0fb0);
        }
        .btn-volver i {
            margin-right: 6px;
            transition: transform 0.3s;
        }
        .btn-volver:hover i {
            transform: translateX(-2px);
        }
        .highlight {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 15px 0;
            font-size: 14px;
            color: #e65100;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-info-circle"></i> Acerca del Sistema</h1>
        </div>

        <div class="content">
            <div class="section">
                <h2><i class="fas fa-box"></i> Sistema de Gestión de Ventas</h2>
                <p>Una solución integral para la administración de ventas, inventario, clientes y usuarios, diseñada para pequeñas y medianas empresas.</p>
                <div class="highlight">
                    <strong>Versión 1.0</strong> - Desarrollado para eficiencia, seguridad y facilidad de uso.
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-star"></i> Funcionalidades Principales</h2>
                <ul>
                    <li><strong>Punto de Venta:</strong> Carrito dinámico, +/– de cantidades, descuentos, IVA opcional y múltiples métodos de pago.</li>
                    <li><strong>Inventario:</strong> Gestión de productos, ajuste de stock, búsqueda en tiempo real y alertas de bajo stock.</li>
                    <li><strong>Clientes y Usuarios:</strong> Registro, edición y gestión con roles y permisos.</li>
                    <li><strong>Auditoría:</strong> Historial de actividades y generación de informes en PDF.</li>
                    <li><strong>Reportes:</strong> Gráficos de ventas semanales y productos más vendidos.</li>
                    <li><strong>Modo Oscuro:</strong> Interfaz con tema claro/oscuro persistente.</li>
                    <li><strong>App Móvil:</strong> Compatible como PWA (instalable en Android).</li>
                </ul>
            </div>

            <div class="section">
                <h2><i class="fas fa-cogs"></i> Tecnologías Utilizadas</h2>
                <p>Construido con tecnologías web modernas y seguras:</p>
                <div style="margin: 15px 0;">
                    <span class="tech-item">PHP 8+</span>
                    <span class="tech-item">MySQL / PDO</span>
                    <span class="tech-item">HTML5 / CSS3</span>
                    <span class="tech-item">JavaScript (ES6)</span>
                    <span class="tech-item">TCPDF</span>
                    <span class="tech-item">Chart.js</span>
                    <span class="tech-item">SweetAlert2</span>
                    <span class="tech-item">Font Awesome</span>
                </div>
            </div>

            <div class="section">
                <h2><i class="fas fa-lock"></i> Seguridad</h2>
                <ul>
                    <li>Inicio de sesión seguro con sesiones PHP.</li>
                    <li>Permisos por roles y acciones.</li>
                    <li>Sanitización de entradas con `htmlspecialchars` y `normalizer_normalize`.</li>
                    <li>Consultas preparadas con PDO para prevenir inyecciones SQL.</li>
                    <li>Acceso restringido a archivos sensibles.</li>
                </ul>
            </div>

            <div class="section">
                <h2><i class="fas fa-mobile-alt"></i> ¿Puede ser una App Android?</h2>
                <p>Sí, este sistema puede instalarse como una <strong>App Web Progresiva (PWA)</strong> en dispositivos Android desde Chrome, sin necesidad de Google Play.</p>
                <p><strong>Beneficios:</strong> Instalación fácil, acceso directo en pantalla, funcionamiento offline (con configuración adicional).</p>
            </div>

            <div class="section">
                <h2><i class="fas fa-heart"></i> Desarrollado con ❤️</h2>
                <p>Este sistema fue optimizado y mejorado para brindar una experiencia de usuario fluida, profesional y eficiente.</p>
                <p><strong>Objetivo:</strong> Que gestionar tu negocio sea fácil, rápido y seguro.</p>
            </div>

            <div class="footer">
                <p>© <?= date('Y') ?> Sistema de Ventas. Todos los derechos reservados.</p>
                <p>Desarrollado por <strong><?= sanitizeText($nombre_usuario) ?></strong> Ingeniero en Sistemas</p>
            </div>

            <a href="panel.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
</body>
</html>