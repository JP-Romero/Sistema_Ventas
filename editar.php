<?php
// Inicia la sesión PHP (si es necesario para la autenticación en esta página)
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
    // Podrías redirigir a una página de error o a la lista de productos
    header("Location: productos.php?error=no_id");
    exit;
}

// Consulta para obtener los datos del producto usando sentencia preparada
$producto = null;
$sql_select = "SELECT * FROM productos WHERE id = ?";
$stmt_select = mysqli_prepare($conexion, $sql_select);

if ($stmt_select) {
    mysqli_stmt_bind_param($stmt_select, "i", $id); // 'i' para integer
    mysqli_stmt_execute($stmt_select);
    $resultado_select = mysqli_stmt_get_result($stmt_select);
    $producto = mysqli_fetch_assoc($resultado_select);
    mysqli_stmt_close($stmt_select);
} else {
    echo "Error al preparar la consulta SELECT: " . mysqli_error($conexion);
    exit; // Detiene la ejecución si la consulta falla
}

// Si el producto no existe, redirige o muestra un error
if (!$producto) {
    header("Location: productos.php?error=producto_no_encontrado");
    exit;
}

// Procesa el formulario cuando se envía (método POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Obtiene los datos del formulario POST
    // Es crucial sanitizar y validar estos datos
    $nombre = htmlspecialchars(trim($_POST['nombre']));
    $precio = (float)$_POST['precio']; // Castea a float
    $stock = (int)$_POST['stock'];     // Castea a int

    // Prepara la consulta SQL para actualizar los datos de forma segura
    $sql_update = "UPDATE productos SET nombre = ?, precio = ?, stock = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conexion, $sql_update);

    if ($stmt_update) {
        // Vincula los parámetros a la declaración preparada
        // 's' para string, 'd' para double, 'i' para integer, 'i' para integer (ID)
        mysqli_stmt_bind_param($stmt_update, "sddi", $nombre, $precio, $stock, $id);

        // Ejecuta la declaración preparada
        if (mysqli_stmt_execute($stmt_update)) {
            // Redirige al usuario a la página de productos después de actualizar
            header("Location: productos.php?status=updated");
            exit(); // Es importante usar exit() después de header()
        } else {
            // Muestra un error si la ejecución falla
            echo "Error al actualizar el producto: " . mysqli_stmt_error($stmt_update);
        }

        // Cierra la declaración preparada
        mysqli_stmt_close($stmt_update);
    } else {
        // Muestra un error si la preparación de la declaración falla
        echo "Error al preparar la consulta UPDATE: " . mysqli_error($conexion);
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto</title>
    <!-- Enlace al archivo CSS para estilos de formularios (si existe) -->
    <link rel="stylesheet" type="text/css" href="css/formularios.css">
    <style>
        /* Estilos generales del cuerpo */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Contenedor del formulario */
        .formulario {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        /* Encabezado del formulario */
        h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 25px;
        }

        /* Mensaje de alerta de stock bajo */
        .alerta-stock-bajo {
            background-color: #fcecec;
            color: #e74c3c;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e74c3c;
        }

        /* Estilos para las etiquetas */
        label {
            display: block;
            text-align: left;
            margin-bottom: 8px;
            color: #555;
            font-weight: bold;
            font-size: 1.1em;
        }

        /* Estilos para los campos de entrada de texto y número */
        input[type="text"],
        input[type="number"] {
            width: calc(100% - 20px); /* Ancho completo menos padding */
            padding: 12px 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box; /* Incluye padding y borde en el ancho total */
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="number"]:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
            outline: none;
        }

        /* Estilos para el botón de enviar */
        input[type="submit"] {
            background-color: #3498db; /* Azul para actualizar */
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 15px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        input[type="submit"]:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);
        }

        /* Estilos responsivos */
        @media (max-width: 600px) {
            .formulario {
                padding: 20px;
                width: 95%;
            }
            h2 {
                font-size: 1.8em;
            }
            input[type="text"],
            input[type="number"] {
                width: calc(100% - 20px);
            }
            input[type="submit"] {
                padding: 10px 20px;
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="formulario">
        <h2>Editar Producto</h2>
        <?php
        // Mensaje de alerta de stock bajo, ahora dentro de las etiquetas PHP
        if ($producto['stock'] < 5) {
            echo "<div class='alerta-stock-bajo'>⚠️ Este producto tiene stock bajo. Actualiza el inventario.</div>";
        }
        ?>

        <form method="POST">
            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>

            <label for="precio">Precio:</label>
            <input type="number" step="0.01" name="precio" id="precio" value="<?php echo htmlspecialchars($producto['precio']); ?>" required>

            <label for="stock">Stock:</label>
            <input type="number" name="stock" id="stock" value="<?php echo htmlspecialchars($producto['stock']); ?>" required>

            <input type="submit" value="Actualizar">
        </form>
    </div>
</body>
</html>
