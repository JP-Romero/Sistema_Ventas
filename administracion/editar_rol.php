<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("../config/conexion.php");

// Verificar si es administrador
if ($_SESSION['rol'] !== 'admin') {
    die("<h3 style='color: #e74c3c; text-align: center; margin-top: 50px;'>Acceso denegado. Solo administradores.</h3>");
}

$error = $exito = "";
$rol_id = $_GET['id'] ?? null;

if (!is_numeric($rol_id) || $rol_id <= 2) {
    die("<h3 style='color: #e74c3c; text-align: center;'>Rol no válido o protegido.</h3>");
}

// Obtener datos del rol
try {
    $stmt = $conexion->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$rol_id]);
    $rol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rol) {
        die("<h3 style='color: #e74c3c; text-align: center;'>Rol no encontrado.</h3>");
    }
} catch (PDOException $e) {
    die("Error al cargar rol: " . $e->getMessage());
}

// Manejar actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $permisos = $_POST['permisos'] ?? [];

    if (empty($nombre)) {
        $error = "El nombre del rol es obligatorio.";
    } else {
        try {
            $permisos_json = json_encode($permisos, JSON_UNESCAPED_UNICODE);
            $stmt = $conexion->prepare("UPDATE roles SET nombre = ?, descripcion = ?, permisos = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $permisos_json, $rol_id]);
            $exito = "Rol actualizado correctamente.";
            registrarActividad($conexion, 'editar_rol', "Editó el rol: $nombre");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Ya existe un rol con ese nombre.";
            } else {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Rol - Sistema de Ventas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --accent-hover: #1a5bc5;
            --border: #ddd;
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 30px;
        }
        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 20px;
        }
        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #e74c3c;
            color: white;
        }
        .alert-exito {
            background: #27ae60;
            color: white;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 10px 0;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: var(--accent-hover);
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: var(--accent);
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .btn-volver {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #2575fc, #6a11cb);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(37, 117, 252, 0.35);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .btn-volver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
            background: linear-gradient(135deg, #1a5bc5, #5a0fb0);
        }
        .btn-volver i {
            margin-right: 6px;
            transition: transform 0.3s;
        }
        .btn-volver:hover i {
            transform: translateX(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="roles.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Roles</a>
        <h1><i class="fas fa-edit"></i> Editar Rol: <?= htmlspecialchars($rol['nombre']) ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-exito"><?= $exito ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nombre del Rol</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($rol['nombre']) ?>" required>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3"><?= htmlspecialchars($rol['descripcion']) ?></textarea>
            </div>
            <div class="form-group">
                <label>Permisos</label>
                <div class="checkbox-group">
                    <?php
                    $permisos_actuales = json_decode($rol['permisos'], true) ?: [];
                    $lista_permisos = [
                        'ver_panel' => 'Ver Panel',
                        'realizar_venta' => 'Realizar Venta',
                        'ver_ventas' => 'Ver Ventas',
                        'ver_clientes' => 'Ver Clientes',
                        'ver_reportes' => 'Ver Reportes',
                        'gestionar_productos' => 'Gestionar Productos',
                        'ajustar_inventario' => 'Ajustar Inventario',
                        'gestionar_usuarios' => 'Gestionar Usuarios',
                        'ver_compras' => 'Ver Compras',
                        'gestionar_ajustes' => 'Ajustes del Sistema'
                    ];
                    foreach ($lista_permisos as $key => $label): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="permisos[<?= $key ?>]" value="1" 
                                <?= isset($permisos_actuales[$key]) && $permisos_actuales[$key] ? 'checked' : '' ?>>
                            <?= $label ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>

    </div>
</body>
</html>
