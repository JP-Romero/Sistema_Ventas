<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

include("../config/conexion.php");

// Verificar si es administrador
if ($_SESSION['rol'] !== 'admin') {
    die("<h3 style='color: #e74c3c; text-align: center; margin-top: 50px;'>Acceso denegado. Requiere permisos de administrador.</h3>");
}

$error = $exito = "";

// === Crear nuevo rol ===
if (isset($_POST['crear_rol'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $permisos = $_POST['permisos'] ?? [];

    if (empty($nombre)) {
        $error = "El nombre del rol es obligatorio.";
    } else {
        try {
            $permisos_json = json_encode($permisos, JSON_UNESCAPED_UNICODE);
            $stmt = $conexion->prepare("INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $permisos_json]);
            $exito = "Rol '$nombre' creado correctamente.";
            // registrarActividad($conexion, 'crear_rol', "Creó el rol: $nombre");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Ya existe un rol con ese nombre.";
            } else {
                $error = "Error al crear el rol: " . $e->getMessage();
            }
        }
    }
}

// === Eliminar rol ===
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($id > 2) { // Proteger "Administrador" y "Cajero"
        try {
            $stmt = $conexion->prepare("SELECT nombre FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            $rol = $stmt->fetch();
            if ($rol) {
                $stmt = $conexion->prepare("DELETE FROM roles WHERE id = ?");
                $stmt->execute([$id]);
                $exito = "Rol '" . htmlspecialchars($rol['nombre']) . "' eliminado.";
                // registrarActividad($conexion, 'eliminar_rol', "Eliminó el rol: " . $rol['nombre']);
            }
        } catch (PDOException $e) {
            $error = "No se puede eliminar. Este rol está en uso.";
        }
    } else {
        $error = "No puedes eliminar este rol.";
    }
}

// === Obtener todos los roles ===
try {
    $stmt = $conexion->query("SELECT * FROM roles ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar roles.";
    $roles = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Roles - Sistema de Ventas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --accent-hover: #1a5bc5;
            --danger: #e74c3c;
            --success: #27ae60;
            --border: #ddd;
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card: #1f1f1f;
            --text: #e0e0e0;
            --border: #333;
            --accent: #4a90e2;
            --danger: #c0392b;
            --success: #2ecc71;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
            padding: 30px;
        }
        h1, h2 {
            color: var(--accent);
            text-align: center;
        }
        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: var(--danger);
            color: white;
        }
        .alert-exito {
            background: var(--success);
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
        input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--card-bg);
            color: var(--text);
        }
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            background: var(--bg);
            padding: 8px;
            border-radius: 6px;
        }
        button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: var(--accent-hover);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: var(--accent);
            color: white;
        }
        .btn-edit {
            background: #f39c12;
            text-decoration: none;
            color: white;
        }
        .btn-delete {
            background: var(--danger);
            text-decoration: none;
            color: white;
        }
        .btn-edit:hover, .btn-delete:hover {
            transform: scale(0.95);
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
        .table-wrapper {
            overflow-x: auto;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .container {
                padding: 15px;
            }
            h1, h2 {
                font-size: 20px;
            }
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="../panel.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        <h1><i class="fas fa-users-cog"></i> Gestión de Roles</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($exito): ?>
            <div class="alert alert-exito"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <!-- Formulario para crear rol -->
        <h2>Crear Nuevo Rol</h2>
        <form method="POST">
            <div class="form-group">
                <label>Nombre del Rol</label>
                <input type="text" name="nombre" required>
            </div>
            <div class="form-group">
                <label>Descripción (opcional)</label>
                <textarea name="descripcion" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>Permisos</label>
                <div class="checkbox-group">
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ver_panel]" value="1"> Ver Panel</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[realizar_venta]" value="1"> Realizar Venta</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ver_ventas]" value="1"> Ver Ventas</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ver_clientes]" value="1"> Ver Clientes</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ver_reportes]" value="1"> Ver Reportes</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[gestionar_productos]" value="1"> Gestionar Productos</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ajustar_inventario]" value="1"> Ajustar Inventario</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[gestionar_usuarios]" value="1"> Gestionar Usuarios</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[ver_compras]" value="1"> Ver Compras</label>
                    <label class="checkbox-item"><input type="checkbox" name="permisos[gestionar_ajustes]" value="1"> Ajustes del Sistema</label>
                </div>
            </div>
            <button type="submit" name="crear_rol"><i class="fas fa-plus"></i> Crear Rol</button>
        </form>

        <hr style="margin: 30px 0; border-color: var(--border);">

        <!-- Lista de roles -->
        <h2>Roles Existentes</h2>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Permisos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['nombre']) ?></td>
                        <td><?= htmlspecialchars($r['descripcion']) ?></td>
                        <td>
                            <?php
                            $permisos = json_decode($r['permisos'], true) ?: [];
                            $perm_list = [];
                            foreach ($permisos as $p => $val) {
                                if ($val) $perm_list[] = ucwords(str_replace('_', ' ', $p));
                            }
                            echo count($perm_list) > 0 ? implode(', ', $perm_list) : 'Sin permisos';
                            ?>
                        </td>
                        <td>
                            <a href="editar_rol.php?id=<?= $r['id'] ?>" class="btn-edit" style="padding: 5px 10px; margin-right: 5px;"><i class="fas fa-edit"></i></a>
                            <?php if ($r['id'] > 2): ?>
                                <a href="?eliminar=<?= $r['id'] ?>" class="btn-delete" style="padding: 5px 10px;" onclick="return confirm('¿Eliminar este rol? Esta acción no se puede deshacer.')"><i class="fas fa-trash"></i></a>
                            <?php else: ?>
                                <span style="color: #aaa; font-size: 12px;">Bloqueado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
