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

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $errores = [];
    $nombre_temporal = $_FILES['archivo_csv']['tmp_name'];

    // Verificar si el archivo es válido
    if ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo.";
    } elseif (!file_exists($nombre_temporal)) {
        $errores[] = "El archivo no se subió correctamente.";
    } elseif (pathinfo($_FILES['archivo_csv']['name'], PATHINFO_EXTENSION) !== 'csv') {
        $errores[] = "Solo se permiten archivos CSV.";
    } else {
        // Definir las columnas esperadas
        $columnas_esperadas = ['nombre', 'codigo', 'precio', 'costo', 'stock', 'stock_minimo', 'categoria_id', 'proveedor'];

        if (($handle = fopen($nombre_temporal, 'r')) !== FALSE) {
            $fila = 0;
            $productos_importados = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $fila++;

                // Limpiar datos
                $data = array_map('trim', $data);

                // Saltar filas vacías
                if (count($data) == 1 && empty($data[0])) continue;

                // Validar número de columnas
                if (count($data) !== count($columnas_esperadas)) {
                    $errores[] = "Fila $fila: Se esperaban " . count($columnas_esperadas) . " columnas, se encontraron " . count($data) . ".";
                    continue;
                }

                // Primera fila: validar encabezados (opcional)
                if ($fila === 1) {
                    $encabezados = array_map('strtolower', $data);
                    if ($encabezados === array_map('strtolower', $columnas_esperadas)) {
                        continue; // Saltar encabezados
                    }
                }

                // Asociar claves y valores
                $producto = array_combine($columnas_esperadas, $data);

                // Validar campos obligatorios
                if (empty($producto['nombre']) || empty($producto['codigo'])) {
                    $errores[] = "Fila $fila: El nombre y código son obligatorios.";
                    continue;
                }

                if (!is_numeric($producto['precio']) || !is_numeric($producto['costo'])) {
                    $errores[] = "Fila $fila: Precio y costo deben ser números.";
                    continue;
                }

                if (!is_numeric($producto['stock']) || !is_numeric($producto['stock_minimo'])) {
                    $errores[] = "Fila $fila: Stock y stock mínimo deben ser números.";
                    continue;
                }

                // Asignar valores por defecto
                $producto['categoria_id'] = is_numeric($producto['categoria_id']) ? $producto['categoria_id'] : 1;
                $producto['proveedor'] = $producto['proveedor'] ?? '';

                // Insertar o actualizar producto
                try {
                    $stmt = $conexion->prepare("
                        INSERT INTO productos (nombre, codigo, precio, costo, stock, stock_minimo, categoria_id, proveedor, activo)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE
                            nombre = VALUES(nombre),
                            precio = VALUES(precio),
                            costo = VALUES(costo),
                            stock = VALUES(stock),
                            stock_minimo = VALUES(stock_minimo),
                            categoria_id = VALUES(categoria_id),
                            proveedor = VALUES(proveedor)
                    ");
                    $stmt->execute([
                        $producto['nombre'],
                        $producto['codigo'],
                        $producto['precio'],
                        $producto['costo'],
                        $producto['stock'],
                        $producto['stock_minimo'],
                        $producto['categoria_id'],
                        $producto['proveedor']
                    ]);
                    $productos_importados++;
                } catch (Exception $e) {
                    $errores[] = "Fila $fila: Error al guardar {$producto['nombre']} - " . $e->getMessage();
                }
            }
            fclose($handle);

            if ($productos_importados > 0) {
                $errores[] = "<strong>✅ Éxito:</strong> Se importaron $productos_importados productos.";
            }
        } else {
            $errores[] = "No se pudo abrir el archivo CSV.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Productos</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --danger: #e74c3c;
            --success: #27ae60;
            --border: #dee2e6;
            --font: 'Poppins', sans-serif;
        }
        [data-theme="dark"] {
            --bg: #121212;
            --card-bg: #1f1f1f;
            --text: #e0e0e0;
            --accent: #4a90e2;
            --danger: #c0392b;
            --success: #2ecc71;
            --border: #333;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        h1 {
            color: var(--accent);
            text-align: center;
            margin-bottom: 30px;
        }
        .btn {
            padding: 12px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #1a5bc5;
        }
        .btn-export {
            background: #1d6f42;
        }
        .btn-export:hover {
            background: #185c39;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--card-bg);
            color: var(--text);
        }
        .errores {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .errores ul {
            margin-top: 10px;
            padding-left: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
       <a href="productos.php" class="btn" style="background-color: #6c757d;"><i class="fas fa-arrow-left"></i> Volver a Productos</a>
        <h1><i class="fas fa-file-import"></i> Importar Productos</h1>

        <!-- Mensajes de error o éxito -->
        <?php if (isset($errores) && !empty($errores)): ?>
            <div class="errores">
                <strong>⚠️ Algunos elementos no se pudieron importar:</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Formulario de importación -->
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Archivo CSV</label>
                <input type="file" name="archivo_csv" accept=".csv" required>
            </div>
            <button type="submit" class="btn">
                <i class="fas fa-upload"></i> Importar Productos
            </button>
        </form>

        <hr style="margin: 30px 0; border-color: var(--border);">

        <!-- Descargar plantilla -->
        <h3>Descargar Plantilla</h3>
        <p>Descarga una plantilla CSV con el formato correcto para importar productos.</p>
        <a href="plantilla_productos.csv" class="btn btn-export" download>
            <i class="fas fa-download"></i> Descargar plantilla CSV
        </a>

        <p style="margin-top: 15px; font-size: 14px; color: #666;">
            <strong>Formato esperado:</strong> nombre, codigo, precio, costo, stock, stock_minimo, categoria_id, proveedor
        </p>
    </div>
</body>
</html>