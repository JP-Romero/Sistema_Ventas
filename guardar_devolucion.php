<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("config/conexion.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: devoluciones.php");
    exit;
}

$venta_id = (int)$_POST['venta_id'];
$cantidades = $_POST['cantidades'];
$precios = $_POST['precios'];
$motivo = trim($_POST['motivo']);
$usuario_nombre = $_SESSION['usuario'];
$total_devolucion = 0;

if (empty($venta_id) || empty($cantidades) || empty($motivo)) {
    header("Location: devoluciones.php?error=" . urlencode("Faltan datos para procesar la devolución."));
    exit;
}

try {
    $conexion->beginTransaction();

    // 1. Crear el registro maestro de la devolución
    // Primero calculamos el total
    foreach ($cantidades as $producto_id => $cantidad) {
        $cantidad_devuelta = (int)$cantidad;
        if ($cantidad_devuelta > 0) {
            $total_devolucion += $cantidad_devuelta * (float)$precios[$producto_id];
        }
    }

    if ($total_devolucion <= 0) {
        throw new Exception("No se seleccionó ninguna cantidad para devolver.");
    }

    $stmt_dev = $conexion->prepare("INSERT INTO devoluciones (venta_id, total, usuario, motivo, fecha) VALUES (?, ?, ?, ?, NOW())");
    $stmt_dev->execute([$venta_id, $total_devolucion, $usuario_nombre, $motivo]);
    $devolucion_id = $conexion->lastInsertId();

    // 2. Crear los detalles de la devolución y actualizar stock
    $stmt_det = $conexion->prepare("INSERT INTO detalles_devolucion (devolucion_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_stock = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");

    foreach ($cantidades as $producto_id => $cantidad) {
        $cantidad_devuelta = (int)$cantidad;
        if ($cantidad_devuelta > 0) {
            $precio_unitario = (float)$precios[$producto_id];
            // Insertar detalle de la devolución
            $stmt_det->execute([$devolucion_id, $producto_id, $cantidad_devuelta, $precio_unitario]);
            // Actualizar el stock del producto
            $stmt_stock->execute([$cantidad_devuelta, $producto_id]);
        }
    }

    $conexion->commit();
    header("Location: devoluciones.php?msg=" . urlencode("Devolución #{$devolucion_id} procesada con éxito. Total reembolsado: C$ " . number_format($total_devolucion, 2)));
    exit;

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    header("Location: crear_devolucion.php?venta_id={$venta_id}&error=" . urlencode("Error al procesar: " . $e->getMessage()));
    exit;
}
?>