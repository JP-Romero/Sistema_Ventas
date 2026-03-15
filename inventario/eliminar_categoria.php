<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("../config/conexion.php");

$id = $_GET['id'] ?? null;
if (!$id || !filter_var($id, FILTER_VALIDATE_INT)) {
    header("Location: categorias.php?error=invalid_id");
    exit;
}

$id = (int)$id;
$error = '';
$mensaje = '';

try {
    // Verificar si alguna producto usa esta categoría
    $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id = ?");
    $stmt_check->execute([$id]);
    $count = $stmt_check->fetchColumn();

    if ($count > 0) {
        throw new Exception("No se puede eliminar la categoría porque está siendo utilizada por {$count} producto(s).");
    }

    // Si no hay productos, proceder a eliminar
    $stmt_delete = $conexion->prepare("DELETE FROM categorias WHERE id = ?");
    $stmt_delete->execute([$id]);

    if ($stmt_delete->rowCount() > 0) {
        $mensaje = "Categoría eliminada con éxito.";
    } else {
        throw new Exception("La categoría no fue encontrada o ya fue eliminada.");
    }

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Redirigir
if ($error) {
    header("Location: categorias.php?error=" . urlencode($error));
} else {
    header("Location: categorias.php?msg=" . urlencode($mensaje));
}
exit;
?>
