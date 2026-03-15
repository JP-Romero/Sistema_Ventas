<?php
ob_start();
require 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;
include("conexion.php");

// Validación de conexión
if (!isset($conexion) || mysqli_connect_errno()) {
    ob_end_clean();
    die("Error: No se pudo conectar a la base de datos.");
}

// Validación de parámetros GET
if (!isset($_GET['desde']) || !isset($_GET['hasta'])) {
    ob_end_clean();
    die("Error: Fechas no especificadas");
}

function isValidDate($date) {
    $partes = explode('-', $date);
    return count($partes) === 3 && checkdate($partes[1], $partes[2], $partes[0]);
}

// Sanitización y validación de fechas
$desde = filter_var($_GET['desde'], FILTER_SANITIZE_STRING);
$hasta = filter_var($_GET['hasta'], FILTER_SANITIZE_STRING);

if (!isValidDate($desde) || !isValidDate($hasta)) {
    ob_end_clean();
    die("Error: Fechas inválidas. Use formato YYYY-MM-DD");
}

// Consulta preparada
$sql = "SELECT ventas.fecha, productos.nombre AS producto, ventas.cantidad, ventas.total
        FROM ventas
        JOIN productos ON ventas.producto_id = productos.id
        WHERE ventas.fecha BETWEEN ? AND ?
        ORDER BY ventas.fecha DESC";

$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    ob_end_clean();
    die("Error en la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "ss", $desde, $hasta);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// Construcción del HTML
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th { background-color: #f2f2f2; text-align: left; }
        td, th { padding: 8px; border: 1px solid #ddd; }
        .total { font-weight: bold; background-color: #eef; }
    </style>
</head>
<body>
    <h2>📋 Historial de Ventas</h2>
    <p>Periodo: '.htmlspecialchars($desde).' al '.htmlspecialchars($hasta).'</p>
    <table>
        <tr>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Cantidad</th>
            <th>Total</th>
        </tr>';

$total_general = 0;
while ($fila = mysqli_fetch_assoc($resultado)) {
    $html .= sprintf('
        <tr>
            <td>%s</td>
            <td>%s</td>
            <td>%s</td>
            <td>$%s</td>
        </tr>',
        htmlspecialchars($fila['fecha']),
        htmlspecialchars($fila['producto']),
        htmlspecialchars($fila['cantidad']),
        number_format($fila['total'], 2)
    );
    $total_general += $fila['total'];
}

$html .= '
        <tr class="total">
            <td colspan="3">Total General</td>
            <td>$'.number_format($total_general, 2).'</td>
        </tr>
    </table>
</body>
</html>';

// Generación del PDF
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

ob_end_clean();
header("Content-Type: application/pdf");
$dompdf->stream("historial_ventas_".date('Ymd_His').".pdf", ["Attachment" => false]);

// Limpieza final
mysqli_stmt_close($stmt);
mysqli_close($conexion);
exit;
