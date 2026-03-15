<?php
// Inicia la sesión PHP
session_start();
// Incluye el archivo de conexión a la base de datos
include("conexion.php");

// Verifica si el usuario ha iniciado sesión. Si no, redirige a la página de login.
// Asumiendo que 'usuario' es la variable de sesión para el login.
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
if (!$usuario) {
    header("Location: login.php");
    exit;
}

// Obtiene el ID del producto de la URL. Es crucial sanitizarlo.
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Castea a int para seguridad inicial

// Si no se proporciona un ID válido, redirige o muestra un error
if ($id === 0) {
    header("Location: productos.php?error=id_invalido_eliminar");
    exit;
}

// Prepara la consulta SQL para eliminar el producto de forma segura
$sql_delete = "DELETE FROM productos WHERE id = ?";
$stmt_delete = mysqli_prepare($conexion, $sql_delete);

if ($stmt_delete) {
    // Vincula el parámetro ID a la declaración preparada
    // 'i' para integer
    mysqli_stmt_bind_param($stmt_delete, "i", $id);

    // Ejecuta la declaración preparada
    if (mysqli_stmt_execute($stmt_delete)) {
        // Verifica si se eliminó alguna fila
        if (mysqli_stmt_affected_rows($stmt_delete) > 0) {
            // Redirige al usuario a la página de productos con un mensaje de éxito
            header("Location: productos.php?status=deleted_success");
        } else {
            // Si no se afectó ninguna fila, el producto no fue encontrado
            header("Location: productos.php?error=producto_no_encontrado_eliminar");
        }
        exit(); // Es importante usar exit() después de header()
    } else {
        // Muestra un error si la ejecución falla
        echo "Error al eliminar el producto: " . mysqli_stmt_error($stmt_delete);
    }

    // Cierra la declaración preparada
    mysqli_stmt_close($stmt_delete);
} else {
    // Muestra un error si la preparación de la declaración falla
    echo "Error al preparar la consulta DELETE: " . mysqli_error($conexion);
}

// En caso de que algo falle antes de la redirección, se puede mostrar un mensaje.
// Este HTML es mínimo ya que la intención es redirigir rápidamente.
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminando Producto...</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f2f5;
            color: #333;
            font-size: 1.2em;
        }
        .message {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="message">
        <p>Procesando eliminación...</p>
        <p>Si no eres redirigido, algo salió mal.</p>
    </div>
</body>
</html>
