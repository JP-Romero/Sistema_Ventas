<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    die("Acceso denegado.");
}

include("../config/conexion.php");

// Verificar si es administrador
$es_admin = false;
try {
    $stmt = $conexion->prepare("SELECT r.permisos FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ?");
    $stmt->execute([$_SESSION['usuario']]);
    $permisos_json = $stmt->fetchColumn();
    $permisos = json_decode($permisos_json, true);
    $es_admin = isset($permisos['*']) && $permisos['*'];
} catch (Exception $e) {
    die("Error de autenticación.");
}

if (!$es_admin) {
    die("Acceso no autorizado.");
}

// Filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_accion = $_GET['accion'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Consulta principal
$sql = "SELECT a.*, u.usuario as nombre_usuario FROM historial_actividades a JOIN usuarios u ON a.usuario_id = u.id WHERE 1=1";
$params = [];

if ($filtro_usuario) { $sql .= " AND u.usuario LIKE ?"; $params[] = "%$filtro_usuario%"; }
if ($filtro_accion) { $sql .= " AND a.accion = ?"; $params[] = $filtro_accion; }
if ($filtro_fecha) { $sql .= " AND DATE(a.fecha) = ?"; $params[] = $filtro_fecha; }
$sql .= " ORDER BY a.fecha DESC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$actividades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Datos para gráficos
$por_usuario = [];
foreach ($actividades as $a) {
    $usuario = $a['nombre_usuario'];
    $por_usuario[$usuario] = ($por_usuario[$usuario] ?? 0) + 1;
}

$por_accion = [];
foreach ($actividades as $a) {
    $accion = $a['accion'];
    $por_accion[$accion] = ($por_accion[$accion] ?? 0) + 1;
}

// Incluir TCPDF
require_once('tcpdf/tcpdf.php');

class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(37, 117, 252);
        $this->Cell(0, 15, 'Informe de Auditoría - Sistema de Ventas', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Generado el: ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + $this->GetPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'], $this->GetY());
        $this->Ln(5);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// === 🔥 LIMPIAR CUALQUIER SALIDA ANTERIOR 🔥 ===
if (ob_get_length()) {
    ob_end_clean();
}

// === Crear PDF ===
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Ventas');
$pdf->SetTitle('Informe de Auditoría');
$pdf->SetSubject('Actividades del sistema');
$pdf->SetMargins(20, 40, 20);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->AddPage();

// === Filtros aplicados ===
$html = '
<h3>Filtros Aplicados</h3>
<table border="1" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <tr><td><strong>Usuario:</strong></td><td>' . ($filtro_usuario ?: 'Todos') . '</td></tr>
    <tr><td><strong>Acción:</strong></td><td>' . ($filtro_accion ?: 'Todas') . '</td></tr>
    <tr><td><strong>Fecha:</strong></td><td>' . ($filtro_fecha ?: 'Todas') . '</td></tr>
</table>
<hr>
<h3>Resumen Estadístico</h3>';

$pdf->writeHTML($html, true, false, true, false, '');

// === Gráfico 1: Actividades por usuario ===
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(37, 117, 252);
$pdf->Cell(0, 10, 'Actividades por Usuario', 0, 1);

if (empty($por_usuario)) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'No se encontraron actividades.', 0, 1);
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(37, 117, 252);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.2);
    $pdf->Ln(5);

    $max = max($por_usuario);
    foreach ($por_usuario as $usuario => $count) {
        $width = ($count / $max) * 120; // Reduced width from 150
        $pdf->Cell(40, 8, $usuario, 1, 0, 'L', 0);
        $pdf->Cell($width, 8, $count, 1, 0, 'C', 1);
        $pdf->Ln(8);
    }
}
$pdf->Ln(10);

// === Tabla de actividades ===
if (!empty($actividades)) {
    $header = ['Usuario', 'Acción', 'Fecha', 'Descripción', 'IP'];
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(37, 117, 252);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);

    $col_widths = [25, 25, 30, 65, 25]; // Adjusted widths: Usuario, Acción, Fecha, Descripción, IP
    foreach ($header as $i => $col) {
        $pdf->Cell($col_widths[$i], 7, $col, 1, 0, 'C', 1);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;

    foreach ($actividades as $act) {
        $pdf->Cell($col_widths[0], 6, $act['nombre_usuario'], 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[1], 6, ucfirst(str_replace('_', ' ', $act['accion'])), 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[2], 6, date('d/m H:i', strtotime($act['fecha'])), 1, 0, 'C', $fill);
        // Use Cell instead of MultiCell and truncate the description
        $descripcion_corta = substr($act['descripcion'], 0, 45);
        $pdf->Cell($col_widths[3], 6, $descripcion_corta, 1, 0, 'L', $fill);
        $pdf->Cell($col_widths[4], 6, $act['ip'], 1, 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
}

// === Generar PDF (descarga) ===
$filename = 'auditoria_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'D');
// No pongas nada después de aquí