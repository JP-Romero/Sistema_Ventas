<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("config/conexion.php");

// Verifica que la conexión exista
if (!isset($conexion) || !$conexion instanceof PDO) {
    http_response_code(500);
    die("Error: No se pudo establecer la conexión con la base de datos.");
}

// === Generar el respaldo manualmente (sin mysqldump) ===

// Nombre del archivo
$fecha = date('Y-m-d_H-i-s');
$nombre_archivo = "respaldo_db_{$fecha}.sql";

// Cabeceras para descarga
header('Content-Type: application/sql; charset=utf-8');
header('Content-Transfer-Encoding: binary');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Iniciar salida
ob_start();
echo "-- Respaldo de Base de Datos\n";
echo "-- Generado el: " . date('Y-m-d H:i:s') . "\n";
echo "-- Base de datos: sistema_ventas\n";
echo "-- Host: localhost\n\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET AUTOCOMMIT = 0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = '+00:00';\n\n";

// Obtener todas las tablas
$stmt = $conexion->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // Estructura de la tabla
    $stmt = $conexion->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    echo "-- Estructura de la tabla `$table`\n";
    echo $row[1] . ";\n\n";

    // Datos de la tabla
    $stmt = $conexion->query("SELECT * FROM `$table`");
    $columns = $stmt->columnCount();
    $rows = $stmt->fetchAll(PDO::FETCH_NUM);

    if (count($rows) > 0) {
        echo "-- Datos de la tabla `$table`\n";
        echo "INSERT INTO `$table` VALUES\n";
        $values = [];
        foreach ($rows as $row) {
            $row_values = [];
            for ($i = 0; $i < $columns; $i++) {
                if ($row[$i] === null) {
                    $row_values[] = 'NULL';
                } else {
                    $row_values[] = "'" . $conexion->quote($row[$i]) . "'";
                }
            }
            $values[] = '(' . implode(',', $row_values) . ')';
        }
        echo implode(",\n", $values) . ";\n\n";
    }
}

echo "COMMIT;\n";
echo "SET FOREIGN_KEY_CHECKS = 1;\n";

// Enviar al navegador
$contenido = ob_get_clean();
echo $contenido;
exit;