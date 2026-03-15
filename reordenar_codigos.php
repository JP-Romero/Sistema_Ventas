<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado. Solo los administradores pueden ejecutar este script.");
}

include('config/conexion.php');

$output = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceder'])) {
    if (isset($_POST['confirmacion']) && $_POST['confirmacion'] === 'REORDENAR') {
        
        ob_start();
        echo "<h2>Proceso de Reordenación de Códigos Iniciado</h2>";
        
        try {
            $conexion->beginTransaction();
            echo "<p>Iniciando transacción...</p>";

            $stmt = $conexion->query('SELECT id FROM productos ORDER BY id ASC');
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p>" . count($productos) . " productos para reordenar.</p>";

            $update_stmt = $conexion->prepare('UPDATE productos SET codigo = ? WHERE id = ?');
            
            $counter = 1;
            foreach ($productos as $producto) {
                $new_code = sprintf("PROD-%03d", $counter);
                $update_stmt->execute([$new_code, $producto['id']]);
                $counter++;
            }
            
            echo "<p>¡Códigos actualizados exitosamente!</p>";

            $conexion->commit();
            echo "<div class='message success'><strong>¡Éxito!</strong> La reordenación se completó correctamente.</div>";

        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            echo "<div class='message error'><strong>Error:</strong> Ocurrió un problema durante la reordenación. Se revirtieron todos los cambios.</div>";
            echo "<pre>Detalles del error: " . htmlspecialchars($e->getMessage()) . "</pre>";
        }
        
        $output = ob_get_clean();

    } else {
        $error_message = "La confirmación no es correcta. Por favor, escriba 'REORDENAR' para proceder.";
    }
}
?>
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Reordenar Códigos de Productos</title>
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
    <h1><i class='fas fa-sort-numeric-down'></i> Reordenar Códigos de Productos</h1>
    
    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="message warning"><strong>¡Atención!</strong> Este proceso reescribirá irreversiblemente TODOS los códigos de los productos existentes al formato PROD-001, PROD-002, etc., ordenados por su ID.</div>

    <?php if (!$output): ?>
    <form method='post'>
        <p>Para confirmar la reordenación, escriba <strong>REORDENAR</strong> en el campo de abajo y presione el botón.</p>
        <input type='text' name='confirmacion' placeholder='Escriba REORDENAR'>
        <br>
        <button type='submit' name='proceder' class='btn btn-danger' style='margin-top: 10px;'>Proceder con la Reordenación</button>
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
