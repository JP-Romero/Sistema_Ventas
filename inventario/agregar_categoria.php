<?php
session_start();
header('Content-Type: application/json');

// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

include("config/conexion.php");

$response = ['success' => false, 'message' => 'Solicitud inválida.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $nombre = trim($_POST['nombre']);

    if (empty($nombre)) {
        $response['message'] = 'El nombre de la categoría no puede estar vacío.';
    } else {
        try {
            // Verificar si la categoría ya existe
            $stmt_check = $conexion->prepare("SELECT id FROM categorias WHERE nombre = ?");
            $stmt_check->execute([$nombre]);
            if ($stmt_check->fetch()) {
                $response['message'] = 'Esta categoría ya existe.';
            } else {
                $stmt_insert = $conexion->prepare("INSERT INTO categorias (nombre) VALUES (?)");
                $stmt_insert->execute([$nombre]);
                
                $new_id = $conexion->lastInsertId();
                
                $response = [
                    'success' => true,
                    'message' => 'Categoría agregada con éxito.',
                    'categoria' => [
                        'id' => $new_id,
                        'nombre' => $nombre
                    ]
                ];
            }
        } catch (PDOException $e) {
            $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);
exit;
?>
