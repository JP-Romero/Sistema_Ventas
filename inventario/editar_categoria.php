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

// Obtener datos de la categoría
try {
    $stmt = $conexion->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$categoria) {
        throw new Exception("Categoría no encontrada.");
    }
} catch (Exception $e) {
    header("Location: categorias.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #333; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 15px; background: #2575fc; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: 500; display: block; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
        .btn-volver {
            display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6c757d, #343a40);
            color: white; text-decoration: none; border-radius: 40px; font-weight: 500; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="categorias.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver</a>
        <h1><i class="fas fa-edit"></i> Editar Categoría</h1>

        <form action="categorias.php" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($categoria['id']) ?>">
            <div class="form-group">
                <label for="nombre">Nombre de la Categoría</label>
                <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($categoria['nombre']) ?>" required>
            </div>
            <button type="submit" name="actualizar_categoria" class="btn">Actualizar Categoría</button>
        </form>
    </div>
</body>
</html>
