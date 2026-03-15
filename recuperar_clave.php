<?php
session_start();
if (isset($_SESSION['usuario'])) {
    header("Location: panel.php");
    exit;
}
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $nueva = $_POST['nueva'];
    $confirmar = $_POST['confirmar'];

    if ($nueva !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        try {
            $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->rowCount() > 0) {
                $hash = password_hash($nueva, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE usuario = ?");
                $stmt->execute([$hash, $usuario]);
                $success = "✅ Contraseña actualizada. Puedes iniciar sesión.";
            } else {
                $error = "Usuario no encontrado.";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar Contraseña</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            width: 360px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            text-align: center;
        }
        h2 {
            color: #2575fc;
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #2575fc;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            margin-top: 10px;
            cursor: pointer;
        }
        .alert {
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
        }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="card">
        <h2><i class="fas fa-key"></i> Recuperar Contraseña</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required><br>
            <input type="password" name="nueva" placeholder="Nueva contraseña" required><br>
            <input type="password" name="confirmar" placeholder="Confirmar contraseña" required><br>
            <button type="submit">Cambiar Contraseña</button>
        </form>
        <br>
        <a href="login.php" style="color: #2575fc;">← Volver al login</a>
    </div>
</body>
</html>