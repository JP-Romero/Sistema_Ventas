<?php
session_start();
// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("<script>alert('Acceso denegado.'); window.location.href='productos.php';</script>");
}

include("../config/conexion.php");

$id = $_GET['id'] ?? null;
if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
    header("Location: productos.php?error=invalid_id");
    exit;
}

$id = (int)$id;
$mensaje = '';
$error = '';

try {
    $conexion->beginTransaction();

    // 1. Verificar si el producto tiene registros relacionados
    $tablas_relacionadas = [
        'inventario_venta' => 'ventas',
        'historial_ajustes' => 'ajustes de inventario',
        'detalles_compra' => 'compras'
        // Se eliminó 'devoluciones_venta' que no existe
    ];

    foreach ($tablas_relacionadas as $tabla => $nombre_amigable) {
        $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM {$tabla} WHERE producto_id = ?");
        $stmt_check->execute([$id]);
        if ($stmt_check->fetchColumn() > 0) {
            throw new Exception("No se puede eliminar el producto porque tiene {$nombre_amigable} asociados. Considere desactivarlo.");
        }
    }

    // 2. Obtener el nombre de la imagen para eliminar el archivo
    $stmt_img = $conexion->prepare("SELECT imagen FROM productos WHERE id = ?");
    $stmt_img->execute([$id]);
    $nombre_imagen = $stmt_img->fetchColumn();

    // 3. Eliminar el producto
    $stmt_delete = $conexion->prepare("DELETE FROM productos WHERE id = ?");
    $filas_afectadas = $stmt_delete->execute([$id]);

    if ($filas_afectadas === 0) {
        throw new Exception("El producto no fue encontrado o ya fue eliminado.");
    }

    // 4. Eliminar el archivo de imagen
    if ($nombre_imagen && file_exists("img/productos/" . $nombre_imagen)) {
        unlink("img/productos/" . $nombre_imagen);
    }

    $conexion->commit();
    $mensaje = "Producto eliminado permanentemente.";

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $error = "Error: " . $e->getMessage();
}

// Redirigir
if ($error) {
    header("Location: productos.php?error=" . urlencode($error));
} else {
    header("Location: productos.php?msg=" . urlencode($mensaje));
}
exit;
?>