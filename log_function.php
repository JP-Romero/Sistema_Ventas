<?php
if (!function_exists('registrar_actividad')) {
    function registrar_actividad($conexion, $usuario_id, $accion, $descripcion) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
            
            $stmt = $conexion->prepare(
                "INSERT INTO historial_actividades (usuario_id, accion, descripcion, ip, fecha) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$usuario_id, $accion, $descripcion, $ip]);
        } catch (PDOException $e) {
            // En un caso real, podrías loguear este error a un archivo en lugar de ignorarlo.
            // Por ejemplo: error_log("Error al registrar actividad: " . $e->getMessage());
        }
    }
}
?>
