<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
require_once("config/conexion.php");

// Verificar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
$usuario = $_SESSION['usuario'];

// 📨 Buscar correo del usuario (reemplaza 'correo' si tu tabla usa otro nombre como 'email')
try {
    $stmtUser = $conexion->prepare("SELECT correo FROM usuarios WHERE usuario = ?");
    $stmtUser->execute([$usuario]);
    $datosUsuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
    $correoDestino = $datosUsuario['correo'] ?? 'mcalebr04@gmail.com';
} catch (PDOException $e) {
    die("❌ Error al obtener correo del usuario: " . $e->getMessage());
}

// 🧪 Consultas de productos
$stock_limit = 5;
try {
    $stmt_criticos = $conexion->prepare("SELECT nombre, stock FROM productos WHERE stock < ?");
    $stmt_criticos->execute([$stock_limit]);
    $resultado_criticos = $stmt_criticos->fetchAll(PDO::FETCH_ASSOC);

    $stmtAgotados = $conexion->prepare("SELECT nombre FROM productos WHERE stock = 0");
    $stmtAgotados->execute();
    $resultadoAgotados = $stmtAgotados->fetchAll(PDO::FETCH_ASSOC);

    $totalCriticos = count($resultado_criticos);
    $totalAgotados = count($resultadoAgotados);

    // 📄 Generar contenido HTML para PDF
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Informe de Inventario</title>
        <style>
            body { font-family: sans-serif; margin: 20px; }
            h2 { color: #2c3e50; text-align: center; margin-bottom: 20px; }
            h3 { color: #34495e; margin-top: 30px; margin-bottom: 10px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; font-size: 14px; }
            th { background-color: #f2f2f2; color: #333; }
            tr:nth-child(even) { background-color: #f9f9f9; }
            .resumen {
                background-color: #fcfcfc;
                padding: 10px 15px;
                border: 1px solid #ccc;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
            }
        </style>
    </head>
    <body>
        <h2>📋 Informe de Inventario</h2>
        <div class="resumen">
            <strong>Resumen general:</strong><br>
            🧯 Productos con stock crítico: <strong>' . $totalCriticos . '</strong><br>
            🚫 Productos agotados: <strong>' . $totalAgotados . '</strong><br>
            🕒 Generado el: ' . date('d/m/Y H:i:s') . '
        </div>
        <h3>🧯 Productos con Stock Crítico</h3>
        <table><thead><tr><th>Producto</th><th>Stock</th></tr></thead><tbody>';
    if ($totalCriticos > 0) {
        foreach ($resultado_criticos as $p) {
            $html .= '<tr><td>' . htmlspecialchars($p["nombre"]) . '</td><td>' . htmlspecialchars($p["stock"]) . '</td></tr>';
        }
    } else {
        $html .= '<tr><td colspan="2">No hay productos con stock crítico.</td></tr>';
    }
    $html .= '</tbody></table>';
    
    $html .= '<h3>🚫 Productos Agotados</h3><table><thead><tr><th>Producto</th></tr></thead><tbody>';
    if ($totalAgotados > 0) {
        foreach ($resultadoAgotados as $p) {
            $html .= '<tr><td>' . htmlspecialchars($p["nombre"]) . '</td></tr>';
        }
    } else {
        $html .= '<tr><td>No hay productos agotados en este momento.</td></tr>';
    }
    $html .= '</tbody></table>
        <p style="text-align:center; margin-top:30px; font-size:12px; color:#777;">
            Informe generado automáticamente por el sistema.
        </p>
    </body>
    </html>';

    // 📄 Crear PDF
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdf_filename = 'inventario_critico_' . date('Ymd_His') . '.pdf';
    $pdf_path = sys_get_temp_dir() . '/' . $pdf_filename;
    file_put_contents($pdf_path, $dompdf->output());

} catch (PDOException $e) {
    die("❌ Error al generar informe: " . $e->getMessage());
}

// ✉️ Enviar correo con PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mcalebr04@gmail.com';          // ⚠️ Reemplaza con tu correo real
    $mail->Password   = 'vtxj rkap zvmo ekuz';  // ⚠️ Contraseña de aplicación desde Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('mcalebr04@gmail.com', 'Sistema de Inventario');
    $mail->addAddress($correoDestino, $usuario);
    $mail->isHTML(true);
    $mail->Subject = ' Informe de Inventario Critico - ' . date('d/m/Y');
    $mail->Body    = "
    <p>Hola <strong>$usuario</strong>,</p>
    <p>Adjunto encontrarás el informe de productos con stock bajo. Por favor, revisa y toma las acciones necesarias.</p>
    <p>Saludos,<br>Sistema de Inventario</p>";
    $mail->AltBody = "Informe adjunto de productos con bajo stock. Consulta el PDF.";
    $mail->addAttachment($pdf_path, $pdf_filename);
    $mail->send();
    echo "<h3>✅ Informe enviado a <strong>$correoDestino</strong></h3>";
} catch (Exception $e) {
    echo "<h3>❌ Error al enviar: {$mail->ErrorInfo}</h3>";
} finally {
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }
}
?>
