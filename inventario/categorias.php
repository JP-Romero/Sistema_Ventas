<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("../config/conexion.php");

$mensaje = '';
$error = '';

// Manejar POST para agregar/actualizar categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar_categoria'])) {
        $nombre = trim($_POST['nombre']);
        if (!empty($nombre)) {
            try {
                $stmt = $conexion->prepare("INSERT INTO categorias (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
                $mensaje = "Categoría agregada con éxito.";
            } catch (PDOException $e) {
                $error = "Error al agregar la categoría: " . $e->getMessage();
            }
        } else {
            $error = "El nombre de la categoría no puede estar vacío.";
        }
    }
    
    if (isset($_POST['actualizar_categoria'])) {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        if (!empty($nombre) && $id > 0) {
            try {
                $stmt = $conexion->prepare("UPDATE categorias SET nombre = ? WHERE id = ?");
                $stmt->execute([$nombre, $id]);
                $mensaje = "Categoría actualizada con éxito.";
            } catch (PDOException $e) {
                $error = "Error al actualizar la categoría: " . $e->getMessage();
            }
        } else {
            $error = "Datos inválidos para actualizar.";
        }
    }
}

// Obtener todas las categorías para mostrarlas
try {
    $stmt = $conexion->query("SELECT * FROM categorias ORDER BY nombre ASC");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar las categorías: " . $e->getMessage();
    $categorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Categorías</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #333; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; border-bottom: 2px solid #2575fc; padding-bottom: 10px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 15px; background: #2575fc; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #1a5bc5; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-sm { font-size: 14px; padding: 6px 12px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #dee2e6; text-align: left; }
        .table th { background-color: #f8f9fa; }
        .form-section { margin-top: 30px; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; }
        .form-group { margin-bottom: 15px; }
        label { font-weight: 500; display: block; margin-bottom: 5px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .btn-volver {
            display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6c757d, #343a40);
            color: white; text-decoration: none; border-radius: 40px; font-weight: 500; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
       <a href="productos.php" class="btn" style="background-color: #6c757d;"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
        <h1><i class="fas fa-tags"></i> Gestionar Categorías</h1>

        <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Formulario para agregar nueva categoría -->
        <div class="form-section">
            <h2>Agregar Nueva Categoría</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="nombre">Nombre de la Categoría</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <button type="submit" name="agregar_categoria" class="btn">Agregar Categoría</button>
            </form>
        </div>

        <!-- Tabla de categorías existentes -->
        <div class="table-container" style="margin-top: 30px;">
            <h2>Categorías Existentes</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categorias)): ?>
                        <tr><td colspan="3" style="text-align:center;">No hay categorías registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td><?= $cat['id'] ?></td>
                                <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                <td>
                                    <a href="editar_categoria.php?id=<?= $cat['id'] ?>" class="btn btn-sm">Editar</a>
                                    <a href="eliminar_categoria.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar esta categoría?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
