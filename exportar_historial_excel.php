<?php
// ¡¡¡MUY IMPORTANTE!!!
// Asegúrate de que NO HAYA NADA (ni espacios, ni líneas en blanco, ni caracteres invisibles como BOM)
// ANTES de esta etiqueta '<?php'. Cualquier salida temprana corromperá el archivo Excel.
// Revisa también 'conexion.php' y cualquier otro archivo incluido para lo mismo.

// Habilita la visualización de errores para depuración (desactivar en producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicia el almacenamiento en búfer de salida.
// Esto captura cualquier salida (errores, espacios, etc.) para evitar que corrompa el archivo Excel.
ob_start();

// Inicia la sesión PHP
session_start();

// Incluye el autoloader de Composer. Esto cargará PhpSpreadsheet.
// Asegúrate de haber ejecutado 'composer install' en tu proyecto.
require 'vendor/autoload.php';

// Incluye el archivo de conexión a la base de datos
// ¡IMPORTANTE! Asegúrate de que 'conexion.php' no tenga NINGUNA salida (espacios, líneas en blanco)
// fuera de las etiquetas PHP '<?php ... ?>'.
include("conexion.php");

// Importa las clases necesarias de PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Verifica si la conexión a la base de datos es válida
if (!isset($conexion) || !$conexion) {
    ob_end_clean(); // Limpia el búfer antes de morir
    die("Error crítico: No se pudo establecer la conexión con la base de datos.");
}

// Verifica si el usuario ha iniciado sesión. Si no, redirige a la página de login.
$usuario = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
if (!$usuario) {
    ob_end_clean(); // Limpia el búfer antes de redirigir
    header("Location: login.php");
    exit;
}

// Obtiene y sanitiza las fechas del parámetro GET
// Se usa htmlspecialchars para prevenir XSS si los valores se mostraran en HTML,
// pero el casteo a string y el uso en prepared statements es lo principal para SQLi.
$desde = isset($_GET['desde']) ? htmlspecialchars($_GET['desde']) : date('Y-m-d', strtotime('-7 days'));
$hasta = isset($_GET['hasta']) ? htmlspecialchars($_GET['hasta']) : date('Y-m-d');

// Valida que las fechas sean válidas (formato YYYY-MM-DD)
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $desde) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $hasta)) {
    ob_end_clean(); // Limpia el búfer antes de morir
    die("Error: Formato de fecha inválido.");
}

// Crea una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Historial de Ventas"); // Título actualizado

// Establece los encabezados de la tabla en la primera fila
$sheet->setCellValue('A1', 'Fecha');
$sheet->setCellValue('B1', 'Producto');
$sheet->setCellValue('C1', 'Cantidad');
$sheet->setCellValue('D1', 'Total');

// Aplica estilos básicos a los encabezados
$sheet->getStyle('A1:D1')->getFont()->setBold(true);
$sheet->getStyle('A1:D1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0'); // Gris claro

// Consulta el historial de ventas usando sentencia preparada para prevenir inyección SQL
$sql_consulta = "
    SELECT v.fecha, p.nombre AS producto, v.cantidad, v.total
    FROM ventas v
    JOIN productos p ON v.producto_id = p.id
    WHERE v.fecha BETWEEN ? AND ?
    ORDER BY v.fecha ASC
";
$stmt_consulta = mysqli_prepare($conexion, $sql_consulta);

if ($stmt_consulta) {
    // Vincula los parámetros de fecha a la declaración preparada
    mysqli_stmt_bind_param($stmt_consulta, "ss", $desde, $hasta); // 'ss' para dos strings

    // Ejecuta la declaración
    mysqli_stmt_execute($stmt_consulta);

    // Obtiene el resultado
    $resultado = mysqli_stmt_get_result($stmt_consulta);

    $fila = 2; // Comienza desde la fila 2 para los datos (después de los encabezados)
    if (mysqli_num_rows($resultado) > 0) {
        while ($venta = mysqli_fetch_assoc($resultado)) {
            // Inserta los datos en las celdas correspondientes
            $sheet->setCellValue("A{$fila}", $venta['fecha']);
            $sheet->setCellValue("B{$fila}", $venta['producto']);
            $sheet->setCellValue("C{$fila}", $venta['cantidad']);
            $sheet->setCellValue("D{$fila}", $venta['total']);
            $fila++;
        }
    } else {
        // Mensaje si no hay datos en el rango de fechas
        $sheet->setCellValue('A2', 'No hay datos de ventas para el rango de fechas seleccionado.');
        $sheet->mergeCells('A2:D2'); // Combina celdas para el mensaje
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    }

    // Cierra la declaración preparada
    mysqli_stmt_close($stmt_consulta);
} else {
    // Manejo de error si la preparación de la consulta falla
    ob_end_clean(); // Limpia el búfer antes de morir
    die("Error al preparar la consulta de ventas: " . mysqli_error($conexion));
}

// Ajusta automáticamente el ancho de las columnas
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Prepara la descarga del archivo Excel
$writer = new Xlsx($spreadsheet);
$filename = 'historial_ventas_' . date('Ymd_His') . '.xlsx'; // Nombre de archivo con fecha y hora

// Limpia el búfer de salida antes de enviar las cabeceras y el contenido del archivo.
ob_end_clean();

// Configura las cabeceras HTTP para forzar la descarga del archivo
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0'); // No cachear el archivo

// Envía el archivo al navegador
$writer->save('php://output');

// Termina la ejecución del script
exit;
