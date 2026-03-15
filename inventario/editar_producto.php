<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    die("<script>alert('Acceso denegado.'); window.location.href='productos.php';</script>");
}

include("../config/conexion.php");

$id = $_GET['id'] ?? null;

if (!$id) {
    die("<script>alert('Producto no especificado.'); window.location.href='productos.php';</script>");
}

// Capturar parámetros para regresar después de guardar
$pagina = $_GET['pagina'] ?? 1;
$busqueda = $_GET['busqueda'] ?? '';
$precio_min = $_GET['precio_min'] ?? '';
$precio_max = $_GET['precio_max'] ?? '';
$categoria_id = $_GET['categoria_id'] ?? '';

// Obtener producto
try {
    $stmt = $conexion->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) throw new Exception("Producto no encontrado");
} catch (Exception $e) {
    die("<script>alert('{$e->getMessage()}'); window.location.href='productos.php';</script>");
}

// Obtener categorías y proveedores
$categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$proveedores = $conexion->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
            color: var(--text);
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        h1 {
            color: var(--accent);
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            background: var(--card-bg);
            color: var(--text);
        }
        .btn {
            padding: 12px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn:hover { transform: translateY(-2px); }
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
        #campoPersonalizado {
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        // Construir URL de regreso con parámetros
        $volver_params = http_build_query([
            'pagina' => $pagina,
            'busqueda' => $busqueda,
            'precio_min' => $precio_min,
            'precio_max' => $precio_max,
            'categoria_id' => $categoria_id
        ]);
        ?>
        <a href="productos.php?<?php echo $volver_params; ?>" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
        <h1><i class="fas fa-edit"></i> Editar Producto</h1>

        <form method="POST" action="actualizar_producto.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">

            <!-- Campos ocultos para mantener la paginación y filtros -->
            <input type="hidden" name="pagina" value="<?php echo htmlspecialchars($pagina); ?>">
            <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>">
            <input type="hidden" name="precio_min" value="<?php echo htmlspecialchars($precio_min); ?>">
            <input type="hidden" name="precio_max" value="<?php echo htmlspecialchars($precio_max); ?>">
            <input type="hidden" name="categoria_id" value="<?php echo htmlspecialchars($categoria_id); ?>">

            <div class="form-group">
                <label>Nombre del Producto</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
            </div>

            <div class="form-group">
                <label>Código de Producto</label>
                <input type="text" name="codigo" value="<?php echo htmlspecialchars($producto['codigo'] ?? ''); ?>">
                <small>Dejar en blanco para no cambiar. Debe ser único.</small>
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Categoría</label>
                <select name="categoria_id" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $producto['categoria_id'] == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Proveedor</label>
                <select name="proveedor_id">
                    <option value="">Ninguno</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?php echo $prov['id']; ?>" <?php echo $producto['proveedor_id'] == $prov['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($prov['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Costo de Compra</label>
                <input type="number" step="0.01" name="costo" id="costo" value="<?php echo $producto['costo']; ?>" required>
            </div>

            <div class="form-group">
                <label>Precio de Venta</label>
                <input type="number" step="0.01" name="precio" id="precio" value="<?php echo $producto['precio']; ?>" required>
            </div>

            <div class="form-group">
                <label>Stock</label>
                <input type="number" name="stock" value="<?php echo $producto['stock']; ?>" required>
            </div>

            <div class="form-group">
                <label>Stock Mínimo</label>
                <input type="number" name="stock_minimo" value="<?php echo $producto['stock_minimo']; ?>">
            </div>

            <!-- Unidad de Medida con opción Personalizado -->
            <div class="form-group">
                <label>Unidad de Medida</label>
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
                        <option value="<?php echo htmlspecialchars($valor); ?>" <?php echo ($producto['unidad_medida'] ?? '') === $valor ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($etiqueta); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="campoPersonalizado" <?php echo ($producto['unidad_medida'] ?? '') === 'Personalizado' ? 'style="display:block;"' : ''; ?>>
                    <label for="unidad_medida_personalizada">Especifique la unidad personalizada:</label>
                    <input type="text" id="unidad_medida_personalizada" name="unidad_medida_personalizada" 
                           value="<?php echo ($producto['unidad_medida'] ?? '') === 'Personalizado' ? htmlspecialchars($producto['descripcion_unidad'] ?? '') : ''; ?>" 
                           placeholder="Ej: Caja de 24 unidades">
                </div>
                <input type="hidden" name="unidad_medida" id="unidad_medida" value="<?php echo htmlspecialchars($producto['unidad_medida'] ?? 'Unidad'); ?>">
            </div>

            <div class="form-group">
                <label>Imagen del Producto</label>
                <input type="file" name="imagen" accept="image/*">
                <?php if ($producto['imagen']): ?>
                    <img src="<?php echo '../img/productos/' . $producto['imagen']; ?>" alt="Imagen actual" style="width: 100px; margin-top: 10px; border-radius: 8px;">
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Activo</label>
                <select name="activo">
                    <option value="1" <?php echo $producto['activo'] ? 'selected' : ''; ?>>Sí</option>
                    <option value="0" <?php echo !$producto['activo'] ? 'selected' : ''; ?>>No</option>
                </select>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
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