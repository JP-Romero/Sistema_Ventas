<?php
session_start();
// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado. Solo los administradores pueden ejecutar este script.");
}

include('config/conexion.php');

$output = '';
$error_message = '';

// Lógica de reindexación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceder'])) {
    if (isset($_POST['confirmacion']) && $_POST['confirmacion'] === 'CONFIRMAR') {
        
        // Inicia el buffer de salida para capturar los echos
        ob_start();

        echo "<h2>Proceso de Reindexación Iniciado</h2>";

        $transaction_success = false;
        $id_map = []; // Definir fuera del try para que esté disponible después

        try {
            // Iniciar transacción
            $conexion->beginTransaction();
            echo "<p>Iniciando transacción...</p>";

            // Deshabilitar temporalmente las restricciones de clave foránea
            $conexion->exec('SET FOREIGN_KEY_CHECKS=0;');
            echo "<p>Restricciones de clave foránea desactivadas temporalmente.</p>";

            // 1. Obtener todos los productos y crear el mapa de IDs
            $stmt = $conexion->query('SELECT id FROM productos ORDER BY id ASC');
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $new_id = 1;
            foreach ($productos as $producto) {
                $id_map[$producto['id']] = $new_id;
                $new_id++;
            }
            echo "<p>Mapa de IDs creado. " . count($id_map) . " productos para reindexar.</p>";

            // 2. Actualizar la tabla de productos en dos pasadas
            echo "<p>Iniciando Pasada 1: Actualizando IDs en `productos` a valores temporales...</p>";
            $update_stmt_temp = $conexion->prepare('UPDATE productos SET id = ? WHERE id = ?');
            foreach ($id_map as $old_id => $new_id_val) {
                $update_stmt_temp->execute([-$old_id, $old_id]);
            }
            echo "<p>Pasada 1 completada.</p>";

            echo "<p>Iniciando Pasada 2: Actualizando IDs en `productos` a valores finales...</p>";
            $update_stmt_final = $conexion->prepare('UPDATE productos SET id = ? WHERE id = ?');
            foreach ($id_map as $old_id => $new_id_val) {
                $update_stmt_final->execute([$new_id_val, -$old_id]);
            }
            echo "<p>Pasada 2 completada. Tabla 'productos' actualizada.</p>";

            // 3. Actualizar tablas relacionadas
            $related_tables = ['historial_ajustes', 'inventario_venta', 'detalles_compra', 'devoluciones_venta'];
            echo "<p>Actualizando tablas relacionadas...</p>";
            foreach ($related_tables as $table) {
                try {
                    $conexion->query("SELECT 1 FROM {$table} LIMIT 1");
                } catch (PDOException $e) {
                    echo "<ul><li><span style='color: orange;'>Advertencia:</span> Tabla '{$table}' no encontrada, se omite.</li></ul>";
                    continue;
                }
                echo "<ul><li>Actualizando tabla '{$table}'...</li></ul>";
                $update_related_stmt = $conexion->prepare("UPDATE {$table} SET producto_id = ? WHERE producto_id = ?");
                foreach ($id_map as $old_id => $new_id_val) {
                    $update_related_stmt->execute([$new_id_val, $old_id]);
                }
            }
            echo "<p>Tablas relacionadas actualizadas.</p>";

            // Habilitar de nuevo las restricciones de clave foránea
            $conexion->exec('SET FOREIGN_KEY_CHECKS=1;');
            echo "<p>Restricciones de clave foránea reactivadas.</p>";

            // Confirmar la transacción
            $conexion->commit();
            $transaction_success = true; // Marcar como éxito
            echo "<div class='message success'><strong>¡Éxito!</strong> La transacción de reindexación de datos se completó.</div>";

        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $conexion->exec('SET FOREIGN_KEY_CHECKS=1;');
            echo "<div class='message error'><strong>Error:</strong> Ocurrió un problema durante la transacción. Se revirtieron todos los cambios.</div>";
            echo "<pre>Detalles del error: " . htmlspecialchars($e->getMessage()) . "</pre>";
        }

        // 4. Resetear el AUTO_INCREMENT (fuera de la transacción)
        if ($transaction_success) {
            try {
                $max_id = count($id_map);
                $next_id = $max_id > 0 ? $max_id + 1 : 1;
                $conexion->exec("ALTER TABLE productos AUTO_INCREMENT = {$next_id};");
                echo "<p>AUTO_INCREMENT de la tabla 'productos' reseteado a {$next_id}.</p>";
                echo "<div class='message success'><strong>¡Proceso finalizado con éxito!</strong></div>";
            } catch (Exception $e) {
                echo "<div class='message error'><strong>Advertencia:</strong> Los datos se reindexaron correctamente, pero falló el reseteo del AUTO_INCREMENT. Puede ignorar este error o corregirlo manualmente.</div>";
                echo "<pre>Detalles: " . htmlspecialchars($e->getMessage()) . "</pre>";
            }
        }
        
        $output = ob_get_clean();

    } else {
        $error_message = "La confirmación no es correcta. Por favor, escriba 'CONFIRMAR' para proceder.";
    }
}
?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Reindexar Productos</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; color: #333; line-height: 1.6; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #2575fc; padding-bottom: 10px; }
        .btn { display: inline-block; background: linear-gradient(135deg, #6a11cb, #2575fc); color: white; padding: 10px 20px; text-decoration: none; border-radius: 40px; font-weight: 500; border: none; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-danger { background: linear-gradient(135deg, #d32f2f, #e74c3c); }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; border-left: 5px solid; }
        .success { background-color: #d4edda; color: #155724; border-color: #28a745; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #dc3545; }
        .warning { background-color: #fff3cd; color: #856404; border-color: #ffc107; }
        pre { background-color: #e9ecef; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; border: 1px solid #dee2e6; }
        input[type='text'] { padding: 10px; width: 220px; border-radius: 5px; border: 1px solid #ccc; font-size: 16px; }
        .process-output { margin-top: 20px; padding: 15px; border: 1px solid #dee2e6; border-radius: 8px; }
    </style>
</head>
<body>
<div class='container'>
    <h1><i class='fas fa-sync-alt'></i> Reindexar IDs de Productos</h1>
    
    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="message warning"><strong>¡Atención!</strong> Este proceso es irreversible y modificará permanentemente los IDs de la tabla de productos y todas las tablas relacionadas. <strong>Se recomienda encarecidamente hacer una copia de seguridad de la base de datos antes de continuar.</strong></div>

    <?php if (!$output): ?>
    <form method='post'>
        <p>Para confirmar la reindexación, escriba <strong>CONFIRMAR</strong> en el campo de abajo y presione el botón.</p>
        <input type='text' name='confirmacion' placeholder='Escriba CONFIRMAR'>
        <br>
        <button type='submit' name='proceder' class='btn btn-danger' style='margin-top: 10px;'>Proceder con la Reindexación</button>
    </form>
    <?php endif; ?>

    <?php if ($output): ?>
        <div class="process-output">
            <?php echo $output; ?>
        </div>
    <?php endif; ?>

    <a href='panel.php' class='btn' style='background: #6c757d; margin-top: 20px;'>Volver al Panel</a>

</div>
</body>
</html>
