<?php
session_start();
require 'vendor/autoload.php';
include("config/conexion.php");
include_once("config/funciones.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

	function isValidDate($date) {
		$d = DateTime::createFromFormat('Y-m-d', $date);
		return $d && $d->format('Y-m-d') === $date;
	}


	$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-7 days'));
	$hasta = $_GET['hasta'] ?? date('Y-m-d');

	if (!isValidDate($desde) || !isValidDate($hasta)) {
    die("❌ Fechas inválidas.");
	}

$sql = "SELECT DATE(fecha) AS dia, SUM(total) AS monto
        FROM ventas
        WHERE fecha BETWEEN ? AND ?
        GROUP BY DATE(fecha)
        ORDER BY dia ASC";

		$stmt = $conexion->prepare($sql);
		$stmt->execute([$desde, $hasta]);
		$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
				if (count($ventas) === 0) {
			echo '
			<!DOCTYPE html>
			<html lang="es">
			<head>
				<meta charset="UTF-8">
				<title>Sin resultados</title>
				<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
			</head>
			<body>
				<script>
					Swal.fire({
						icon: "info",
						title: "Sin ventas en el periodo",
						text: "No se encontraron registros entre ' . $desde . ' y ' . $hasta . '.",
						confirmButtonText: "Cerrar"
					}).then(() => {
						window.location.href = "productos.php"; // 🔁 Reemplaza con tu ruta
					});
				</script>
			</body>
			</html>';
			exit;
		}


		// Crear hoja de cálculo
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle("Informe de Ventas");

		// Encabezado
		$sheet->setCellValue('A1', '📊 Informe de Ventas');
		$sheet->mergeCells('A1:B1');
		$sheet->setCellValue('A2', 'Generado por:');
		$sheet->setCellValue('B2', $_SESSION['usuario']);
		$sheet->setCellValue('A3', 'Fecha de generación:');
		$sheet->setCellValue('B3', date('d/m/Y'));
		$sheet->setCellValue('A4', 'Rango:');
		$sheet->setCellValue('B4', "$desde a $hasta");

		// Encabezados de tabla
		$sheet->setCellValue('A6', 'Fecha');
		$sheet->setCellValue('B6', 'Total Vendido');

		// Estilo de encabezado
		$headerStyle = [
			'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
			'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '3498DB']],
			'alignment' => ['horizontal' => 'center']
		];
		$sheet->getStyle('A6:B6')->applyFromArray($headerStyle);

		// Datos
		$fila = 7;
		$total_general = 0;
		foreach ($ventas as $venta) {
			$sheet->setCellValue("A$fila", $venta['dia']);
			$sheet->setCellValue("B$fila", formatearCordobas($venta['monto']));
			$total_general += $venta['monto'];
			$fila++;
		}

		// Total general
		$sheet->setCellValue("A$fila", "Total General:");
		$sheet->setCellValue("B$fila", formatearCordobas($total_general));
		$sheet->getStyle("A$fila:B$fila")->getFont()->setBold(true);

		// Ajustar ancho
		$sheet->getColumnDimension('A')->setAutoSize(true);
		$sheet->getColumnDimension('B')->setAutoSize(true);

		// Descargar archivo
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="reporte_ventas_' . date('Ymd_His') . '.xlsx"');
		header('Cache-Control: max-age=0');

		$writer = new Xlsx($spreadsheet);
		$writer->save('php://output');
	exit;
