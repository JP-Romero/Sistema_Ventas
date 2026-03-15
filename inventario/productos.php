<?php
// =================================================================
// BLOQUE DE LÓGICA PHP
// =================================================================
session_start();

// 1. VERIFICACIÓN DE SESIÓN
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// 2. CONEXIÓN A LA BASE DE DATOS
include("../config/conexion.php");

// 3. INICIALIZACIÓN DE VARIABLES Y PARÁMETROS
$busqueda = $_GET['busqueda'] ?? '';
$precio_min = $_GET['precio_min'] ?? '';
$precio_max = $_GET['precio_max'] ?? '';
$categoria_id = $_GET['categoria_id'] ?? '';
$por_pagina = 10;
$pagina_actual = max(1, (int)($_GET['pagina'] ?? 1));

// 4. OBTENCIÓN DE DATOS (CATEGORÍAS)
try {
    $categorias = $conexion->query("SELECT id, nombre FROM categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
    error_log("Error al obtener categorías: " . $e->getMessage());
}

// 5. CONSTRUCCIÓN DE LA CONSULTA SQL CON FILTROS
$sql_base = "FROM productos p LEFT JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
$params = [];

if ($busqueda) {
    $sql_base .= " AND p.nombre LIKE ?";
    $params[] = "%$busqueda%";
}
if ($precio_min !== '' && is_numeric($precio_min)) {
    $sql_base .= " AND p.precio >= ?";
    $params[] = (float)$precio_min;
}
if ($precio_max !== '' && is_numeric($precio_max)) {
    $sql_base .= " AND p.precio <= ?";
    $params[] = (float)$precio_max;
}
if ($categoria_id && is_numeric($categoria_id)) {
    $sql_base .= " AND p.categoria_id = ?";
    $params[] = (int)$categoria_id;
}

// 6. PAGINACIÓN: CÁLCULO DEL TOTAL DE PRODUCTOS
try {
    $stmt_count = $conexion->prepare("SELECT COUNT(*) " . $sql_base);
    $stmt_count->execute($params);
    $total_productos = $stmt_count->fetchColumn();
    $total_paginas = ceil($total_productos / $por_pagina);
} catch (PDOException $e) {
    $total_productos = 0;
    $total_paginas = 1;
    error_log("Error en conteo de productos: " . $e->getMessage());
}

// 7. OBTENCIÓN DE PRODUCTOS PARA LA PÁGINA ACTUAL
$pagina_actual = max(1, min($pagina_actual, $total_paginas));
$offset = ($pagina_actual - 1) * $por_pagina;

try {
    $sql_final = "SELECT p.id, p.nombre, p.codigo, p.precio, p.costo, p.stock, p.stock_minimo, p.activo, c.nombre as cat_nombre "
               . $sql_base . " ORDER BY p.nombre LIMIT " . (int)$por_pagina . " OFFSET " . (int)$offset;
    $stmt = $conexion->prepare($sql_final);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $productos = [];
    error_log("Error al obtener productos: " . $e->getMessage());
}

// 8. FUNCIÓN AUXILIAR PARA LA PAGINACIÓN
function generar_paginacion($pagina_actual, $total_paginas, $params_query) {
    if ($total_paginas <= 1) return '';

    $enlaces = "<ul class='pagination'>";
    $rango = 2;

    // Botón Anterior
    $enlaces .= ($pagina_actual > 1)
        ? "<li><a href='?pagina=" . ($pagina_actual - 1) . "&$params_query'>&lsaquo;</a></li>"
        : "<li class='disabled'><span>&lsaquo;</span></li>";

    // Números de página con lógica de "..."
    $puntos_suspensivos_inicio = false;
    $puntos_suspensivos_fin = false;
    for ($i = 1; $i <= $total_paginas; $i++) {
        if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)) {
            $clase_actual = ($i == $pagina_actual) ? 'current' : '';
            $enlaces .= "<li><a class='$clase_actual' href='?pagina=$i&$params_query'>$i</a></li>";
        } elseif ($i < $pagina_actual && !$puntos_suspensivos_inicio) {
            $enlaces .= "<li class='disabled'><span>...</span></li>";
            $puntos_suspensivos_inicio = true;
        } elseif ($i > $pagina_actual && !$puntos_suspensivos_fin) {
            $enlaces .= "<li class='disabled'><span>...</span></li>";
            $puntos_suspensivos_fin = true;
        }
    }

    // Botón Siguiente
    $enlaces .= ($pagina_actual < $total_paginas)
        ? "<li><a href='?pagina=" . ($pagina_actual + 1) . "&$params_query'>&rsaquo;</a></li>"
        : "<li class='disabled'><span>&rsaquo;</span></li>";

    $enlaces .= "</ul>";
    return $enlaces;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Sistema de Ventas</title>
    
    <!-- FUENTES Y ESTILOS EXTERNOS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <!-- LIBRERÍAS JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- ESTILOS CSS -->
    <style>
        :root {
            --bg: #f4f6f9;
            --card-bg: #ffffff;
            --text: #333;
            --accent: #2575fc;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
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
            --warning: #f1c40f;
            --border: #333;
        }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            padding: 20px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: var(--accent);
            margin-bottom: 30px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            width: 100%;
        }
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        label {
            display: block;
            margin-bottom: 8px;
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
            transition: border-color 0.3s, background-color 0.3s;
        }
        .btn {
            padding: 10px 16px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex; /* Para alinear íconos */
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
        }
        .btn-success { background: var(--success); }
        .btn-danger { background: var(--danger); }
        .btn-warning { background: var(--warning); }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
        
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
        }
        .btn-volver:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 117, 252, 0.45);
            background: linear-gradient(135deg, #1a5bc5, #5a0fb0);
        }
        .btn-volver i { transition: transform 0.3s; }
        .btn-volver:hover i { transform: translateX(-2px); }

        .table-wrapper {
            overflow-x: auto;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.1);
        }
        .table-container {
            /* No styles needed here now, wrapper handles it */
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: var(--accent);
            color: white;
            font-weight: 600;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: rgba(0,0,0,0.02); }
        [data-theme="dark"] tr:hover { background-color: rgba(255,255,255,0.04); }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            color: white;
        }
        .badge-success { background: var(--success); }
        .badge-danger { background: var(--danger); }
        .badge-warning { background: var(--warning); }

        .pagination {
            display: flex;
            justify-content: center;
            margin: 30px 0;
            list-style: none;
            padding: 0;
        }
        .pagination li { margin: 0 4px; }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s;
        }
        .pagination a:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        .pagination .current {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
            font-weight: 600;
        }
        .pagination .disabled span {
            color: #aaa;
            pointer-events: none;
            background: var(--bg);
            border-color: var(--border);
        }

        .theme-toggle {
            position: fixed; top: 20px; right: 20px;
            background: var(--card-bg); color: var(--text);
            border: 1px solid var(--border);
            width: 50px; height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .theme-toggle:hover { transform: scale(1.1); }
        
        .modal {
            display: none; position: fixed; z-index: 1001;
            left: 0; top: 0; width: 100%; height: 100%;
            overflow: auto; background-color: rgba(0,0,0,0.6);
            align-items: center; justify-content: center;
        }
        .modal-content {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            padding-bottom: 15px; border-bottom: 1px solid var(--border); margin-bottom: 15px;
        }
        .modal-header h2 { margin: 0; }
        .close-modal-btn {
            background:none; border:none; font-size: 24px; cursor:pointer; color: var(--text);
        }
        .form-actions { text-align: right; margin-top: 20px; }

        @media (max-width: 768px) {
            .header, .filters {
                flex-direction: column;
                align-items: stretch;
            }
            .filters .form-group, .filters .btn {
                width: 100%;
            }
            .btn-volver {
                align-self: center;
                margin-bottom: 20px;
            }
            .action-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Botón de modo oscuro -->
    <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>

    <!-- Modal para agregar categoría -->
    <div id="modalCategoria" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Agregar Nueva Categoría</h2>
                <button id="cerrarModal" class="close-modal-btn">&times;</button>
            </div>
            <form id="formNuevaCategoria">
                <div class="form-group">
                    <label for="nombreNuevaCategoria">Nombre de la Categoría</label>
                    <input type="text" id="nombreNuevaCategoria" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal para Confirmación de Eliminación Masiva -->
    <div id="modalEliminacion" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalEliminacionTitle">Confirmar Eliminación</h2>
                <button class="close-modal-btn">&times;</button>
            </div>
            <div id="modalEliminacionBody">
                <p id="modalMensajeConfirmacion"></p>
                <div id="modalResultado" style="display:none; max-height: 200px; overflow-y: auto;"></div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn close-modal-btn" style="background-color: #6c757d;">Cerrar</button>
                <button type="button" id="btnConfirmarEliminacion" class="btn btn-danger">Eliminar</button>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="container">
        <a href="/sistema_ventas/panel.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver al Panel</a>

        <h1><i class="fas fa-box"></i> Gestión de Productos</h1>

        <!-- Mensajes de Alerta -->
        <?php if (isset($_GET['msg'])): ?>
            <div style='padding: 15px; margin-bottom: 20px; border-radius: 5px; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;'><?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div style='padding: 15px; margin-bottom: 20px; border-radius: 5px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <!-- Acciones rápidas -->
        <div class="action-buttons" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="agregar_producto.php" class="btn btn-success"><i class="fas fa-plus"></i> Nuevo Producto</a>
            <a href="categorias.php" class="btn btn-warning" style="color: #333;"><i class="fas fa-tags"></i> Categorías</a>
            <a href="proveedores.php" class="btn" style="background: #007bff;"><i class="fas fa-truck"></i> Proveedores</a>
            <a href="importar_productos.php" class="btn" style="background: #1d6f42;"><i class="fas fa-file-import"></i> Importar CSV</a>
            <a href="ajuste_inventario.php" class="btn"><i class="fas fa-wrench"></i> Ajustar Stock</a>
            <a href="historial_ajustes.php" class="btn" style="background: #f39c12;"><i class="fas fa-history"></i> Historial</a>
            <button id="btnEliminarSeleccionados" class="btn btn-danger" disabled><i class="fas fa-trash-alt"></i> Eliminar Seleccionados</button>
        </div>

        <!-- Filtros -->
        <form class="filters" method="GET" id="filtrosForm">
            <div class="form-group">
                <label for="filtroNombre">Buscar por Nombre</label>
                <input type="text" name="busqueda" id="filtroNombre" placeholder="Buscar..." value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
            <div class="form-group">
                <label for="precioMin">Precio Mínimo</label>
                <input type="number" name="precio_min" id="precioMin" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($precio_min); ?>">
            </div>
            <div class="form-group">
                <label for="precioMax">Precio Máximo</label>
                <input type="number" name="precio_max" id="precioMax" step="0.01" placeholder="1000.00" value="<?php echo htmlspecialchars($precio_max); ?>">
            </div>
            <div class="form-group">
                <label for="filtroCategoria">Categoría</label>
                <div style="display: flex; gap: 5px; align-items: center;">
                    <select name="categoria_id" id="filtroCategoria" style="flex-grow: 1;">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $categoria_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="btnAbrirModal" class="btn btn-sm" title="Agregar nueva categoría" style="flex-shrink: 0;">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn"><i class="fas fa-search"></i> Aplicar Filtros</button>
        </form>

        <!-- Tabla de Productos -->
        <div class="table-wrapper">
            <div class="table-container">
                <table id="tablaProductos">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Categoría</th>
                            <th>Costo</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #888; padding: 20px;">
                                    <i>No hay productos registrados que coincidan con los filtros actuales.</i>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p): ?>
                            <tr data-id="<?= htmlspecialchars($p['id']) ?>">
                                <td><input type="checkbox" class="producto-checkbox" value="<?= htmlspecialchars($p['id']) ?>"></td>
                                <td><?php echo htmlspecialchars($p['id']); ?></td>
                                <td><?php echo htmlspecialchars($p['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($p['codigo']); ?></td>
                                <td><?php echo htmlspecialchars($p['cat_nombre'] ?? 'Sin categoría'); ?></td>
                                <td>C$ <?php echo number_format($p['costo'], 2); ?></td>
                                <td>C$ <?php echo number_format($p['precio'], 2); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($p['stock']); ?>
                                    <?php if ($p['stock'] <= $p['stock_minimo']): ?>
                                        <span class="badge badge-danger">Bajo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $p['activo'] ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $p['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
    <?php
    $params_edit = http_build_query([
        'pagina' => $pagina_actual,
        'busqueda' => $busqueda,
        'precio_min' => $precio_min,
        'precio_max' => $precio_max,
        'categoria_id' => $categoria_id
    ]);
    ?>
    <a href="editar_producto.php?id=<?php echo $p['id']; ?>&<?php echo $params_edit; ?>" class="btn btn-sm" style="margin-right: 5px;" title="Editar">
        <i class="fas fa-edit"></i>
    </a>
    <button class="btn btn-sm btn-danger" onclick="confirmarEliminar(<?php echo $p['id']; ?>, '<?php echo addslashes(htmlspecialchars($p['nombre'])); ?>')" title="Eliminar">
        <i class="fas fa-trash"></i>
    </button>
</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginación -->
        <?php
        $query_params = http_build_query([
            'busqueda' => $busqueda,
            'precio_min' => $precio_min,
            'precio_max' => $precio_max,
            'categoria_id' => $categoria_id
        ]);
        echo generar_paginacion($pagina_actual, $total_paginas, $query_params);
        ?>
    </div>

    <!-- ================================================================= -->
    <!-- BLOQUE DE JAVASCRIPT -->
    <!-- ================================================================= -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {

        // === MANEJO DEL MODO OSCURO ===
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme') || 'light';

        function applyTheme(theme) {
            body.setAttribute('data-theme', theme);
            themeToggle.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        }

        applyTheme(savedTheme);

        themeToggle.addEventListener('click', () => {
            const newTheme = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // === CONFIRMACIÓN DE ELIMINACIÓN INDIVIDUAL ===
        window.confirmarEliminar = function(id, nombre) {
            Swal.fire({
                title: '¿Eliminar producto?',
                text: `¿Estás seguro de eliminar "${nombre}"? Esta acción no se puede deshacer.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--danger)',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `eliminar_producto.php?id=${id}`;
                }
            });
        }

        // === LÓGICA DEL MODAL PARA AGREGAR CATEGORÍAS ===
        const modalCategoria = document.getElementById('modalCategoria');
        const btnAbrirModalCat = document.getElementById('btnAbrirModal');
        const btnCerrarModalCat = document.getElementById('cerrarModal');
        const formCategoria = document.getElementById('formNuevaCategoria');
        const selectCategoria = document.getElementById('filtroCategoria');
        const inputCategoria = document.getElementById('nombreNuevaCategoria');

        if (btnAbrirModalCat) {
            btnAbrirModalCat.onclick = () => {
                modalCategoria.style.display = "flex";
                inputCategoria.focus();
            }
        }
        if (btnCerrarModalCat) {
            btnCerrarModalCat.onclick = () => modalCategoria.style.display = "none";
        }
        
        window.onclick = (event) => {
            if (event.target == modalCategoria) {
                modalCategoria.style.display = "none";
            }
        }

        if (formCategoria) {
            formCategoria.addEventListener('submit', function(e) {
                e.preventDefault();
                const nombreCategoria = inputCategoria.value.trim();
                if (nombreCategoria === '') return;

                const formData = new FormData();
                formData.append('nombre', nombreCategoria);

                fetch('agregar_categoria.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const nuevaOpcion = new Option(data.categoria.nombre, data.categoria.id, true, true);
                        selectCategoria.appendChild(nuevaOpcion);
                        inputCategoria.value = '';
                        modalCategoria.style.display = "none";
                        Swal.fire('¡Éxito!', data.message, 'success');
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
                });
            });
        }

        // === LÓGICA PARA ELIMINACIÓN MASIVA CON SELECCIÓN PERSISTENTE ===
        const selectionKey = 'selectedProductIds';
        let selectedProductIds = [];

        const selectAllCheckbox = document.getElementById('selectAll');
        const itemCheckboxes = document.querySelectorAll('.producto-checkbox');
        const deleteSelectedButton = document.getElementById('btnEliminarSeleccionados');
        
        const modalEliminacion = document.getElementById('modalEliminacion');
        const modalEliminacionTitle = document.getElementById('modalEliminacionTitle');
        const btnConfirmarEliminacion = document.getElementById('btnConfirmarEliminacion');
        const btnsCerrarEliminacion = document.querySelectorAll('#modalEliminacion .close-modal-btn');
        const msgConfirmacion = document.getElementById('modalMensajeConfirmacion');
        const divResultado = document.getElementById('modalResultado');

        // Cargar selección desde sessionStorage
        function loadSelection() {
            const storedSelection = sessionStorage.getItem(selectionKey);
            if (storedSelection) {
                selectedProductIds = JSON.parse(storedSelection);
            }
        }

        // Guardar selección en sessionStorage
        function saveSelection() {
            sessionStorage.setItem(selectionKey, JSON.stringify(selectedProductIds));
        }

        // Actualizar la interfaz (botones y checkboxes)
        function updateUI() {
            const count = selectedProductIds.length;
            // Actualizar botón de eliminar
            if (deleteSelectedButton) {
                deleteSelectedButton.disabled = count === 0;
                if (count > 0) {
                    deleteSelectedButton.innerHTML = `<i class="fas fa-trash-alt"></i> Eliminar (${count})`;
                } else {
                    deleteSelectedButton.innerHTML = `<i class="fas fa-trash-alt"></i> Eliminar Seleccionados`;
                }
            }

            // Sincronizar checkboxes individuales
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = selectedProductIds.includes(checkbox.value);
            });

            // Sincronizar checkbox "seleccionar todos"
            if (selectAllCheckbox) {
                const allVisibleIds = Array.from(itemCheckboxes).map(cb => cb.value);
                const allVisibleSelected = allVisibleIds.length > 0 && allVisibleIds.every(id => selectedProductIds.includes(id));
                selectAllCheckbox.checked = allVisibleSelected;
            }
        }

        function closeModalEliminacion() {
            if(modalEliminacion) modalEliminacion.style.display = 'none';
        }

        // Event listener para "Seleccionar Todos"
        if(selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const allVisibleIds = Array.from(itemCheckboxes).map(cb => cb.value);
                if (this.checked) {
                    // Añadir todos los visibles a la selección (evitando duplicados)
                    allVisibleIds.forEach(id => {
                        if (!selectedProductIds.includes(id)) {
                            selectedProductIds.push(id);
                        }
                    });
                } else {
                    // Quitar todos los visibles de la selección
                    selectedProductIds = selectedProductIds.filter(id => !allVisibleIds.includes(id));
                }
                saveSelection();
                updateUI();
            });
        }

        // Event listeners para checkboxes individuales
        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const id = e.target.value;
                if (e.target.checked) {
                    if (!selectedProductIds.includes(id)) {
                        selectedProductIds.push(id);
                    }
                } else {
                    selectedProductIds = selectedProductIds.filter(selId => selId !== id);
                }
                saveSelection();
                updateUI();
            });
        });

        if(deleteSelectedButton) {
            deleteSelectedButton.addEventListener('click', function() {
                if (selectedProductIds.length === 0) return;

                // 1. Mostrar estado de carga
                modalEliminacionTitle.textContent = 'Cargando Productos...';
                msgConfirmacion.innerHTML = '<p>Obteniendo detalles de los productos seleccionados, por favor espere...</p>';
                btnConfirmarEliminacion.style.display = 'none'; // Ocultar botón mientras carga
                divResultado.style.display = 'none';
                if(modalEliminacion) modalEliminacion.style.display = 'flex';

                // 2. Fetch para obtener nombres de los productos
                fetch('get_product_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: selectedProductIds })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.products.length > 0) {
                        // 3. Poblar el modal con la lista de productos
                        modalEliminacionTitle.textContent = 'Confirmar Eliminación';
                        let productListHTML = '¿Está seguro de que desea eliminar los siguientes ' + data.products.length + ' productos?<ul>';
                        data.products.forEach(product => {
                            productListHTML += `<li>${product.nombre} (ID: ${product.id})</li>`;
                        });
                        productListHTML += '</ul>';
                        msgConfirmacion.innerHTML = productListHTML;
                        btnConfirmarEliminacion.style.display = 'inline-block';
                        btnConfirmarEliminacion.disabled = false;
                    } else {
                        // Manejar error o si no se encontraron productos
                        modalEliminacionTitle.textContent = 'Error';
                        msgConfirmacion.textContent = data.message || 'No se pudieron obtener los detalles de los productos seleccionados.';
                    }
                })
                .catch(error => {
                    modalEliminacionTitle.textContent = 'Error de Conexión';
                    msgConfirmacion.textContent = 'No se pudo conectar con el servidor para obtener los detalles de los productos.';
                });
            });
        }

        if(btnConfirmarEliminacion) {
            btnConfirmarEliminacion.addEventListener('click', function() {
                msgConfirmacion.textContent = 'Eliminando, por favor espere...';
                this.disabled = true;

                fetch('eliminar_productos_masivo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: selectedProductIds })
                })
                .then(response => response.json())
                .then(data => {
                    let resultadoHtml = `<h4>Resultados:</h4>`;
                    if (data.success) {
                        resultadoHtml += `<p style="color:green;">${data.results.eliminados} producto(s) eliminado(s).</p>`;
                        if (data.results.fallidos > 0) {
                            resultadoHtml += `<p style="color:red;">${data.results.fallidos} producto(s) no se pudieron eliminar:</p><ul style="text-align:left; font-size:14px;">`;
                            data.results.detalles.forEach(detalle => {
                                resultadoHtml += `<li>${detalle}</li>`;
                            });
                            resultadoHtml += '</ul>';
                        }
                    } else {
                        resultadoHtml += `<p style="color:red;">Error: ${data.message}</p>`;
                    }
                    
                    modalEliminacionTitle.textContent = 'Proceso Completado';
                    msgConfirmacion.style.display = 'none';
                    divResultado.innerHTML = resultadoHtml;
                    divResultado.style.display = 'block';
                    btnConfirmarEliminacion.style.display = 'none';
                    
                    // Limpiar selección y actualizar UI
                    const eliminadosBackend = selectedProductIds.filter(id => 
                        !data.results.detalles.some(d => d.includes(`ID ${id}`))
                    );
                    
                    // Limpiar la selección de los elementos eliminados
                    selectedProductIds = selectedProductIds.filter(id => !eliminadosBackend.includes(id));
                    saveSelection();

                    // Eliminar filas de la tabla dinámicamente
                    eliminadosBackend.forEach(id => {
                        const row = document.querySelector(`tr[data-id='${id}']`);
                        if (row) row.remove();
                    });

                    updateUI();
                })
                .catch(error => {
                    divResultado.innerHTML = `<p style="color:red;"><b>Error de conexión.</b> No se pudo completar la solicitud.</p>`;
                    msgConfirmacion.style.display = 'none';
                    btnConfirmarEliminacion.style.display = 'none';
                });
            });
        }

        btnsCerrarEliminacion.forEach(btn => btn.addEventListener('click', closeModalEliminacion));

        // Carga inicial al cargar la página
        loadSelection();
        updateUI();
    });
    </script>
</body>
</html>