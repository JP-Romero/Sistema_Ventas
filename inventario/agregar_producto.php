<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 🔐 Solo administradores
if ($_SESSION['rol'] !== 'admin') {
    die("
        <script>
            alert('Acceso denegado. Solo los administradores pueden acceder a esta sección.');
            window.location.href = 'panel.php';
        </script>
    ");
}

include("../config/conexion.php");

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $categoria_id = (int)$_POST['categoria_id'];
    $proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
    $costo = floatval($_POST['costo']);
    $precio = floatval($_POST['precio']); // Aceptar precio manual
    $stock = (int)$_POST['stock'];
    $unidad_medida = $_POST['unidad_medida']; // ← ¡Sigue recibiendo este valor normalmente!
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre) || $costo < 0 || $stock < 0 || $precio < 0) {
        $error = "Nombre, costo, precio y stock deben ser válidos.";
    } else {
        try {
            $imagen = '';
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $tmp = $_FILES['imagen']['tmp_name'];
                $nombre_img = 'prod_' . time() . '_' . basename($_FILES['imagen']['name']);
                $ruta = "../img/productos/" . $nombre_img; // ← Asegúrate de que la ruta sea correcta
                if (move_uploaded_file($tmp, $ruta)) {
                    $imagen = $nombre_img;
                }
            }

            // Corregido: El INSERT tenía 'activo' dos veces
            $stmt = $conexion->prepare("
                INSERT INTO productos 
                (nombre, descripcion, precio, stock, categoria_id, proveedor_id, costo, unidad_medida, imagen, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nombre, $descripcion, $precio, $stock, $categoria_id,
                $proveedor_id, $costo, $unidad_medida, $imagen, $activo
            ]);

            $mensaje = "✅ Producto agregado con éxito.";
        } catch (PDOException $e) {
            // Corregido: Mostrar un mensaje de error más específico para duplicados
            if ($e->errorInfo[1] == 1062) {
                $error = "Error: Ya existe un producto con ese nombre o código.";
            } else {
                $error = "Error al guardar: " . $e->getMessage();
            }
        }
    }
}

// Cargar datos para selects
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; padding: 20px; background: #f4f6f9; }
        .form-container { max-width: 700px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
        h1 { color: #2575fc; margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: 500; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; }
        .btn { padding: 12px 20px; background: #2575fc; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn:hover { background: #1a5bc5; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 8px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
        #campoPersonalizado {
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <a href="productos.php" class="btn-volver">
            <i class="fas fa-arrow-left"></i> Volver a Productos
        </a>
        <h1><i class="fas fa-plus"></i> Agregar Nuevo Producto</h1>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Nombre del Producto *</label>
                <input type="text" name="nombre" required>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Categoría *</label>
                <select name="categoria_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Proveedor</label>
                <select name="proveedor_id">
                    <option value="">Sin proveedor</option>
                    <?php foreach ($proveedores as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Costo de Compra *</label>
                <input type="number" name="costo" id="costo" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Precio de Venta *</label>
                <input type="number" name="precio" id="precio" step="0.01" min="0" required>
            </div>
            <div class="form-group">
                <label>Stock Inicial *</label>
                <input type="number" name="stock" min="0" required>
            </div>

            <!-- Unidad de Medida con opción Personalizado -->
            <div class="form-group">
                <label>Unidad de Medida *</label>
                <?php
                $unidades_medida = [
                    'Unidad' => 'Unidad',
                    'Kilogramo' => 'Kilogramo (kg)',
                    'Gramo' => 'Gramo (g)',
                    'Litro' => 'Litro (L)',
                    'Mililitro' => 'Mililitro (ml)',
                    'Metro' => 'Metro (m)',
                    'Centímetro' => 'Centímetro (cm)',
                    'Paquete' => 'Paquete',
                    'Caja' => 'Caja',
                    'Botella' => 'Botella',
                    'Galón' => 'Galón',
                    'Onza' => 'Onza (oz)',
                    'Libra' => 'Libra (lb)',
                    'Pieza' => 'Pieza',
                    'Juego' => 'Juego',
                    'Lata' => 'Lata',
                    'Bolsa' => 'Bolsa',
                    'Personalizado' => 'Personalizado...'
                ];
                ?>
                <select name="unidad_medida_selector" id="unidad_medida_selector" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($unidades_medida as $valor => $etiqueta): ?>
                        <option value="<?php echo htmlspecialchars($valor); ?>">
                            <?php echo htmlspecialchars($etiqueta); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="campoPersonalizado">
                    <label for="unidad_medida_personalizada">Especifique la unidad personalizada:</label>
                    <input type="text" id="unidad_medida_personalizada" name="unidad_medida_personalizada" placeholder="Ej: Caja de 24 unidades">
                </div>
                <input type="hidden" name="unidad_medida" id="unidad_medida" value="Unidad">
            </div>

            <div class="form-group">
                <label>Imagen del Producto</label>
                <input type="file" name="imagen" accept="image/*">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="activo" checked> Activo</label>
            </div>
            <button type="submit" class="btn">Guardar Producto</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const selector = document.getElementById('unidad_medida_selector');
        const campoPersonalizado = document.getElementById('campoPersonalizado');
        const inputHidden = document.getElementById('unidad_medida');
        const inputPersonalizado = document.getElementById('unidad_medida_personalizada');

        function actualizarUnidad() {
            if (selector.value === 'Personalizado') {
                campoPersonalizado.style.display = 'block';
                inputHidden.value = inputPersonalizado.value || 'Personalizado';
            } else {
                campoPersonalizado.style.display = 'none';
                inputHidden.value = selector.value;
            }
        }

        selector.addEventListener('change', actualizarUnidad);
        inputPersonalizado?.addEventListener('input', function() {
            if (selector.value === 'Personalizado') {
                inputHidden.value = this.value || 'Personalizado';
            }
        });

        // Inicializar
        actualizarUnidad();
    });
    </script>
</body>
</html>