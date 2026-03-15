<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Conexión a la base de datos
include("../config/conexion.php");

$mensaje = '';
$error = '';

// Lógica para manejar acciones (Crear, Actualizar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // Crear nuevo proveedor
        if ($action === 'crear') {
            $stmt = $conexion->prepare("INSERT INTO proveedores (nombre, contacto, telefono, correo, direccion) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['nombre'], $_POST['contacto'], $_POST['telefono'], $_POST['correo'], $_POST['direccion']]);
            $mensaje = 'Proveedor creado exitosamente.';
        }

        // Actualizar proveedor
        if ($action === 'editar') {
            $stmt = $conexion->prepare("UPDATE proveedores SET nombre = ?, contacto = ?, telefono = ?, correo = ?, direccion = ? WHERE id = ?");
            $stmt->execute([$_POST['nombre'], $_POST['contacto'], $_POST['telefono'], $_POST['correo'], $_POST['direccion'], $_POST['id']]);
            $mensaje = 'Proveedor actualizado exitosamente.';
        }

    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Eliminar proveedor
if (isset($_GET['action']) && $_GET['action'] === 'eliminar' && isset($_GET['id'])) {
    $id_a_eliminar = (int)$_GET['id'];

    try {
        // 1. Verificar si el proveedor está en uso
        $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM productos WHERE proveedor_id = ?");
        $stmt_check->execute([$id_a_eliminar]);
        $count = $stmt_check->fetchColumn();

        if ($count > 0) {
            $error = "Error: No se puede eliminar el proveedor porque está asociado a {$count} producto(s).";
        } else {
            // 2. Si no está en uso, proceder a eliminar
            $stmt_delete = $conexion->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt_delete->execute([$id_a_eliminar]);
            $mensaje = 'Proveedor eliminado exitosamente.';
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// Obtener todos los proveedores para mostrarlos en la tabla
$stmt = $conexion->query("SELECT * FROM proveedores ORDER BY nombre");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Proveedores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --border: #dee2e6;
            --shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: var(--accent); margin-bottom: 20px; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-primary { background-color: var(--accent); }
        .btn-danger { background-color: var(--danger); }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .table-container { background: var(--card-bg); border-radius: 12px; overflow: hidden; box-shadow: var(--shadow); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: var(--accent); color: white; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: var(--card-bg); padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: var(--shadow); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; }
        .form-actions { text-align: right; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-actions">
            <h1><i class="fas fa-truck"></i> Gestión de Proveedores</h1>
            <div>
                <a href="productos.php" class="btn" style="background-color: #6c757d;"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
                <button class="btn btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Nuevo Proveedor</button>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Contacto</th>
                        <th>Teléfono</th>
                        <th>Correo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proveedores as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td><?= htmlspecialchars($p['contacto']) ?></td>
                        <td><?= htmlspecialchars($p['telefono']) ?></td>
                        <td><?= htmlspecialchars($p['correo']) ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openModal(<?= json_encode($p) ?>)'><i class="fas fa-edit"></i></button>
                            <a href="?action=eliminar&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar este proveedor?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para Añadir/Editar Proveedor -->
    <div id="proveedorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Proveedor</h2>
                <button onclick="closeModal()" style="background:none; border:none; font-size: 24px; cursor:pointer;">&times;</button>
            </div>
            <form id="proveedorForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="crear">
                <input type="hidden" name="id" id="proveedorId">
                <div class="form-group">
                    <label for="nombre">Nombre del Proveedor</label>
                    <input type="text" id="nombre" name="nombre" required>
                </div>
                <div class="form-group">
                    <label for="contacto">Nombre del Contacto</label>
                    <input type="text" id="contacto" name="contacto">
                </div>
                <div class="form-group">
                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono">
                </div>
                <div class="form-group">
                    <label for="correo">Correo Electrónico</label>
                    <input type="email" id="correo" name="correo">
                </div>
                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn" style="background-color: #6c757d;" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('proveedorModal');
        const modalTitle = document.getElementById('modalTitle');
        const form = document.getElementById('proveedorForm');
        const formAction = document.getElementById('formAction');
        const proveedorId = document.getElementById('proveedorId');

        function openModal(proveedor = null) {
            form.reset();
            if (proveedor) {
                modalTitle.textContent = 'Editar Proveedor';
                formAction.value = 'editar';
                proveedorId.value = proveedor.id;
                document.getElementById('nombre').value = proveedor.nombre;
                document.getElementById('contacto').value = proveedor.contacto;
                document.getElementById('telefono').value = proveedor.telefono;
                document.getElementById('correo').value = proveedor.correo;
                document.getElementById('direccion').value = proveedor.direccion;
            } else {
                modalTitle.textContent = 'Nuevo Proveedor';
                formAction.value = 'crear';
                proveedorId.value = '';
            }
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        <?php if (!empty($mensaje)): ?>
        Swal.fire({ icon: 'success', title: 'Éxito', text: '<?= $mensaje ?>' });
        <?php endif; ?>
        <?php if (!empty($error)): ?>
        Swal.fire({ icon: 'error', title: 'Error', text: '<?= $error ?>' });
        <?php endif; ?>
    </script>
</body>
</html>
