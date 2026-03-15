<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

include("../config/conexion.php");
$mensaje = '';

// === CRUD de usuarios ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];

    try {
        if ($action === 'agregar') {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['usuario'], $hash, $_POST['rol']]);
            $response = ['success' => true, 'id' => $conexion->lastInsertId()];
        }
        elseif ($action === 'editar') {
            $stmt = $conexion->prepare("UPDATE usuarios SET usuario = ?, rol = ? WHERE id = ?");
            $stmt->execute([$_POST['usuario'], $_POST['rol'], $_POST['id']]);
            $response = ['success' => true];
        }
        elseif ($action === 'cambiar_clave') {
            $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $_POST['id']]);
            $response = ['success' => true];
        }
        elseif ($action === 'eliminar') {
            if ($_POST['id'] == $_SESSION['user_id']) {
                $response['message'] = "No puedes eliminarte a ti mismo.";
            } else {
                $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $response = ['success' => true];
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// === Cargar usuarios ===
$stmt = $conexion->prepare("SELECT id, usuario, rol FROM usuarios ORDER BY usuario");
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --border: #333;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            padding: 20px;
            color: var(--text);
        }
        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
        }
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-warning { background: #f39c12; color: white; }
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: var(--accent);
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <h1><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>

    <button class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo Usuario</button>

    <div class="table-container">
        <table>
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
                <tr>
                    <td><?php echo $u['id']; ?></td>
                    <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                    <td><?php echo ucfirst($u['rol']); ?></td>
                    <td>
                        <button class="btn btn-warning" onclick="editarUsuario(<?php echo $u['id']; ?>)">Editar</button>
                        <button class="btn btn-warning" onclick="cambiarClave(<?php echo $u['id']; ?>)">Cambiar Clave</button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-danger" onclick="eliminarUsuario(<?php echo $u['id']; ?>)">Eliminar</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal Nuevo/Editar -->
    <div class="modal" id="usuarioModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Usuario</h2>
                <span class="close" onclick="cerrarModal()">&times;</span>
            </div>
            <form id="usuarioForm">
                <input type="hidden" id="usuarioId">
                <div style="margin-bottom: 15px;">
                    <label>Usuario *</label>
                    <input type="text" id="usuario" required>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Rol *</label>
                    <select id="rol" required>
                        <option value="admin">Administrador</option>
                        <option value="cajero">Cajero</option>
                        <option value="invitado">Invitado</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label>Contraseña <?php echo isset($editando) ? '(opcional)' : '*' ?></label>
                    <input type="password" id="password" <?php echo !isset($editando) ? 'required' : ''; ?>>
                </div>
                <div style="text-align: right;">
                    <button type="button" class="btn btn-danger" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalNuevo() {
            document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
            document.getElementById('usuarioForm').reset();
            document.getElementById('usuarioId').value = '';
            document.getElementById('password').required = true;
            document.getElementById('usuarioModal').style.display = 'flex';
        }

        function editarUsuario(id) {
            fetch('usuarios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=obtener&id=${id}`
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('modalTitle').textContent = 'Editar Usuario';
                document.getElementById('usuarioId').value = data.id;
                document.getElementById('usuario').value = data.usuario;
                document.getElementById('rol').value = data.rol;
                document.getElementById('password').required = false;
                document.getElementById('password').placeholder = 'Dejar vacío para mantener';
                document.getElementById('usuarioModal').style.display = 'flex';
            });
        }

        function cambiarClave(id) {
            Swal.fire({
                title: 'Cambiar contraseña',
                html: `
                    <input type="password" id="swal-password" class="swal2-input" placeholder="Nueva contraseña" required>
                    <input type="password" id="swal-confirm" class="swal2-input" placeholder="Confirmar contraseña" required>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cambiar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const pwd = document.getElementById('swal-password').value;
                    const conf = document.getElementById('swal-confirm').value;
                    if (pwd !== conf) {
                        Swal.showValidationMessage('Las contraseñas no coinciden');
                        return false;
                    }
                    return pwd;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('usuarios.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=cambiar_clave&id=${id}&password=${result.value}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Éxito', 'Contraseña actualizada.', 'success');
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
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
                    fetch('usuarios.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=eliminar&id=${id}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    });
                }
            });
        }

        document.getElementById('usuarioForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const id = document.getElementById('usuarioId').value;
            const action = id ? 'editar' : 'agregar';
            formData.append('action', action);
            if (id) formData.append('id', id);

            fetch('usuarios.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Éxito', 'Usuario guardado.', 'success');
                    cerrarModal();
                    location.reload();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        });

        function cerrarModal() {
            document.getElementById('usuarioModal').style.display = 'none';
        }

        window.onclick = (e) => {
            const modal = document.getElementById('usuarioModal');
            if (e.target === modal) cerrarModal();
        };
    </script>
</body>
</html>
