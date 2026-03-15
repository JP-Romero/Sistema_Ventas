<?php
session_start();
header('Content-Type: application/json');

// Objeto de respuesta estándar
$response = ['success' => false, 'products' => [], 'message' => ''];

// 1. Verificar sesión de usuario
if (!isset($_SESSION['usuario'])) {
    $response['message'] = 'Error: Acceso no autorizado.';
    echo json_encode($response);
    exit;
}

// 2. Incluir conexión a la base de datos
include("../config/conexion.php");

// 3. Obtener y decodificar los datos de entrada
$input = json_decode(file_get_contents('php://input'), true);
$ids = $input['ids'] ?? [];

// 4. Validar los IDs
if (empty($ids) || !is_array($ids)) {
    $response['message'] = 'Error: No se proporcionaron IDs de productos.';
    echo json_encode($response);
    exit;
}

// Filtrar para asegurarse de que todos los IDs son enteros
$sanitized_ids = array_filter($ids, 'is_numeric');
$sanitized_ids = array_map('intval', $sanitized_ids);

if (empty($sanitized_ids)) {
    $response['message'] = 'Error: IDs de productos no válidos.';
    echo json_encode($response);
    exit;
}

try {
    // 5. Preparar la consulta SQL
    // Crear placeholders (?) para la cláusula IN
    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
    
    $sql = "SELECT id, nombre FROM productos WHERE id IN ($placeholders)";
    
    $stmt = $conexion->prepare($sql);
    
    // 6. Ejecutar la consulta
    $stmt->execute($sanitized_ids);
    
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 7. Preparar y enviar la respuesta exitosa
    $response['success'] = true;
    $response['products'] = $productos;
    $response['message'] = 'Productos obtenidos correctamente.';
    
} catch (PDOException $e) {
    // Manejo de errores de la base de datos
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    // En un entorno de producción, sería mejor loguear el error y mostrar un mensaje genérico
    // error_log($e->getMessage());
    // $response['message'] = 'Ocurrió un error al consultar la base de datos.';
}

echo json_encode($response);
?>
