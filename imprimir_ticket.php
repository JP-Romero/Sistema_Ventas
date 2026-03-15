<?php
// Configuración básica
header('Content-Type: text/html; charset=UTF-8');
session_start();

// Protección de acceso
if (!isset($_SESSION['usuario'])) {
    die('<h3>No autorizado</h3><p>Acceso denegado.</p>');
}

include("config/conexion.php");

// Configurar UTF-8
$conexion->exec("SET NAMES 'utf8mb4'");
$conexion->exec("SET CHARACTER SET utf8mb4");
$conexion->exec("SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci'");

// Obtener ID de venta
$venta_id = $_GET['id'] ?? null;

if (!$venta_id || !is_numeric($venta_id)) {
    die('<h3>Error</h3><p>ID de venta no válido.</p>');
}

try {
    // Obtener datos de la venta
    $stmt = $conexion->prepare("
        SELECT v.id, v.cliente, v.total, v.metodo_pago, v.fecha, v.descuento
        FROM ventas v 
        WHERE v.id = ?
    ");
    $stmt->execute([$venta_id]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        die('<h3>Error</h3><p>Venta no encontrada.</p>');
    }

    // Obtener productos de la venta
    $stmt = $conexion->prepare("
        SELECT p.nombre, iv.cantidad, iv.precio 
        FROM inventario_venta iv 
        JOIN productos p ON iv.producto_id = p.id 
        WHERE iv.venta_id = ?
    ");
    $stmt->execute([$venta_id]);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear fecha
    $fecha = new DateTime($venta['fecha']);
    $fechaFormateada = $fecha->format('d/m/Y H:i:s');

} catch (Exception $e) {
    error_log("Error al cargar ticket: " . $e->getMessage());
    die('<h3>Error</h3><p>No se pudo cargar el ticket.</p>');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket de Venta #<?php echo $venta['id']; ?></title>
    <style>
        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
            }
            body {
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            max-width: 80mm;
            margin: 10px auto;
            padding: 10px;
            background: #f4f4f4;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .ticket {
            background: white;
            padding: 10px;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .ticket-header {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .ticket-divider {
            text-align: center;
            margin: 8px 0;
            font-weight: bold;
        }

        .item {
            display: grid;
            grid-template-columns: 8ch 1fr 3ch 6ch;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .footer {
            margin-top: 10px;
            font-size: 13px;
        }

        .btn-print {
            display: block;
            width: 100%;
            padding: 12px;
            background: #2575fc;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            text-align: center;
        }

        .btn-print:hover {
            background: #1a5bc5;
        }
    </style>
</head>
<body onload="window.print()">
    <!-- Botón para imprimir (solo visible en pantalla) -->
    <button class="btn-print no-print" onclick="window.print();">
        🖨️ Imprimir Ticket
    </button>

    <!-- Ticket -->
    <div class="ticket">
        <div class="ticket-header">
            FARMACIA FUENTES
        </div>
        <div style="text-align: center; font-size: 12px;">
            Granada, Nicaragua
        </div>
        <div class="ticket-divider">
            --------------------
        </div>

        <div><strong>VENTA #: <?php echo str_pad($venta['id'], 6, '0', STR_PAD_LEFT); ?></strong></div>
        <div>Fecha: <?php echo $fechaFormateada; ?></div>
        <div>Cliente: <?php echo htmlspecialchars($venta['cliente']); ?></div>
        <div>Método: <?php echo ucfirst($venta['metodo_pago']); ?></div>

        <div class="ticket-divider">
            --------------------
        </div>

        <!-- Productos -->
        <div class="items">
            <?php foreach ($productos as $p): ?>
                <div class="item">
                    <span><?php echo $p['cantidad']; ?>x</span>
                    <span><?php echo htmlspecialchars($p['nombre']); ?></span>
                    <span>@</span>
                    <span>C$<?php echo number_format($p['precio'], 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ticket-divider">
            --------------------
        </div>

        <!-- Totales -->
        <div class="footer">
            Subtotal:     C$<?php echo number_format($venta['total'] / 1.15, 2); ?>
            <?php if ($venta['descuento'] > 0): ?>
            Descuento:    -C$<?php echo number_format($venta['total'] * ($venta['descuento']/100) / 1.15, 2); ?>
            <?php endif; ?>
            IVA (15%):    C$<?php echo number_format($venta['total'] - ($venta['total'] / 1.15), 2); ?>
            <div style="font-weight: bold; font-size: 16px; margin-top: 5px;">
                TOTAL:        C$<?php echo number_format($venta['total'], 2); ?>
            </div>
        </div>

        <div class="ticket-divider">
            --------------------
        </div>
        <div style="text-align: center; font-size: 12px; margin-top: 10px;">
            ¡Gracias por su compra!
        </div>
    </div>
</body>
</html>