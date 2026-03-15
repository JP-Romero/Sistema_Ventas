<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
include("config/conexion.php");
$usuario = $_SESSION['usuario'];
$error = $exito = "";

// === Función para sanitizar texto ===
function sanitizeText($text) {
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8');
    }
    $text = normalizer_normalize($text, Normalizer::FORM_C);
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

// === Obtener datos del usuario ===
try {
    $stmt = $conexion->prepare("SELECT nombre, correo, telefono, foto_perfil FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $error = "Usuario no encontrado.";
        exit;
    }
    // Valores por defecto
    $nombre = $user['nombre'] ?? '';
    $correo = $user['correo'] ?? '';
    $telefono = $user['telefono'] ?? '';
    $foto_perfil = $user['foto_perfil'] ? 'uploads/perfiles/' . $user['foto_perfil'] : 'img/avatar.png';
} catch (PDOException $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
    $foto_perfil = 'img/avatar.png';
}

// === Obtener rol del usuario (¡ahora va ANTES del HTML!) ===
try {
    $stmt = $conexion->prepare("
        SELECT r.nombre as rol_nombre 
        FROM usuarios u 
        JOIN roles r ON u.rol_id = r.id 
        WHERE u.usuario = ?
    ");
    $stmt->execute([$usuario]);
    $rol = $stmt->fetch(PDO::FETCH_ASSOC);
    // Si no se encuentra rol, asignar valor por defecto
    if (!$rol) {
        $rol = ['rol_nombre' => 'Sin rol asignado'];
    }
} catch (PDOException $e) {
    $rol = ['rol_nombre' => 'Error al cargar'];
}

// === Manejo del formulario ===
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $telefono = trim($_POST['telefono']);
    $contrasena_actual = $_POST['contrasena_actual'] ?? '';
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    // Validación básica
    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico no válido.";
    } else {
        try {
            // Verificar contraseña actual si se quiere cambiar
            if (!empty($nueva_contrasena)) {
                if (empty($contrasena_actual)) {
                    $error = "Debes ingresar tu contraseña actual.";
                } else {
                    $stmt = $conexion->prepare("SELECT contrasena FROM usuarios WHERE usuario = ?");
                    $stmt->execute([$usuario]);
                    $hash = $stmt->fetchColumn();
                    if (!$hash || !password_verify($contrasena_actual, $hash)) {
                        $error = "La contraseña actual es incorrecta.";
                    } elseif ($nueva_contrasena !== $confirmar_contrasena) {
                        $error = "Las nuevas contraseñas no coinciden.";
                    } elseif (strlen($nueva_contrasena) < 6) {
                        $error = "La nueva contraseña debe tener al menos 6 caracteres.";
                    }
                }
            }

            if (!$error) {
                // Subir foto si se envió
                $nombre_foto = $user['foto_perfil']; // mantener la actual
                if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed)) {
                        if ($_FILES['foto']['size'] <= 2 * 1024 * 1024) { // 2MB max
                            $nombre_foto = 'perfil_' . time() . '_' . uniqid() . '.' . $ext;
                            $ruta = 'uploads/perfiles/' . $nombre_foto;
                            // Eliminar foto anterior si existe y no es avatar por defecto
                            if ($user['foto_perfil'] && file_exists('uploads/perfiles/' . $user['foto_perfil'])) {
                                unlink('uploads/perfiles/' . $user['foto_perfil']);
                            }
                            if (move_uploaded_file($_FILES['foto']['tmp_name'], $ruta)) {
                                // Actualizar solo el nombre en BD
                                $stmt = $conexion->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario = ?");
                                $stmt->execute([$nombre_foto, $usuario]);
                            } else {
                                $error = "Error al subir la imagen.";
                            }
                        } else {
                            $error = "La imagen es demasiado grande (máx. 2MB).";
                        }
                    } else {
                        $error = "Formato no permitido. Usa JPG, PNG o WEBP.";
                    }
                }

                // Actualizar datos generales
                $sql = "UPDATE usuarios SET nombre = ?, correo = ?, telefono = ? WHERE usuario = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([$nombre, $correo, $telefono, $usuario]);

                // Actualizar contraseña si se proporcionó
                if (!empty($nueva_contrasena)) {
                    $hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
                    $stmt = $conexion->prepare("UPDATE usuarios SET contrasena = ? WHERE usuario = ?");
                    $stmt->execute([$hash, $usuario]);
                }

                $exito = "Perfil actualizado correctamente.";
                // Refrescar datos
                $user['nombre'] = $nombre;
                $user['correo'] = $correo;
                $user['telefono'] = $telefono;
            }
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - Sistema de Ventas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            --danger: #e74c3c;
            --success: #27ae60;
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card: #1f1f1f;
            --text: #e0e0e0;
            --border: #333;
            --accent: #4a90e2;
            --accent-hover: #3a7bc8;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 30px;
            transition: all 0.4s;
        }
        .container {
            max-width: 700px;
            margin: auto;
            background: var(--card);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .header {
            background: var(--accent);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        .content {
            padding: 30px;
        }
        .alert {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--card);
            color: var(--text);
        }
        .profile-pic-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid var(--accent);
            margin-bottom: 10px;
        }
        .upload-btn {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
        }
        .upload-btn:hover {
            background: var(--accent-hover);
        }
        .btn-save {
            background: linear-gradient(135deg, var(--accent), #6a11cb);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(37, 117, 252, 0.3);
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.4);
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
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
            border: none;
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
        .table-container {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .container {
                width: 100%;
                padding: 0;
                border-radius: 0;
                box-shadow: none;
            }
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="panel.php" class="btn-volver" style="margin-bottom: 20px;"><i class="fas fa-arrow-left"></i> Volver al Panel </a>
        <div class="header">
            <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
            <div style="font-size: 14px; margin-top: 5px;">
                Rol: <strong><?= htmlspecialchars($rol['rol_nombre']) ?></strong>
            </div>
        </div>
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($exito): ?>
                <div class="alert alert-exito"><?= $exito ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <!-- Foto de perfil -->
                <div class="profile-pic-container">
                    <img src="<?= $foto_perfil ?>" alt="Foto de perfil" class="profile-pic" id="previewFoto">
                    <input type="file" name="foto" id="fotoInput" accept="image/*" style="display: none;">
                    <button type="button" class="upload-btn" onclick="document.getElementById('fotoInput').click()">
                        <i class="fas fa-upload"></i> Cambiar foto
                    </button>
                </div>

                <!-- Nombre -->
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" value="<?= htmlspecialchars($nombre) ?>" required>
                </div>

                <!-- Correo -->
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" value="<?= htmlspecialchars($correo) ?>" required>
                </div>

                <!-- Teléfono -->
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" value="<?= htmlspecialchars($telefono) ?>">
                </div>

                <hr style="margin: 25px 0; border-color: var(--border);">

                <!-- Cambio de contraseña -->
                <h3 style="margin-bottom: 15px; color: var(--accent);">Cambiar Contraseña</h3>
                <div class="form-group">
                    <label>Contraseña Actual</label>
                    <input type="password" name="contrasena_actual" placeholder="Deja en blanco si no deseas cambiarla">
                </div>
                <div class="form-group">
                    <label>Nueva Contraseña</label>
                    <input type="password" name="nueva_contrasena" placeholder="Mínimo 6 caracteres">
                </div>
                <div class="form-group">
                    <label>Confirmar Nueva Contraseña</label>
                    <input type="password" name="confirmar_contrasena" placeholder="Repite la nueva contraseña">
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>

            <!-- Historial de Actividades -->
            <h3 style="margin: 30px 0 15px 0; color: var(--accent);">Historial de Actividades</h3>
            <div class="table-wrapper">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Acción</th>
                                <th>Fecha</th>
                                <th>Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $stmt = $conexion->prepare("
                                    SELECT a.accion, a.descripcion, a.fecha, a.ip 
                                    FROM historial_actividades a
                                    JOIN usuarios u ON a.usuario_id = u.id
                                    WHERE u.usuario = ?
                                    ORDER BY a.fecha DESC
                                    LIMIT 5
                                ");
                                $stmt->execute([$usuario]);
                                $actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                if (empty($actividades)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #888;">
                                            <i>No hay actividades recientes.</i>
                                        </td>
                                    </tr>
                                <?php else:
                                    foreach ($actividades as $act): ?>
                                        <tr>
                                            <td><?= ucfirst(str_replace('_', ' ', $act['accion'])) ?></td>
                                            <td><?= date('d/m H:i', strtotime($act['fecha'])) ?></td>
                                            <td style="font-size: 13px; color: #666;">
                                                <?= sanitizeText($act['descripcion']) ?> <br>
                                                <small>IP: <?= $act['ip'] ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach;
                                endif;
                            } catch (PDOException $e) { ?>
                                <tr>
                                    <td colspan="3" style="color: var(--danger);">Error al cargar historial.</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Previsualizar foto
        document.getElementById('fotoInput').onchange = function(e) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewFoto').src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        };
    </script>
</body>
</html>