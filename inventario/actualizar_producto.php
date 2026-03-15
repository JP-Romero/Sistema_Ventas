<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'admin') {
    die("<script>alert('Acceso denegado.'); window.location.href='productos.php';</script>");
}

include("../config/conexion.php");

$id = $_POST['id'] ?? null;
if (!$id) {
    die("<script>alert('ID de producto no válido.'); window.location.href='productos.php';</script>");
}

// Capturar parámetros de paginación y filtros para redirección
$pagina = $_POST['pagina'] ?? 1;
$busqueda = $_POST['busqueda'] ?? '';
$precio_min = $_POST['precio_min'] ?? '';
$precio_max = $_POST['precio_max'] ?? '';
$categoria_id = $_POST['categoria_id'] ?? '';

// Obtener los datos del formulario
$nombre = trim($_POST['nombre']);
$codigo = trim($_POST['codigo']);
$descripcion = trim($_POST['descripcion']);
$categoria_id_post = (int)$_POST['categoria_id'];
$proveedor_id = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;
$costo = floatval($_POST['costo']);
$precio = floatval($_POST['precio']);
$stock = (int)$_POST['stock'];
$stock_minimo = (int)($_POST['stock_minimo'] ?? 0);
$unidad_medida = trim($_POST['unidad_medida']);
$activo = (int)$_POST['activo'];

// === Manejo de imagen ===
$imagen_actual = '';
try {
    $stmt_img = $conexion->prepare("SELECT imagen FROM productos WHERE id = ?");
    $stmt_img->execute([$id]);
    $imagen_actual = $stmt_img->fetchColumn();
} catch (Exception $e) {
    // No hacer nada si falla
}

$nombre_imagen = $imagen_actual;

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
    $directorio_destino = "../img/productos/";
    $nombre_nuevo_archivo = 'prod_' . time() . '_' . basename($_FILES['imagen']['name']);
    $ruta_completa = $directorio_destino . $nombre_nuevo_archivo;

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_completa)) {
        if (!empty($imagen_actual) && file_exists($directorio_destino . $imagen_actual)) {
            unlink($directorio_destino . $imagen_actual);
        }
        $nombre_imagen = $nombre_nuevo_archivo;
    }
}

// === Actualizar producto en la base de datos ===
try {
    // Construcción dinámica de la consulta
    $sql_parts = [
        "nombre = ?", "descripcion = ?", "categoria_id = ?", "proveedor_id = ?", "costo = ?",
        "precio = ?", "stock = ?", "stock_minimo = ?", "unidad_medida = ?", "imagen = ?", "activo = ?"
    ];
    
    $params = [
        $nombre, $descripcion, $categoria_id_post, $proveedor_id, $costo, $precio, $stock,
        $stock_minimo, $unidad_medida, $nombre_imagen, $activo
    ];

    // CORRECCIÓN: Usar una comprobación más estricta que permita el código "0"
    if (isset($_POST['codigo']) && $_POST['codigo'] !== '') {
        $sql_parts[] = "codigo = ?";
        $params[] = $codigo;
    }

    $sql = "UPDATE productos SET " . implode(", ", $sql_parts) . " WHERE id = ?";
    $params[] = $id;

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);

    // Redirigir con parámetros y mensaje de éxito
    $redirect_params = http_build_query([
        'pagina' => $pagina,
        'busqueda' => $busqueda,
        'precio_min' => $precio_min,
        'precio_max' => $precio_max,
        'categoria_id' => $categoria_id,
        'msg' => 'Producto actualizado correctamente.'
    ]);

    header("Location: productos.php?" . $redirect_params);
    exit;

} catch (Exception $e) {
    error_log("Error al actualizar producto ID $id: " . $e->getMessage());

    // En caso de error, regresar a editar_producto.php con los mismos parámetros + error
    $error_params = http_build_query([
        'pagina' => $pagina,
        'busqueda' => $busqueda,
        'precio_min' => $precio_min,
        'precio_max' => $precio_max,
        'categoria_id' => $categoria_id,
        'id' => $id
    ]);

    if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'for key \'codigo\'') !== false) {
        header("Location: editar_producto.php?error=codigo_duplicado&" . $error_params);
    } else {
        header("Location: editar_producto.php?error=update_failed&" . $error_params);
    }
    exit;
}
?>