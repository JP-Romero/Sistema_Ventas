<?php
include 'config/conexion.php';
header('Content-Type: application/json');

// Trae categorías únicas activas ordenadas alfabéticamente
$sql = "SELECT DISTINCT categoria FROM productos WHERE activo = 1 ORDER BY categoria ASC";
$stmt = $conexion->prepare($sql);
$stmt->execute();
$categorias = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($categorias);
