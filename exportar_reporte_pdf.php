<?php
session_start();
require 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

include("config/conexion.php"); // Asumimos que "conexion.php" está en la carpeta config

// Rango de fechas
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// Validación de fechas
function isValidDate($date) {
    $partes = explode('-', $date);
    return count($partes) === 3 && checkdate($partes[1], $partes[2], $partes[0]);
}

if (!isValidDate($desde) || !isValidDate($hasta)) {
    die("❌ Fechas inválidas.");
}

// Consulta de ventas
$stmt = $conexion->prepare("
    SELECT fecha, cantidad, total, productos.nombre
    FROM ventas
    JOIN productos ON ventas.producto_id = productos.id
    WHERE fecha BETWEEN ? AND ?
    ORDER BY fecha DESC
");
$stmt->execute([$desde, $hasta]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construcción de HTML
$html = '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Ventas</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { text-align: center; color: #2c3e50; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th { background-color: #3498db; color: white; padding: 10px; border: 1px solid #ccc; }
    td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    .no-data { text-align: center; font-style: italic; padding: 15px; color: #999; }
    .footer { text-align: center; font-size: 12px; color: #666; margin-top: 30px; }
  </style>
</head>
<body>
  <h2>📋 Reporte de Ventas</h2>
  <p><strong>Rango:</strong> ' . htmlspecialchars($desde) . ' a ' . htmlspecialchars($hasta) . '</p>
  <table>
    <tr><th>Fecha</th><th>Producto</th><th>Cantidad</th><th>Total</th></tr>';

if (count($ventas) > 0) {
    foreach ($ventas as $v) {
        $html .= '<tr>
            <td>' . htmlspecialchars($v['fecha']) . '</td>
            <td>' . htmlspecialchars($v['nombre']) . '</td>
            <td>' . intval($v['cantidad']) . '</td>
            <td>' . number_format($v['total'], 2, '.', ',') . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="4" class="no-data">No hay ventas registradas en este rango.</td></tr>';
}

$html .= '</table>
  <div class="footer">Documento generado automáticamente por el sistema</div>
</body>
</html>';

// Generar PDF
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("reporte_ventas_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);
exit;
