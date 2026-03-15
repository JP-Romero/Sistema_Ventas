<?php
function formatearCordobas($monto) {
  return 'C$ ' . number_format($monto, 2, '.', ',');
}
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function exportarProductosExcel(PDO $conexion)
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Productos');

    // Encabezados
    $sheet->setCellValue('A1', 'ID');
    $sheet->setCellValue('B1', 'Nombre');
    $sheet->setCellValue('C1', 'Precio');
    $sheet->setCellValue('D1', 'Stock');
    $sheet->setCellValue('E1', 'Categoría');

    $row = 2;
    $total_general = 0;

    $productos = $conexion->query("SELECT * FROM productos");
    foreach ($productos as $producto) {
        $sheet->setCellValue("A$row", $producto['id']);
        $sheet->setCellValue("B$row", $producto['nombre']);
        $sheet->setCellValue("C$row", $producto['precio']);
        $sheet->getStyle("C$row")->getNumberFormat()
              ->setFormatCode('#,##0.00 [$C$-419]');
        $sheet->setCellValue("D$row", $producto['stock']);
        $sheet->setCellValue("E$row", $producto['categoria']);
        $total_general += $producto['precio'];
        $row++;
    }

    // Total general
    $sheet->setCellValue("B$row", "Total General:");
    $sheet->setCellValue("C$row", $total_general);
    $sheet->getStyle("B$row:C$row")->getFont()->setBold(true);
    $sheet->getStyle("C$row")->getAlignment()->setHorizontal('right');
    $sheet->getStyle("C$row")->getNumberFormat()
          ->setFormatCode('#,##0.00 [$C$-419]');

    // Descargar Excel al navegador
    if (ob_get_contents()) {
        ob_end_clean(); // Limpia el buffer si hay contenido
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="productos.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

