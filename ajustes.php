<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Verificar que el usuario sea administrador
if ($_SESSION['rol'] !== 'admin') {
    die("<script>alert('Acceso denegado. Solo los administradores pueden acceder a esta sección.');window.location.href = 'panel.php';</script>");
}

include("config/conexion.php");

// Verifica que la conexión sea válida
if (!isset($conexion) || !$conexion instanceof PDO) {
    die("Error: No se pudo establecer la conexión con la base de datos.");
}

$mensaje = '';

// === 🔧 FUNCIONES DE CONFIGURACIÓN ===
function getConfig($conexion, $clave, $default = '') {
    try {
        $stmt = $conexion->prepare("SELECT valor FROM configuraciones WHERE clave = ?");
        $stmt->execute([$clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['valor'] : $default;
    } catch (Exception $e) {
        error_log("Error en getConfig($clave): " . $e->getMessage());
        return $default;
    }
}

function setConfig($conexion, $clave, $valor) {
    try {
        $stmt = $conexion->prepare("SELECT id FROM configuraciones WHERE clave = ?");
        $stmt->execute([$clave]);
        if ($stmt->fetch()) {
            $stmt = $conexion->prepare("UPDATE configuraciones SET valor = ? WHERE clave = ?");
            return $stmt->execute([$valor, $clave]);
        } else {
            $stmt = $conexion->prepare("INSERT INTO configuraciones (clave, valor) VALUES (?, ?)");
            return $stmt->execute([$clave, $valor]);
        }
    } catch (Exception $e) {
        error_log("Error en setConfig($clave): " . $e->getMessage());
        return false;
    }
}

// === 📦 CARGAR CONFIGURACIONES ===
$nombre_negocio = getConfig($conexion, 'nombre_negocio', 'Mi Negocio');
$direccion = getConfig($conexion, 'direccion', '');
$telefono = getConfig($conexion, 'telefono', '');
$correo = getConfig($conexion, 'correo', '');
$tipo_cambio = getConfig($conexion, 'tipo_cambio', '36.50');
$iva = getConfig($conexion, 'iva', '15');
$porcentaje_ganancia_global = getConfig($conexion, 'porcentaje_ganancia_global', '30');
$modo_precio = getConfig($conexion, 'modo_precio', 'con_iva');
$intentos_login = getConfig($conexion, 'intentos_login', '3');
$expiracion_sesion = getConfig($conexion, 'expiracion_sesion', '30');
$ancho_ticket = getConfig($conexion, 'ancho_ticket', '8');
$imprimir_logo = getConfig($conexion, 'imprimir_logo', '1');
$mensaje_ticket = getConfig($conexion, 'mensaje_ticket', '¡Gracias por su compra!');

// === 👥 CARGAR USUARIOS ===
try {
    $stmt = $conexion->prepare("SELECT id, usuario, rol FROM usuarios ORDER BY usuario");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $usuarios = [];
    error_log("Error al cargar usuarios: " . $e->getMessage());
}

// === 📦 PROCESAR GUARDADO DE AJUSTES ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ajustes'])) {
    try {
        $conexion->beginTransaction();

        // General
        setConfig($conexion, 'nombre_negocio', $_POST['nombre_negocio'] ?? '');
        setConfig($conexion, 'direccion', $_POST['direccion'] ?? '');
        setConfig($conexion, 'telefono', $_POST['telefono'] ?? '');
        setConfig($conexion, 'correo', $_POST['correo'] ?? '');

        // Ventas
        setConfig($conexion, 'tipo_cambio', $_POST['tipo_cambio'] ?? '36.50');
        setConfig($conexion, 'iva', $_POST['iva'] ?? '15');
        setConfig($conexion, 'porcentaje_ganancia_global', $_POST['porcentaje_ganancia_global'] ?? '30');
        setConfig($conexion, 'modo_precio', $_POST['modo_precio'] ?? 'con_iva');

        // Seguridad
        setConfig($conexion, 'intentos_login', $_POST['intentos_login'] ?? '3');
        setConfig($conexion, 'expiracion_sesion', $_POST['expiracion_sesion'] ?? '30');

        // Impresión
        setConfig($conexion, 'ancho_ticket', $_POST['ancho_ticket'] ?? '8');
        setConfig($conexion, 'imprimir_logo', $_POST['imprimir_logo'] ?? '1');
        setConfig($conexion, 'mensaje_ticket', $_POST['mensaje_ticket'] ?? '¡Gracias por su compra!');

        $conexion->commit();
        $mensaje = "✅ Ajustes guardados correctamente.";
    } catch (Exception $e) {
        $conexion->rollback();
        $mensaje = "❌ Error al guardar: " . $e->getMessage();
    }
}

// === 👤 PROCESAR ACCIONES DE USUARIOS (AJAX) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Acción no válida'];
    $action = $_POST['action'];

    try {
        if ($action === 'nuevo_usuario') {
            $usuario = trim($_POST['usuario']);
            $password = $_POST['password'];
            $rol = $_POST['rol'];

            if (empty($usuario) || empty($password)) {
                $response['message'] = "Todos los campos son obligatorios.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
                $stmt->execute([$usuario, $hash, $rol]);
                $response = ['success' => true, 'id' => $conexion->lastInsertId()];
            }
        } elseif ($action === 'editar_usuario') {
            $usuario = trim($_POST['usuario']);
            $rol = $_POST['rol'];
            $id = (int)$_POST['id'];

            if (empty($usuario)) {
                $response['message'] = "El nombre de usuario es obligatorio.";
            } else {
                $stmt = $conexion->prepare("UPDATE usuarios SET usuario = ?, rol = ? WHERE id = ?");
                $stmt->execute([$usuario, $rol, $id]);
                $response = ['success' => true];
            }
        } elseif ($action === 'cambiar_clave') {
            $password = $_POST['password'];
            $id = (int)$_POST['id'];

            if (empty($password)) {
                $response['message'] = "La contraseña es obligatoria.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $id]);
                $response = ['success' => true];
            }
        } elseif ($action === 'eliminar_usuario') {
            $id = (int)$_POST['id'];
            if ($id == $_SESSION['user_id']) {
                $response['message'] = "No puedes eliminarte a ti mismo.";
            } else {
                $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $response = ['success' => true];
            }
        } elseif ($action === 'obtener') {
            $id = (int)$_POST['id'];
            $stmt = $conexion->prepare("SELECT id, usuario, rol FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $response = $user ? ['success' => true] + $user : ['success' => false, 'message' => 'Usuario no encontrado'];
        }

        $response['success'] = true;
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ajustes del Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --success: #27ae60;
            --danger: #e74c3c;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --success: #2ecc71;
            --danger: #c0392b;
            --border: #333;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
        }
        .tab-container {
            display: flex;
            border-bottom: 1px solid var(--border);
            overflow-x: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .tab-container::-webkit-scrollbar {
            display: none;
        }
        .tab-button {
            padding: 12px 24px;
            background: var(--card-bg);
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: var(--text);
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .tab-button:hover {
            color: var(--accent);
        }
        .tab-button.active {
            border-bottom: 3px solid var(--accent);
            color: var(--accent);
            font-weight: 600;
        }
        .tab-content {
            display: none;
            padding: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            margin-top: -1px; /* Fix for border overlap */
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--card-bg);
            color: var(--text);
        }
        [data-theme="dark"] input, [data-theme="dark"] select {
            background: #2d2d2d;
            border-color: #444;
            color: var(--text);
        }
        .btn {
            padding: 12px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn:hover {
            background: #1a5bc5;
        }
        .btn-danger {
            background: var(--danger);
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        .usuarios-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .usuarios-table th, .usuarios-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .usuarios-table th {
            background: var(--accent);
            color: white;
        }
        .usuarios-table tbody tr:hover {
            background: var(--border);
        }
        .action-btn {
            margin: 0 2px;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .action-edit { background: #f39c12; color: white; }
        .action-pass { background: #3498db; color: white; }
        .action-delete { background: var(--danger); color: white; }
        .action-btn:hover {
            opacity: 0.9;
        }
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--accent);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .theme-toggle:hover {
            transform: scale(1.1);
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
        .danger-zone {
            margin-top: 30px;
            padding: 20px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            color: #721c24;
        }
        .danger-zone h3 {
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .settings-container {
                padding: 0;
            }
            h1 {
                font-size: 24px;
            }
            .tab-button {
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <a href="panel.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        <h1><i class="fas fa-cog"></i> Ajustes del Sistema</h1>

        <?php if ($mensaje): ?>
            <div class="alert <?= strpos($mensaje, 'Error') !== false ? 'alert-error' : '' ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="tab-container">
            <button class="tab-button active" data-tab="general">General</button>
            <button class="tab-button" data-tab="ventas">Ventas</button>
            <button class="tab-button" data-tab="seguridad">Seguridad</button>
            <button class="tab-button" data-tab="impresion">Impresión</button>
            <button class="tab-button" data-tab="usuarios">Usuarios</button>
        </div>

        <form method="POST">
            <input type="hidden" name="guardar_ajustes" value="1">

            <!-- Sección: General -->
            <div id="general" class="tab-content" style="display: block;">
                <h2>Configuración General</h2>
                <div class="form-group">
                    <label>Nombre del Negocio</label>
                    <input type="text" name="nombre_negocio" value="<?= htmlspecialchars($nombre_negocio); ?>" required>
                </div>
                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?= htmlspecialchars($direccion); ?>">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($telefono); ?>">
                </div>
                <div class="form-group">
                    <label>Correo</label>
                    <input type="email" name="correo" value="<?= htmlspecialchars($correo); ?>">
                </div>
            </div>

            <!-- Sección: Ventas -->
            <div id="ventas" class="tab-content">
                <h2>Configuración de Ventas</h2>
                <div class="form-group">
                    <label>Tipo de Cambio (C$ por $1)</label>
                    <input type="number" name="tipo_cambio" value="<?= htmlspecialchars($tipo_cambio); ?>" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>IVA (%)</label>
                    <input type="number" name="iva" value="<?= htmlspecialchars($iva); ?>" min="0" max="100" required>
                </div>
                <div class="form-group">
                    <label>Mostrar Precios</label>
                    <select name="modo_precio">
                        <option value="con_iva" <?= $modo_precio === 'con_iva' ? 'selected' : '' ?>>Con IVA incluido</option>
                        <option value="sin_iva" <?= $modo_precio === 'sin_iva' ? 'selected' : '' ?>>Sin IVA</option>
                    </select>
                </div>
            </div>

            <!-- Sección: Seguridad -->
            <div id="seguridad" class="tab-content">
                <h2>Configuración de Seguridad</h2>
                <div class="form-group">
                    <label>Intentos de Login antes de bloqueo</label>
                    <input type="number" name="intentos_login" value="<?= htmlspecialchars($intentos_login); ?>" min="1" max="10" required>
                </div>
                <div class="form-group">
                    <label>Expiración de sesión (minutos)</label>
                    <input type="number" name="expiracion_sesion" value="<?= htmlspecialchars($expiracion_sesion); ?>" min="1" max="120" required>
                </div>
            </div>

            <!-- Sección: Impresión -->
            <div id="impresion" class="tab-content">
                <h2>Configuración de Impresión</h2>
                <div class="form-group">
                    <label>Ancho del ticket (cm)</label>
                    <input type="number" name="ancho_ticket" value="<?= htmlspecialchars($ancho_ticket); ?>" min="5" max="12" required>
                </div>
                <div class="form-group">
                    <label>Imprimir logo en ticket</label>
                    <select name="imprimir_logo">
                        <option value="1" <?= $imprimir_logo === '1' ? 'selected' : '' ?>>Sí</option>
                        <option value="0" <?= $imprimir_logo === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mensaje final en ticket</label>
                    <input type="text" name="mensaje_ticket" value="<?= htmlspecialchars($mensaje_ticket); ?>" required>
                </div>
            </div>

            <button type="submit" class="btn" style="margin-top: 20px; width: 100%; padding: 15px; font-size: 18px;">
                <i class="fas fa-save"></i> Guardar Todos los Ajustes
            </button>
        </form>

        <!-- Sección: Usuarios -->
        <div id="usuarios" class="tab-content">
            <h2>Gestión de Usuarios</h2>
            <button type="button" class="btn" onclick="abrirModal()">+ Nuevo Usuario</button>
            <div class="table-wrapper">
                <table class="usuarios-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr id="usuario-<?= $u['id'] ?>">
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['usuario']) ?></td>
                                <td><?= htmlspecialchars($u['rol']) ?></td>
                                <td>
                                    <button class="action-btn action-edit" onclick="editarUsuario(<?= $u['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="action-btn action-pass" onclick="cambiarClave(<?= $u['id'] ?>)"><i class="fas fa-key"></i></button>
                                    <button class="action-btn action-delete" onclick="eliminarUsuario(<?= $u['id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Zona de Peligro -->
        <div class="danger-zone">
            <h3><i class="fas fa-exclamation-triangle"></i> Zona de Peligro</h3>
            <p>Estas acciones son irreversibles y pueden afectar todo el sistema.</p>
            <button class="btn btn-danger" onclick="generarRespaldo()">
                <i class="fas fa-download"></i> Descargar Copia de Seguridad
            </button>
        </div>

        <!-- Botón de tema -->
        <button class="theme-toggle" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>

    </div>

    <!-- Modal para Nuevo/Editar Usuario -->
    <div class="modal" id="usuarioModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <h2 id="modalTitle">Nuevo Usuario</h2>
            <form id="usuarioForm">
                <input type="hidden" id="usuarioId" name="id">
                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" id="usuario" name="usuario" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Dejar vacío para mantener">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select id="rol" name="rol" required>
                        <option value="admin">Administrador</option>
                        <option value="cajero">Cajero</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn" style="background: #e74c3c;" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Pestañas
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                const tabId = btn.getAttribute('data-tab');
                document.getElementById(tabId).style.display = 'block';
            });
        });

        // Modal
        const modal = document.getElementById('usuarioModal');
        const form = document.getElementById('usuarioForm');
        let accionActual = 'nuevo_usuario';

        function abrirModal() {
            document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
            form.reset();
            document.getElementById('usuarioId').value = '';
            document.getElementById('password').required = true;
            document.getElementById('password').placeholder = '';
            modal.style.display = 'flex';
        }

        function cerrarModal() {
            modal.style.display = 'none';
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('action', accionActual);

            fetch('ajustes.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(() => {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            });
        });

        function editarUsuario(id) {
            accionActual = 'editar_usuario';
            fetch('ajustes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=obtener&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalTitle').textContent = 'Editar Usuario';
                    document.getElementById('usuarioId').value = data.id;
                    document.getElementById('usuario').value = data.usuario;
                    document.getElementById('rol').value = data.rol;
                    document.getElementById('password').required = false;
                    document.getElementById('password').placeholder = 'Dejar vacío para mantener';
                    modal.style.display = 'flex';
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }

        function cambiarClave(id) {
            accionActual = 'cambiar_clave';
            document.getElementById('modalTitle').textContent = 'Cambiar Contraseña';
            form.reset();
            document.getElementById('usuarioId').value = id;
            document.getElementById('usuario').required = false;
            document.getElementById('rol').required = false;
            document.getElementById('password').required = true;
            document.getElementById('password').placeholder = '';
            modal.style.display = 'flex';
        }

        function eliminarUsuario(id) {
            Swal.fire({
                title: '¿Eliminar usuario?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('ajustes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=eliminar_usuario&id=${id}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById(`usuario-${id}`).remove();
                            Swal.fire('Eliminado', 'Usuario eliminado del sistema.', 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        // Cerrar modal al hacer clic fuera
        window.onclick = (e) => {
            if (e.target === modal) cerrarModal();
        };

        // ✅ Función de respaldo mejorada
        function generarRespaldo() {
            Swal.fire({
                title: '¿Generar copia de seguridad?',
                text: "Se descargará un archivo SQL con toda la base de datos",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2575fc',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, generar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar alerta de carga
                    Swal.fire({
                        title: 'Generando respaldo...',
                        text: 'Por favor espere.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Descargar el respaldo
                    fetch('backup_db.php')
                        .then(response => {
                            if (!response.ok) throw new Error('Error en la red');
                            return response.blob();
                        })
                        .then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `respaldo_${new Date().toISOString().split('T')[0]}.sql`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                            Swal.fire('¡Listo!', 'La copia de seguridad se ha descargado.', 'success');
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            Swal.fire('Error', 'No se pudo generar el respaldo: ' + err.message, 'error');
                        });
                }
            });
        }

        // Modo oscuro
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme') || 'light';

        function applyTheme(theme) {
            if (theme === 'dark') {
                body.setAttribute('data-theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                body.removeAttribute('data-theme');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
            localStorage.setItem('theme', theme);
        }

        applyTheme(savedTheme);
        themeToggle.addEventListener('click', () => {
            const isDark = body.getAttribute('data-theme') === 'dark';
            applyTheme(isDark ? 'light' : 'dark');
        });
    </script>
</body>
</html>