<?php
include 'config/conexion.php';

header('Content-Type: application/json');

$usuario = 'juan'; // o puedes obtenerlo desde sesión

$stmt = $conexion->query("SELECT id, nombre, imagen FROM productos");
$productos = [];

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['imagen'] = 'img/productos/' . $row['imagen']; // ruta correcta
    $productos[] = $row;
}
date_default_timezone_set('America/Managua');
echo json_encode([
    'usuario' => $usuario,
    'fecha_hora' => date('d-m-Y h:i:s A')
]);

?>
