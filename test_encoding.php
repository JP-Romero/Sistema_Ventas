<?php
// Incluir la conexión, que ya debería tener la cabecera UTF-8
include("config/conexion.php");

echo "<h1>Prueba de Codificación de Caracteres</h1>";

// --- Prueba 1: Cadena de texto literal ---
$cadena_literal = "Prueba con acentos y caracteres especiales: á, é, í, ó, ú, Ñ, ¿, ¡";
echo "<h2>Prueba 1: Cadena de texto escrita en el archivo</h2>";
echo "<p>" . $cadena_literal . "</p>";
echo "<hr>";

// --- Prueba 2: Datos desde la Base de Datos ---
echo "<h2>Prueba 2: Datos obtenidos de la Base de Datos</h2>";
try {
    $stmt = $conexion->query("SELECT nombre FROM productos ORDER BY id LIMIT 1");
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
        $nombre_producto = $producto['nombre'];
        echo "<p>Nombre del primer producto: <strong>" . htmlspecialchars($nombre_producto) . "</strong></p>";

        // --- Verificación explícita de la codificación ---
        echo "<h3>Verificación interna de la codificación:</h3>";
        if (mb_check_encoding($nombre_producto, 'UTF-8')) {
            echo "<p style='color:green;'>La cadena del producto ('" . htmlspecialchars($nombre_producto) . "') ES válida en UTF-8.</p>";
        } else {
            echo "<p style='color:red;'>La cadena del producto ('" . htmlspecialchars($nombre_producto) . "') NO ES válida en UTF-8.</p>";
        }
    } else {
        echo "<p>No se encontraron productos en la base de datos.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error al conectar o consultar la base de datos: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p>Si todos los caracteres en este texto se ven correctamente, la configuración del servidor y la conexión a la base de datos son correctas.</p>";
?>
