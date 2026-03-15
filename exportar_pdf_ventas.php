<?php
session_start();
require 'vendor/autoload.php';
include("config/conexion.php");
include_once("config/funciones.php");

use Dompdf\Dompdf;

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Validar fechas
function isValidDate($date) {
    $partes = explode('-', $date);
    return count($partes) === 3 && checkdate($partes[1], $partes[2], $partes[0]);
}

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!isValidDate($desde) || !isValidDate($hasta)) {
    die("❌ Fechas inválidas.");
}

// Consulta de ventas
$sql = "SELECT DATE(fecha) AS dia, SUM(total) AS monto
        FROM ventas
        WHERE fecha BETWEEN ? AND ?
        GROUP BY DATE(fecha)
        ORDER BY dia ASC";

$stmt = $conexion->prepare($sql);
$stmt->execute([$desde, $hasta]);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir HTML
$html = '<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Informe de Ventas</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .encabezado { text-align: center; margin-bottom: 20px; }
    h2 { color: #34495e; margin-bottom: 10px; }
    .datos { font-size: 14px; margin-bottom: 8px; color: #555; }
    .tabla { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .tabla th { background-color: #3498db; color: white; padding: 10px; text-align: center; }
    .tabla td { padding: 10px; text-align: center; border: 1px solid #ccc; }
    .firma { margin-top: 30px; font-size: 14px; color: #333; text-align: right; }
    .footer { margin-top: 20px; font-size: 12px; color: #777; text-align: center; }
    .no-data { text-align: center; font-style: italic; color: #777; padding: 15px; }
  </style>
</head>
<body>
  <div class="encabezado">
    <h2>📈 Informe de Ventas</h2>
    <h2>🏥 Farmacia Fuentes</h2>
    <div class="datos">
      Generado por: <strong>' . htmlspecialchars($_SESSION['usuario']) . '</strong><br>
      Fecha de generación: ' . date("d/m/Y") . '<br>
      Rango: ' . htmlspecialchars($desde) . ' a ' . htmlspecialchars($hasta) . '
    </div>
  </div>

  <table class="tabla">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Total Vendido</th>
      </tr>
    </thead>
    <tbody>';

$total_general = 0;

if (count($ventas) > 0) {
    foreach ($ventas as $fila) {
        $html .= '<tr>
          <td>' . htmlspecialchars($fila['dia']) . '</td>
          <td>' . formatearCordobas($fila['monto']) . '</td>
        </tr>';
        $total_general += $fila['monto'];
    }

    $html .= '<tr>
      <td style="text-align: right; font-weight: bold;">Total General:</td>
      <td style="font-weight: bold;">' . formatearCordobas($total_general) . '</td>
    </tr>';
} else {
    $html .= '<tr><td colspan="2" class="no-data">No hay datos de ventas para el rango seleccionado.</td></tr>';
}

$html .= '</tbody>
  </table>
  <div class="firma">Farmacia Fuentes</div>
  <div class="footer">Documento generado automáticamente</div>
</body>
</html>';

// Generar PDF
$dompdf = new Dompdf();
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Enviar PDF al navegador
header("Content-Type: application/pdf");
$dompdf->stream("reporte_ventas_" . date('Ymd_His') . ".pdf", ["Attachment" => false]);
exit;
