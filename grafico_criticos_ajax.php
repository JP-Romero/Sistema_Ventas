<?php
include("conexion.php");

$nombre = isset($_GET['nombre']) ? mysqli_real_escape_string($conexion, $_GET['nombre']) : '';
$min = isset($_GET['min']) ? floatval($_GET['min']) : 0;
$max = isset($_GET['max']) ? floatval($_GET['max']) : 99999;

$consulta = mysqli_query($conexion, "
  SELECT nombre, stock FROM productos
  WHERE nombre LIKE '%$nombre%' AND precio >= $min AND precio <= $max
    AND stock < 5
  ORDER BY stock ASC
  LIMIT 20
");

$data = [];
while ($p = mysqli_fetch_assoc($consulta)) {
  $data[] = ["nombre" => $p['nombre'], "stock" => $p['stock']];
}

echo json_encode($data);
?>
