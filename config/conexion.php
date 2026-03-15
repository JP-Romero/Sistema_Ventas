<?php
header('Content-Type: text/html; charset=UTF-8');

function registrarActividad($conexion, $accion, $descripcion = '', $tabla_afectada = null, $id_registro = null) {
    if (!isset($_SESSION['usuario'])) return;

    try {
        // Obtener ID del usuario
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmt->execute([$_SESSION['usuario']]);
        $usuario = $stmt->fetch();

        if (!$usuario) return;

        $usuario_id = $usuario['id'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $navegador = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

        $stmt = $conexion->prepare("
            INSERT INTO historial_actividades 
            (usuario_id, accion, descripcion, tabla_afectada, id_registro, ip, navegador) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $accion, $descripcion, $tabla_afectada, $id_registro, $ip, $navegador]);
    } catch (PDOException $e) {
        error_log("Error al registrar actividad: " . $e->getMessage());
    }
}
date_default_timezone_set('America/Managua');

if (file_exists(__DIR__ . '/../.env')) {
    $env = parse_ini_file(__DIR__ . '/../.env');
    $DB_HOST = $env['DB_HOST'] ?? 'localhost';
    $DB_NAME = $env['DB_NAME'] ?? 'sistema_ventas';
    $DB_USER = $env['DB_USER'] ?? 'root';
    $DB_PASS = $env['DB_PASS'] ?? '';
} else {
    $DB_HOST = 'localhost';
    $DB_NAME = 'sistema_ventas';
    $DB_USER = 'root';
    $DB_PASS = '';
}

try {
    $options = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET time_zone = 'America/Managua'",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];

    $conexion = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    error_log("[ERROR DB] " . $e->getMessage());
    die("❌ Error de conexión con la base de datos.");
}

?>
