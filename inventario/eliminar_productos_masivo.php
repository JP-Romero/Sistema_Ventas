<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

include("../config/conexion.php");

$response = ['success' => false, 'message' => 'Solicitud inválida.'];
$data = json_decode(file_get_contents('php://input'), true);
$ids_a_eliminar = $data['ids'] ?? [];

if (empty($ids_a_eliminar) || !is_array($ids_a_eliminar)) {
    $response['message'] = 'No se proporcionaron IDs para eliminar.';
    echo json_encode($response);
    exit;
}

$eliminados_count = 0;
$fallidos_count = 0;
$fallidos_detalles = [];

$tablas_relacionadas = [
    'inventario_venta' => 'ventas',
    'historial_ajustes' => 'ajustes de inventario',
    'detalles_compra' => 'compras'
];

foreach ($ids_a_eliminar as $id) {
    if (!filter_var($id, FILTER_VALIDATE_INT)) {
        $fallidos_count++;
        $fallidos_detalles[] = "ID no válido: " . htmlspecialchars($id);
        continue;
    }
    $id = (int)$id;
    $se_puede_eliminar = true;

    try {
        $conexion->beginTransaction();

        // 1. Verificar dependencias
        foreach ($tablas_relacionadas as $tabla => $nombre_amigable) {
            $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM {$tabla} WHERE producto_id = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                $se_puede_eliminar = false;
                $fallidos_detalles[] = "ID {$id}: No se puede eliminar, tiene {$nombre_amigable} asociados.";
                break; // Salir del bucle de tablas si ya se encontró una dependencia
            }
        }

        if ($se_puede_eliminar) {
            // 2. Obtener nombre de imagen para borrar archivo
            $stmt_img = $conexion->prepare("SELECT imagen FROM productos WHERE id = ?");
            $stmt_img->execute([$id]);
            $nombre_imagen = $stmt_img->fetchColumn();

            // 3. Eliminar producto
            $stmt_delete = $conexion->prepare("DELETE FROM productos WHERE id = ?");
            $stmt_delete->execute([$id]);

            // 4. Eliminar archivo de imagen
            if ($nombre_imagen && file_exists("img/productos/" . $nombre_imagen)) {
                unlink("img/productos/" . $nombre_imagen);
            }
            
            $eliminados_count++;
        } else {
            $fallidos_count++;
        }

        $conexion->commit();

    } catch (Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $fallidos_count++;
        $fallidos_detalles[] = "ID {$id}: Error de base de datos - " . $e->getMessage();
    }
}

$response['success'] = true;
$response['message'] = "Proceso completado.";
$response['results'] = [
    'eliminados' => $eliminados_count,
    'fallidos' => $fallidos_count,
    'detalles' => $fallidos_detalles
];

echo json_encode($response);
exit;
?>