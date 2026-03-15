<?php
session_start();
ob_start();

require 'vendor/autoload.php';
require_once(__DIR__ . "/config/conexion.php");


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

if (!isset($conexion) || !$conexion) {
    ob_end_clean();
    die("❌ Error: No se pudo conectar a la base de datos.");
}

if (!isset($_SESSION['usuario'])) {
    ob_end_clean();
    header("Location: login.php");
    exit;
}

try {
    $stock_limit = 5;
    $stmt = $conexion->prepare("SELECT nombre, stock, categoria, precio FROM productos WHERE stock < ? ORDER BY stock ASC");
    $stmt->execute([$stock_limit]);
    $productosCriticos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean();
    die("❌ Error en la consulta: " . $e->getMessage());
}

// 📊 Generar Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Inventario Crítico');

// Encabezados
$sheet->setCellValue('A1', 'Producto');
$sheet->setCellValue('B1', 'Stock');
$sheet->setCellValue('C1', 'Categoría');
$sheet->setCellValue('D1', 'Precio (C$)');
$sheet->setCellValue('E1', 'Valor Total (C$)');

$sheet->getStyle('A1:E1')->getFont()->setBold(true);
$sheet->getStyle('A1:E1')->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setARGB('FFA0A0A0');

$fila = 2;
$total_general = 0;

if (count($productosCriticos) > 0) {
    foreach ($productosCriticos as $p) {
        $sheet->setCellValue("A{$fila}", htmlspecialchars($p['nombre']));
        $sheet->setCellValue("B{$fila}", $p['stock']);
        $sheet->setCellValue("C{$fila}", htmlspecialchars($p['categoria']));
        $sheet->setCellValue("D{$fila}", $p['precio']);
        $sheet->getStyle("D{$fila}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_SIMPLE);

        $valor_total = $p['stock'] * $p['precio'];
        $sheet->setCellValue("E{$fila}", $valor_total);
        $sheet->getStyle("E{$fila}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_SIMPLE);

        $total_general += $valor_total;
        $fila++;
    }

    // Resumen al final
    $sheet->setCellValue("D{$fila}", "Total Inventario:");
    $sheet->setCellValue("E{$fila}", $total_general);
    $sheet->getStyle("D{$fila}:E{$fila}")->getFont()->setBold(true);
    $sheet->getStyle("E{$fila}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_SIMPLE);
    $sheet->getStyle("D{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
} else {
    $sheet->setCellValue('A2', 'No hay productos con stock crítico.');
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getFont()->setItalic(true);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Ajuste de columnas
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Descargar
if (ob_get_contents()) ob_end_clean();
$filename = 'reporte_inventario_critico_' . date('Ymd_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
