<?php
session_start();
require_once 'db.php';

// Mostrar errores (solo para desarrollo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$inicio = ($pagina - 1) * $por_pagina;

$categoria = $_GET['categoria'] ?? '';
$estado = $_GET['estado'] ?? '';
$nombre = $_GET['nombre'] ?? '';

$where = [];
$params = [];

if (!empty($categoria)) {
    $where[] = "categoria_id = ?";
    $params[] = $categoria;
}

if (!empty($estado)) {
    $where[] = "estado = ?";
    $params[] = $estado;
}

if (!empty($nombre)) {
    $where[] = "nombre LIKE ?";
    $params[] = "%$nombre%";
}

$condicion = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos $condicion");
    $stmt->execute($params);
    $total_productos = $stmt->fetchColumn();
    $total_paginas = ceil($total_productos / $por_pagina);

    $query = "SELECT *, c.nombre AS categoria_nombre 
              FROM productos p
              LEFT JOIN categorias c ON p.categoria_id = c.id
              $condicion
              ORDER BY p.nombre ASC
              LIMIT $por_pagina OFFSET $inicio";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'productos' => $productos,
        'total_paginas' => $total_paginas,
        'pagina_actual' => $pagina
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos: ' . $e->getMessage()]);
}